#!/usr/bin/env python3
"""Extract gettext msgids from FOSSBilling sources into a messages.pot file.

Open-source replacement for the manual Poedit Pro step. Scans Twig
``|trans`` literals and PHP ``__trans`` / ``__pluralTrans`` / exception
keywords under ``src/`` (minus vendor, install, data, load.php, tests and
web-asset extensions); obsolete msgids are dropped.

Usage:
    extract_pot.py <src_dir> <out.pot>
    extract_pot.py --diff <old.pot> <new.pot>   # msgid-set comparison,
        # flagging casing-only changes (gettext matches case-sensitively,
        # so those silently invalidate existing translations)
"""

from __future__ import annotations

import argparse
import bisect
import datetime
import os
import re
import sys
from typing import NamedTuple

# ---------------------------------------------------------------------------
# Configuration (mirrors the historical Poedit runs + project decisions)
# ---------------------------------------------------------------------------

EXCLUDED_DIR_NAMES = {"vendor", "install", "data", "tests"}
EXCLUDED_FILES = {"load.php"}
EXCLUDED_EXTENSIONS = {".js", ".html", ".css", ".scss", ".md"}
SCANNED_EXTENSIONS = {".php", ".twig"}

PHP_KEYWORDS_SINGLE = {
    "__trans",
    "Exception",
    "InformationException",
    "Server_Exception",
    "Registrar_Exception",
    "Payment_Exception",
}
PHP_KEYWORDS_PLURAL = {"__pluralTrans"}

HEADER_TEMPLATE = """\
# en_US translation of FOSSBilling Application
# Copyright 2022-2026 FOSSBilling
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: 4\\n"
"POT-Creation-Date: {date}\\n"
"PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\\n"
"Last-Translator: FOSSBilling TM <info@fossbilling.org>\\n"
"Language-Team: FOSSBilling TM <info@fossbilling.org>\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n"
"X-Generator: FOSSBilling extract_pot.py\\n"
"""

# ---------------------------------------------------------------------------
# String decoding / encoding helpers
# ---------------------------------------------------------------------------

_PHP_DOUBLE_ESCAPES = {
    "n": "\n",
    "r": "\r",
    "t": "\t",
    "v": "\v",
    "e": "\x1b",
    "f": "\f",
    "\\": "\\",
    "$": "$",
    '"': '"',
}


def _decode_php_double(body: str) -> str:
    """Decode a PHP double-quoted string body (no surrounding quotes)."""

    def repl(match: re.Match[str]) -> str:
        seq = match.group(1)
        if seq in _PHP_DOUBLE_ESCAPES:
            return _PHP_DOUBLE_ESCAPES[seq]
        if seq.startswith("x"):
            return chr(int(seq[1:] or "0", 16))
        if seq[0].isdigit():
            return chr(int(seq, 8) & 0xFF)
        return match.group(0)

    return re.sub(r"\\(x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)", repl, body, flags=re.DOTALL)


def _decode_single(body: str) -> str:
    """Decode a PHP/Twig single-quoted string body (only \\\\ and \\' are special)."""
    return body.replace("\\\\", "\x00").replace("\\'", "'").replace("\x00", "\\")


def po_escape(text: str) -> str:
    out = text.replace("\\", "\\\\").replace('"', '\\"')
    out = out.replace("\n", "\\n").replace("\r", "\\r").replace("\t", "\\t")
    return out


# ---------------------------------------------------------------------------
# Comment masking (replaced with spaces, newlines preserved -> offsets stable)
# ---------------------------------------------------------------------------

_PHP_TOKEN = re.compile(
    r"(?P<heredoc><<<\s*['\"]?(?P<htag>[A-Za-z_][A-Za-z0-9_]*)['\"]?[^\n]*\n)"
    r"|(?P<dstr>\"(?:\\.|[^\"\\])*\")"
    r"|(?P<sstr>'(?:\\.|[^'\\])*')"
    r"|(?P<linec>//[^\n]*|#[^\n]*)"
    r"|(?P<blockc>/\*.*?\*/)",
    re.DOTALL,
)


def _mask_php_comments(text: str) -> tuple[str, list[tuple[int, int]]]:
    """Blank out PHP comments, honouring strings and heredocs (offsets stable).

    Also returns the spans of ordinary string literals and heredoc bodies so
    callers can ignore keyword-like text inside string content (which real
    xgettext never extracts).
    """
    out: list[str] = []
    spans: list[tuple[int, int]] = []
    pos = 0
    for match in _PHP_TOKEN.finditer(text):
        kind = match.lastgroup
        if kind == "heredoc":
            tag = match.group("htag")
            end = re.search(rf"^[ \t]*{re.escape(tag)}[ \t;]*$", text[match.end():], re.MULTILINE)
            stop = match.end() + end.end() if end else len(text)
            spans.append((match.start(), stop))
            out.append(text[pos:stop])
            pos = stop
            continue
        out.append(text[pos : match.start()])
        token = match.group(0)
        if kind in ("linec", "blockc"):
            out.append(re.sub(r"[^\n]", " ", token))
        else:
            spans.append((match.start(), match.end()))
            out.append(token)
        pos = match.end()
    out.append(text[pos:])
    return "".join(out), spans


def _in_spans(spans: list[tuple[int, int]], starts: list[int], offset: int) -> bool:
    """Whether offset falls inside a string/heredoc span (spans are sorted)."""
    idx = bisect.bisect_right(starts, offset) - 1
    return idx >= 0 and spans[idx][0] <= offset < spans[idx][1]


_TWIG_COMMENT = re.compile(r"\{#.*?#\}", re.DOTALL)


def _mask_twig_comments(text: str) -> str:
    return _TWIG_COMMENT.sub(lambda m: re.sub(r"[^\n]", " ", m.group(0)), text)


# ---------------------------------------------------------------------------
# Extraction
# ---------------------------------------------------------------------------

# A quoted literal immediately feeding the |trans filter, e.g.
#   {{ 'Order status'|trans }}   {{ 'Hello'|trans({...}) }}   {{ 'Guest' |trans }}
_TWIG_TRANS = re.compile(
    r"""'(?P<sbody>(?:\\.|[^'\\])*)'\s*\|\s*trans(?![A-Za-z0-9_])"""
    r"""|"(?P<dbody>(?:\\.|[^"\\])*)"\s*\|\s*trans(?![A-Za-z0-9_])"""
)

_PHP_STR = r"""'(?P<sbody>(?:\\.|[^'\\])*)'|"(?P<dbody>(?:\\.|[^"\\])*)\""""

_KEYWORD_ALTS = "|".join(
    sorted(PHP_KEYWORDS_SINGLE | PHP_KEYWORDS_PLURAL, key=len, reverse=True)
)
# Matches bare calls AND `new [Namespace\]Keyword(`. The lookbehind rejects
# method/static calls (->, ::), variables and longer identifiers
# (e.g. the `Exception` in `InformationException`).
_PHP_CALL = re.compile(
    rf"(?:new\s+)?(?<![\w$>:\\])(?:\\)?(?:[A-Za-z_][\w]*\\)*(?P<kw>{_KEYWORD_ALTS})\s*\(\s*"
    rf"(?P<arg>{_PHP_STR})?"
    rf"(?P<rest>\s*,)?",
    re.DOTALL,
)

# xgettext treats a double-quoted PHP literal as non-constant (and skips the
# call) when it contains an unescaped `$` starting an interpolation
# (`$var`, `${var}`, `{$var}` …). A `$` followed by anything else
# (e.g. `$5`) is literal and kept. Single-quoted strings never interpolate.
_PHP_DYNAMIC_DQOUTE = re.compile(r"(?<!\\)\$(?=[A-Za-z_{\x80-\xff])")

class Occurrence(NamedTuple):
    msgid: str
    plural: str | None
    ref: str


def _line_no(text: str, offset: int) -> int:
    return text.count("\n", 0, offset) + 1


def extract_twig(rel: str, text: str, occurrences: list[Occurrence], stats: dict) -> None:
    masked = _mask_twig_comments(text)
    for match in _TWIG_TRANS.finditer(masked):
        if match.group("sbody") is not None:
            msgid = _decode_single(match.group("sbody"))
        else:
            body = match.group("dbody")
            if "#{" in body:
                stats["skipped_dynamic"] += 1
                stats["skipped"].append(f"{rel}:{_line_no(masked, match.start())} interpolated Twig string")
                continue
            msgid = _decode_php_double(body)
        occurrences.append(Occurrence(msgid, None, f"{rel}:{_line_no(masked, match.start())}"))
        stats["twig"] += 1


def _read_php_literal(masked: str, start: int) -> tuple[str, int, bool] | None:
    """Read one quoted literal at offset.

    Returns (value, end_offset, is_dynamic_double) or None. Double-quoted
    literals containing an interpolation are flagged dynamic (xgettext
    treats them as non-constant).
    """
    if start >= len(masked) or masked[start] not in ("'", '"'):
        return None
    quote = masked[start]
    pattern = (
        r"'(?:\\.|[^'\\])*'" if quote == "'" else r'"(?:\\.|[^"\\])*"'
    )
    match = re.compile(pattern, re.DOTALL).match(masked, start)
    if not match:
        return None
    body = match.group(0)[1:-1]
    if quote == "'":
        return _decode_single(body), match.end(), False
    return _decode_php_double(body), match.end(), bool(_PHP_DYNAMIC_DQOUTE.search(body))


def extract_php(rel: str, text: str, occurrences: list[Occurrence], stats: dict) -> None:
    masked, spans = _mask_php_comments(text)
    starts = [start for start, _ in spans]
    for match in _PHP_CALL.finditer(masked):
        if _in_spans(spans, starts, match.start()):
            continue
        keyword = match.group("kw")
        if re.search(r"\bfunction\s*$", masked[max(0, match.start() - 200) : match.start()]):
            stats["skipped_defs"] += 1
            continue
        if match.group("arg") is None:
            # Non-literal first argument (variable, constant, expression...).
            stats["skipped_dynamic"] += 1
            snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
            stats["skipped"].append(f"{rel}:{_line_no(masked, match.start())} {snippet}")
            continue
        if match.group("sbody") is not None:
            first = _decode_single(match.group("sbody"))
            first_dynamic = False
        else:
            body = match.group("dbody")
            first_dynamic = bool(_PHP_DYNAMIC_DQOUTE.search(body))
            first = _decode_php_double(body)
        if first_dynamic:
            stats["skipped_dynamic"] += 1
            snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
            stats["skipped"].append(f"{rel}:{_line_no(masked, match.start())} interpolated {snippet}")
            continue
        # Rescan the first literal to find where it ends (for the plural arg).
        arg_start = match.start("arg")
        parsed = _read_php_literal(masked, arg_start)
        end = parsed[1] if parsed else match.end()
        # A concatenated first argument ('a ' . $b) never matches at runtime
        # as a msgid, so skip it instead of recording the literal prefix.
        if re.match(r"\s*[),]", masked[end:]) is None:
            stats["skipped_dynamic"] += 1
            snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
            stats["skipped"].append(f"{rel}:{_line_no(masked, match.start())} concat {snippet}")
            continue
        plural: str | None = None
        if keyword in PHP_KEYWORDS_PLURAL:
            rest = re.match(r"\s*,\s*", masked[end:])
            second = (
                _read_php_literal(masked, end + rest.end())
                if rest
                else None
            )
            # A plural entry is only valid with a complete static second
            # literal; anything else (missing, dynamic, concatenated)
            # would mistranslate, so skip the whole call.
            if (
                second is None
                or second[2]
                or not re.match(r"\s*(?:,|\))", masked[second[1]:])
            ):
                stats["skipped_dynamic"] += 1
                snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
                stats["skipped"].append(f"{rel}:{_line_no(masked, match.start())} {snippet}")
                continue
            plural = second[0]
        occurrences.append(Occurrence(first, plural, f"{rel}:{_line_no(masked, match.start())}"))
        stats["php"] += 1


def iter_source_files(src_dir: str):
    for root, dirs, files in os.walk(src_dir):
        dirs[:] = sorted(d for d in dirs if d not in EXCLUDED_DIR_NAMES)
        for name in sorted(files):
            if name in EXCLUDED_FILES:
                continue
            if os.path.splitext(name)[1] not in SCANNED_EXTENSIONS:
                continue
            full = os.path.join(root, name)
            yield full, os.path.relpath(full, src_dir).replace(os.sep, "/")


def extract_all(src_dir: str) -> tuple[list[Occurrence], dict]:
    occurrences: list[Occurrence] = []
    stats: dict = {"twig": 0, "php": 0, "skipped_dynamic": 0, "skipped_defs": 0, "skipped": [], "files": 0}
    for full, rel in iter_source_files(src_dir):
        with open(full, encoding="utf-8", errors="replace") as handle:
            text = handle.read()
        stats["files"] += 1
        if full.endswith(".twig"):
            extract_twig(rel, text, occurrences, stats)
        else:
            extract_php(rel, text, occurrences, stats)
    return occurrences, stats


# ---------------------------------------------------------------------------
# .pot output
# ---------------------------------------------------------------------------

def _ref_sort_key(ref: str) -> tuple[str, int]:
    path, _, line = ref.rpartition(":")
    try:
        return (path, int(line))
    except ValueError:
        return (ref, 0)


def write_pot(occurrences: list[Occurrence], out_path: str) -> int:
    merged: dict[str, dict] = {}
    for occ in occurrences:
        entry = merged.setdefault(occ.msgid, {"refs": [], "plural": None, "plurals_seen": set()})
        entry["refs"].append(occ.ref)
        if occ.plural is not None:
            entry["plurals_seen"].add(occ.plural)
            if entry["plural"] is None:
                entry["plural"] = occ.plural
            elif entry["plural"] != occ.plural:
                print(
                    f"warning: conflicting plurals for {occ.msgid!r}: "
                    f"{entry['plural']!r} vs {occ.plural!r} (keeping first)",
                    file=sys.stderr,
                )
    date = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M+0000")
    with open(out_path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(HEADER_TEMPLATE.format(date=date))
        for msgid in sorted(merged):
            entry = merged[msgid]
            refs = sorted(set(entry["refs"]), key=_ref_sort_key)
            handle.write(f"\n#: {' '.join(refs)}\n")
            handle.write(f'msgid "{po_escape(msgid)}"\n')
            if entry["plural"] is not None:
                handle.write(f'msgid_plural "{po_escape(entry["plural"])}"\n')
                handle.write('msgstr[0] ""\nmsgstr[1] ""\n')
            else:
                handle.write('msgstr ""\n')
    return len(merged)


# ---------------------------------------------------------------------------
# .pot parsing (for --diff) and comparison
# ---------------------------------------------------------------------------

def _po_unescape(quoted: str) -> str:
    body = quoted[1:-1]
    out: list[str] = []
    i = 0
    simple = {"n": "\n", "r": "\r", "t": "\t", '"': '"', "\\": "\\"}
    while i < len(body):
        char = body[i]
        if char != "\\" or i + 1 >= len(body):
            out.append(char)
            i += 1
            continue
        nxt = body[i + 1]
        out.append(simple.get(nxt, nxt))
        i += 2
    return "".join(out)


def parse_pot_entries(path: str) -> dict[str, str | None]:
    """Parse a .pot into {msgid: msgid_plural}; handles wrapped lines."""
    with open(path, encoding="utf-8", errors="replace") as handle:
        text = handle.read()
    entries: dict[str, str | None] = {}
    for block in re.split(r"\n\s*\n", text):
        msgid_parts: list[str] = []
        plural_parts: list[str] = []
        target: list[str] | None = None
        for line in block.splitlines():
            if line.startswith("msgid_plural"):
                target = plural_parts
                plural_parts.append(_po_unescape(line[len("msgid_plural") :].strip()))
            elif line.startswith("msgid"):
                target = msgid_parts
                msgid_parts.append(_po_unescape(line[len("msgid") :].strip()))
            elif line.startswith('"') and target is not None:
                target.append(_po_unescape(line.strip()))
            else:
                target = None
        msgid = "".join(msgid_parts)
        if msgid:
            plural = "".join(plural_parts) or None
            entries[msgid] = plural
    return entries


def cmd_diff(old_path: str, new_path: str) -> int:
    old_entries = parse_pot_entries(old_path)
    new_entries = parse_pot_entries(new_path)
    old_ids = set(old_entries)
    new_ids = set(new_entries)
    added = sorted(new_ids - old_ids)
    removed = sorted(old_ids - new_ids)

    removed_lookup: dict[str, list[str]] = {}
    for msgid in removed:
        removed_lookup.setdefault(msgid.casefold(), []).append(msgid)
    casing_changes: list[tuple[str, str]] = []
    real_added: list[str] = []
    for msgid in added:
        candidates = removed_lookup.get(msgid.casefold(), [])
        if candidates:
            casing_changes.append((candidates.pop(0), msgid))
        else:
            real_added.append(msgid)
    changed_set = {old for old, _ in casing_changes}
    real_removed = [msgid for msgid in removed if msgid not in changed_set]

    print(f"Added: {len(added)} (new: {len(real_added)}, casing-only: {len(casing_changes)})")
    print(f"Removed: {len(removed)} (gone: {len(real_removed)}, casing-only: {len(casing_changes)})")
    if casing_changes:
        print("\nCasing-only changes (invalidate existing translations!):")
        for old, new in sorted(casing_changes):
            print(f"  - {old!r} -> {new!r}")
    if real_added:
        print("\nNew msgids needing translation:")
        for msgid in real_added:
            print(f"  + {msgid!r}")
    if real_removed:
        print("\nDropped msgids:")
        for msgid in real_removed:
            print(f"  - {msgid!r}")
    plural_changed = sorted(
        msgid
        for msgid in old_ids & new_ids
        if old_entries[msgid] != new_entries[msgid]
    )
    plural_changed_pairs = sorted(
        (old, new)
        for old, new in casing_changes
        if old_entries[old] != new_entries[new]
    )
    if plural_changed or plural_changed_pairs:
        print("\nChanged plural forms:")
        for msgid in plural_changed:
            print(f"  ~ {msgid!r}: {old_entries[msgid]!r} -> {new_entries[msgid]!r}")
        for old, new in plural_changed_pairs:
            print(f"  ~ {old!r} -> {new!r}: {old_entries[old]!r} -> {new_entries[new]!r}")
    return 0


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Extract gettext msgids into messages.pot")
    parser.add_argument("src_dir", nargs="?", help="FOSSBilling src/ directory")
    parser.add_argument("out_pot", nargs="?", help="Output .pot path")
    parser.add_argument("--diff", nargs=2, metavar=("OLD_POT", "NEW_POT"), help="Compare two .pot files")
    args = parser.parse_args(argv)

    if args.diff:
        return cmd_diff(args.diff[0], args.diff[1])
    if not args.src_dir or not args.out_pot:
        parser.error("src_dir and out_pot are required (or use --diff)")
    occurrences, stats = extract_all(args.src_dir)
    count = write_pot(occurrences, args.out_pot)
    print(f"files scanned: {stats['files']}", file=sys.stderr)
    print(f"twig hits: {stats['twig']}, php hits: {stats['php']}", file=sys.stderr)
    print(f"unique msgids written: {count}", file=sys.stderr)
    print(f"skipped non-literals: {stats['skipped_dynamic']}, skipped defs: {stats['skipped_defs']}", file=sys.stderr)
    for item in stats["skipped"]:
        print(f"  skip: {item}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
