#!/usr/bin/env python3
"""Validate and compile module translations with GNU gettext."""

from __future__ import annotations

import argparse
import ast
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from extract_pot import Module, discover_modules, parse_pot_entries, template_path


def placeholder_set(text: str, expected: set[str] | None = None) -> set[str]:
    # strtr matches known placeholders before any attached word suffix.
    choices = [re.escape(value) for value in sorted(expected or (), key=len, reverse=True)]
    return set(re.findall("|".join([*choices, r"(?<!:):[A-Za-z_][A-Za-z0-9_]*"]), text))


def read_entries(path: Path) -> list[dict]:
    """Read canonical GNU msgcat output; gettext handles PO syntax and encoding."""
    entries: list[dict] = []
    entry: dict = {"fuzzy": False}
    field: str | None = None
    for line in path.read_text(encoding="utf-8").split("\n") + [""]:
        if not line.strip():
            if "msgid" in entry:
                entries.append(entry)
            entry = {"fuzzy": False}
            field = None
        elif line.startswith("#, "):
            entry["fuzzy"] = "fuzzy" in line[3:].split(", ")
        elif line.startswith("#"):
            continue
        elif line.startswith('"'):
            if field is None:
                raise ValueError(f"{path}: unexpected continuation in gettext output")
            entry[field] += ast.literal_eval(line)
        else:
            match = re.fullmatch(r'(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+(".*")', line)
            if match is None:
                raise ValueError(f"{path}: unexpected gettext output: {line}")
            field = match[1]
            entry[field] = ast.literal_eval(match[2])
    return entries


def validate_entries(po: Path, canonical: Path, active: Path, expected: dict[str, str | None]) -> bool:
    entries = read_entries(canonical)
    # Check source identities even for fuzzy entries excluded from compilation.
    for entry in entries:
        msgid = entry["msgid"]
        if "msgctxt" in entry:
            raise ValueError(f"{po}: message contexts are not supported by the module translation runtime")
        if msgid == "":
            continue
        plural = entry.get("msgid_plural")
        if msgid not in expected or expected[msgid] != plural:
            raise ValueError(f"{po}: stale or mismatched entry {msgid!r}; merge the current module template")
    entries = read_entries(active)
    header = next((entry.get("msgstr", "") for entry in entries if entry["msgid"] == ""), "")
    plural_header = re.search(r"\bnplurals\s*=\s*(\d+)", header)
    plural_count = int(plural_header.group(1)) if plural_header else 0
    usable = False
    for entry in entries:
        msgid = entry["msgid"]
        if msgid == "":
            continue
        plural = entry.get("msgid_plural")
        allowed = [placeholder_set(msgid)]
        if plural:
            allowed.append(placeholder_set(plural))
            slots = {int(key[7:-1]): value for key, value in entry.items() if key.startswith("msgstr[")}
            forms = list(slots.values())
            if any(forms) and (plural_count == 0 or set(slots) != set(range(plural_count))):
                raise ValueError(f"{po}: missing plural forms for {msgid!r}; expected {plural_count} slots")
            if any(forms) and not all(forms):
                raise ValueError(f"{po}: incomplete plural translation for {msgid!r}; translate every form or leave all forms empty")
        else:
            forms = [entry.get("msgstr", "")]
        for translation in forms:
            if translation and not any(placeholder_set(translation, source) == source for source in allowed):
                raise ValueError(f"{po}: placeholders do not match {msgid!r}")
        usable |= any(forms)
    return usable


def run_gettext(command: list[str], po: Path) -> None:
    result = subprocess.run(command, capture_output=True, text=True)
    if result.returncode:
        raise ValueError(f"{po}: {Path(command[0]).name} failed\n{result.stderr.strip()}")


def compile_translations(modules: list[Module], templates: Path, output: Path | None) -> int:
    compiler, normalizer = shutil.which("msgfmt"), shutil.which("msgcat")
    attributes = shutil.which("msgattrib")
    if compiler is None or normalizer is None or attributes is None:
        raise ValueError("GNU gettext msgfmt, msgcat, and msgattrib are required to validate or compile translations")
    if output is not None and output.exists() and (not output.is_dir() or any(output.iterdir())):
        raise ValueError(f"compile output must be a new or empty staging directory: {output}")
    checked = 0
    compiled: list[tuple[Path, Path]] = []
    with tempfile.TemporaryDirectory(prefix="fossbilling-module-mo-") as temporary:
        for module in modules:
            expected = parse_pot_entries(str(template_path(templates, module)))
            for po in sorted((module.path / "locale").rglob("*.po")):
                relative = po.relative_to(module.path / "locale")
                if (
                    len(relative.parts) != 3
                    or relative.parts[1:] != ("LC_MESSAGES", f"{module.id}.po")
                    or not re.fullmatch(r"[a-z]{2,3}(?:_[A-Za-z0-9]+)*", relative.parts[0])
                ):
                    raise ValueError(f"{po}: expected locale/<locale>/LC_MESSAGES/{module.id}.po")
                mo = Path(temporary) / f"{checked}.mo"
                canonical = Path(temporary) / f"{checked}.po"
                filtered = Path(temporary) / f"{checked}-filtered.po"
                active = Path(temporary) / f"{checked}-active.po"
                run_gettext([compiler, "--check", "--check-format", str(po), "-o", os.devnull], po)
                # msgcat can drop fuzzy flags on first-empty plural entries.
                # Filter drafts from the original PO, before normalization.
                run_gettext([attributes, "--no-fuzzy", "--no-obsolete", "--force-po", str(po), "-o", str(filtered)], po)
                run_gettext([normalizer, "--no-wrap", "--force-po", "--to-code=UTF-8", str(po), "-o", str(canonical)], po)
                run_gettext([normalizer, "--no-wrap", "--force-po", "--to-code=UTF-8", str(filtered), "-o", str(active)], po)
                if validate_entries(po, canonical, active, expected):
                    # Compile UTF-8 input even on gettext versions before 0.22,
                    # which otherwise preserve the source PO's encoding.
                    run_gettext([compiler, "--check", "--check-format", str(active), "-o", str(mo)], po)
                    compiled.append((mo, Path(module.path.name) / "locale" / relative.with_suffix(".mo")))
                checked += 1
        if output is not None:
            for mo, relative in compiled:
                target = output / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(mo, target)
    print(f"Validated {checked} PO files; {len(compiled)} contain usable translations.")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    for name in ("validate", "compile"):
        command = commands.add_parser(
            name,
            help="Validate PO files" if name == "validate" else "Compile validated translations into a clean staging directory",
        )
        command.add_argument("root", type=Path)
        command.add_argument("--templates", required=True, type=Path, help="Directory containing generated per-module templates")
        if name == "compile":
            command.add_argument("--output", required=True, type=Path)
    args = parser.parse_args(argv)
    try:
        modules = discover_modules(args.root)
        return compile_translations(modules, args.templates, getattr(args, "output", None))
    except (ValueError, OSError) as error:
        print(f"error: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
