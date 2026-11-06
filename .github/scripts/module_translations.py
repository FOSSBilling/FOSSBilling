#!/usr/bin/env python3
"""Extract, validate, and compile first-party module translations without Crowdin."""

from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from dataclasses import dataclass
from pathlib import Path

import polib

from extract_pot import (
    Occurrence,
    cmd_diff,
    extract_all,
    normalized_pot,
    parse_pot_entries,
    write_pot,
)


@dataclass(frozen=True)
class Module:
    id: str
    path: Path


def discover_modules(root: Path) -> list[Module]:
    if not root.is_dir():
        raise ValueError(f"modules directory does not exist: {root}")
    directories = [root] if (root / "manifest.json").is_file() else sorted(root.iterdir())
    modules: list[Module] = []
    for directory in directories:
        manifest = directory / "manifest.json"
        if not directory.is_dir():
            continue
        if not manifest.is_file():
            if (directory / "Service.php").is_file() or any((directory / name).is_dir() for name in ("templates", "Api", "Controller")):
                raise ValueError(f"missing module manifest: {manifest}")
            continue
        data = json.loads(manifest.read_text(encoding="utf-8"))
        if not isinstance(data, dict) or data.get("manifest_version") != 1 or data.get("type") != "module":
            raise ValueError(f"{manifest}: expected a v1 module manifest")
        module_id = data.get("id")
        if not isinstance(module_id, str) or not re.fullmatch(r"[a-z][a-z0-9_-]*", module_id) or module_id == "core":
            raise ValueError(f"{manifest}: invalid or reserved module ID")
        if directory.name != module_id[0].upper() + module_id[1:]:
            raise ValueError(f"{manifest}: directory must match Ucfirst(id)")
        modules.append(Module(module_id, directory))
    if not modules:
        raise ValueError(f"no module manifests found in {root}")
    return modules


def module_occurrences(modules: list[Module]) -> tuple[dict[str, list[Occurrence]], list[Occurrence]]:
    by_source: dict[str, list[Occurrence]] = {module.id: [] for module in modules}
    core: list[Occurrence] = []
    errors: list[str] = []
    for module in modules:
        occurrences, stats = extract_all(str(module.path), module.id)
        errors.extend(f"{module.path.name}/{error}" for error in stats["source_errors"])
        for occurrence in occurrences:
            occurrence = occurrence._replace(ref=f"{module.path.name}/{occurrence.ref}")
            if occurrence.source == "core":
                core.append(occurrence)
            elif occurrence.source in by_source:
                by_source[occurrence.source].append(occurrence)
            else:
                errors.append(f"{occurrence.ref}: unknown module translation source {occurrence.source!r}")
    if errors:
        raise ValueError("\n".join(errors))
    return by_source, core


def template_path(root: Path, module: Module) -> Path:
    return root / module.path.name / "locale" / f"{module.id}.pot"


def extract_templates(modules: list[Module], core_pot: Path, output: Path, check: bool, single_module: bool = False) -> int:
    by_source, core = module_occurrences(modules)
    core_entries = parse_pot_entries(str(core_pot))
    errors = [
        f"{occ.ref}: {occ.msgid!r} is not a matching shared core entry; use the module's from argument"
        for occ in core
        if occ.msgid not in core_entries or core_entries[occ.msgid] != occ.plural
    ]
    known_templates = {template_path(output, module) for module in modules}
    if output.is_dir():
        errors.extend(
            f"{path}: template has no corresponding module; review its removal manually"
            for path in sorted(output.glob(f"{modules[0].path.name if single_module else '*'}/locale/*.pot"))
            if path not in known_templates
        )
    if errors:
        raise ValueError("\n".join(errors))

    changed: list[tuple[Path, Path]] = []
    # Validate every source before writing any template, including plural conflicts.
    with tempfile.TemporaryDirectory(prefix="fossbilling-module-pot-") as temporary:
        for module in modules:
            generated = Path(temporary) / f"{module.id}.pot"
            count = write_pot(by_source[module.id], str(generated), f"FOSSBilling module {module.id}", strict=True)
            target = template_path(output, module)
            if not target.is_file() or normalized_pot(str(target)) != normalized_pot(str(generated)):
                changed.append((generated, target))
                print(f"{module.id}: {count} entries; {'needs regeneration' if check else 'updated'}")
                if target.is_file():
                    cmd_diff(str(target), str(generated))
            else:
                print(f"{module.id}: {count} entries; unchanged")
        if check:
            return int(bool(changed))
        for generated, target in changed:
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(generated, target)
    return 0


def placeholder_set(text: str, expected: set[str] | None = None) -> set[str]:
    # strtr matches known placeholders before any attached word suffix.
    choices = [re.escape(value) for value in sorted(expected or (), key=len, reverse=True)]
    return set(re.findall("|".join([*choices, r"(?<!:):[A-Za-z_][A-Za-z0-9_]*"]), text))


def validate_entries(po: Path, expected: dict[str, str | None]) -> polib.MOFile:
    translations = polib.pofile(str(po))
    # Build the MO file directly to retain partially translated plural entries.
    compiled = polib.MOFile(encoding="utf-8")
    compiled.metadata = translations.metadata.copy()
    compiled.metadata["Content-Type"] = "text/plain; charset=UTF-8"
    plural_header = re.search(r"\bnplurals\s*=\s*(\d+)", translations.metadata.get("Plural-Forms", ""))
    plural_count = int(plural_header.group(1)) if plural_header else 0
    for entry in translations:
        if entry.obsolete:
            continue
        if entry.msgctxt is not None:
            raise ValueError(f"{po}: message contexts are not supported by the module translation runtime")
        if entry.msgid not in expected or expected[entry.msgid] != (entry.msgid_plural or None):
            raise ValueError(f"{po}: stale or mismatched entry {entry.msgid!r}; merge the current module template")
        if entry.fuzzy:
            continue
        allowed = [placeholder_set(entry.msgid)]
        if entry.msgid_plural:
            allowed.append(placeholder_set(entry.msgid_plural))
        forms = list(entry.msgstr_plural.values()) if entry.msgid_plural else [entry.msgstr]
        # msgfmt skips this count check when the first form is empty.
        if entry.msgid_plural and any(forms) and (
            len(forms) != plural_count or set(entry.msgstr_plural) != set(range(plural_count))
        ):
            raise ValueError(f"{po}: missing plural forms for {entry.msgid!r}; expected {plural_count} slots")
        for translation in forms:
            if translation and not any(placeholder_set(translation, source) == source for source in allowed):
                raise ValueError(f"{po}: placeholders do not match {entry.msgid!r}")
        if any(forms):
            compiled.append(polib.MOEntry(
                msgid=entry.msgid,
                msgid_plural=entry.msgid_plural,
                msgstr=entry.msgstr,
                msgstr_plural=entry.msgstr_plural,
            ))
    return compiled


def compile_translations(modules: list[Module], templates: Path, output: Path | None) -> int:
    compiler = shutil.which("msgfmt")
    if compiler is None:
        raise ValueError("GNU gettext msgfmt is required to validate or compile translations")
    if output is not None and output.exists() and (not output.is_dir() or any(output.iterdir())):
        raise ValueError(f"compile output must be a new or empty staging directory: {output}")
    compiled: list[tuple[polib.MOFile, Path]] = []
    checked = 0
    for module in modules:
        template = template_path(templates, module)
        expected = parse_pot_entries(str(template))
        for po in sorted((module.path / "locale").rglob("*.po")):
            relative = po.relative_to(module.path / "locale")
            if (
                len(relative.parts) != 3
                or relative.parts[1:] != ("LC_MESSAGES", f"{module.id}.po")
                or not re.fullmatch(r"[a-z]{2,3}(?:_[A-Za-z0-9]+)*", relative.parts[0])
            ):
                raise ValueError(f"{po}: expected locale/<locale>/LC_MESSAGES/{module.id}.po")
            result = subprocess.run(
                [compiler, "--check", "--check-format", str(po), "-o", os.devnull],
                capture_output=True, text=True,
            )
            if result.returncode:
                raise ValueError(f"{po}: msgfmt failed\n{result.stderr.strip()}")
            translations = validate_entries(po, expected)
            if translations:
                compiled.append((translations, Path(module.path.name) / "locale" / relative.with_suffix(".mo")))
            checked += 1
    if output is not None:
        for translations, relative in compiled:
            target = output / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            translations.save(str(target))
    print(f"Validated {checked} PO files; {len(compiled)} contain usable translations.")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    extract = commands.add_parser("extract", help="Generate one template per module into a staging directory")
    extract.add_argument("root", type=Path, help="Modules repository or single Ucfirst(id) module directory")
    extract.add_argument("--core-pot", required=True, type=Path, help="Pinned core template used to validate shared core references")
    extract.add_argument("--output", required=True, type=Path)
    extract.add_argument("--check", action="store_true", help="Report stale templates without modifying them")
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
        if args.command == "extract":
            return extract_templates(modules, args.core_pot, args.output, args.check, (args.root / "manifest.json").is_file())
        return compile_translations(modules, args.templates, getattr(args, "output", None))
    except (ValueError, OSError) as error:
        print(f"error: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
