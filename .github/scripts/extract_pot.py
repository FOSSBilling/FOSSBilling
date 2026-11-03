#!/usr/bin/env python3
"""Extract gettext msgids from FOSSBilling sources into a messages.pot file.

Open-source replacement for the manual Poedit Pro step. Scans Twig
``|trans`` literals and PHP ``__trans`` / ``__pluralTrans`` calls plus
string literals passed to ``new`` constructions (exceptions and other
message carriers, minus a small denylist of non-message constructors)
under ``src/`` (minus vendor, install, data, load.php, tests and
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
from collections import Counter
from typing import NamedTuple

# ---------------------------------------------------------------------------
# Configuration (mirrors the historical Poedit runs + project decisions)
# ---------------------------------------------------------------------------

EXCLUDED_DIR_NAMES = {"vendor", "install", "data", "tests"}
EXCLUDED_FILES = {"load.php"}
EXCLUDED_EXTENSIONS = {".js", ".html", ".css", ".scss", ".md"}
SCANNED_EXTENSIONS = {".php", ".twig"}

PHP_TRANS_SINGLE = {"__trans"}
PHP_KEYWORDS_PLURAL = {"__pluralTrans"}

# Constructors whose first string argument is never a user-facing message:
# date/time plumbing and HTTP plumbing, plus exception types that take a
# sentinel instead of a message (AuthenticationRequiredException takes an
# area like 'admin' / 'client'; its real messages live in its constructor).
NON_MESSAGE_CONSTRUCTORS = {
    "DateTime",
    "DateTimeImmutable",
    "DateTimeZone",
    "DateInterval",
    "DatePeriod",
    "Response",
    "RedirectResponse",
    "DOMDocument",
    "PDO",
    "AuthenticationRequiredException",
}
# PHP class names are case-insensitive (`new \datetime('now')` is legal).
_NON_MESSAGE_LOWER = frozenset(name.lower() for name in NON_MESSAGE_CONSTRUCTORS)

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
    r"(?P<php_open><\?(?:php|=))"
    r"|(?P<heredoc><<<\s*['\"]?(?P<htag>[A-Za-z_][A-Za-z0-9_]*)['\"]?[^\n]*\n)"
    r"|(?P<dstr>\"(?:\\.|[^\"\\])*\")"
    r"|(?P<sstr>'(?:\\.|[^'\\])*')"
    r"|(?P<linec>//[^\n]*|#[^\n]*)"
    r"|(?P<blockc>/\*.*?\*/)"
    r"|(?P<php_close>\?>)",
    re.DOTALL,
)


def _mask_php_comments(text: str) -> tuple[str, list[tuple[int, int]]]:
    """Blank out PHP comments, honouring strings and heredocs (offsets stable).

    Also returns the spans of ordinary string literals and heredoc bodies so
    callers can ignore keyword-like text inside string content (which real
    xgettext never extracts).

    Raw HTML outside `<?php` / `<?=` regions is copied through verbatim:
    its quotes and `//` sequences are markup, not PHP strings or comments,
    and must not corrupt span tracking.
    """
    out: list[str] = []
    spans: list[tuple[int, int]] = []
    pos = 0
    in_php = False
    for match in _PHP_TOKEN.finditer(text):
        kind = match.lastgroup
        if match.start() < pos:
            # Inside an already-consumed heredoc body; it was copied verbatim.
            continue
        if kind in ("php_open", "php_close"):
            out.append(text[pos : match.end()])
            pos = match.end()
            in_php = kind == "php_open"
            continue
        if not in_php:
            continue  # Markup text; copied verbatim by the trailing append.
        if kind == "heredoc":
            tag = match.group("htag")
            # Flexible heredoc (PHP 7.3+): indented marker optionally
            # followed by `;` or `,` when used inside an expression.
            end = re.search(
                rf"^[ \t]*{re.escape(tag)}[ \t]*[;,]?[ \t]*$",
                text[match.end():],
                re.MULTILINE,
            )
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


_SCRIPT_OPEN = re.compile(r"<(?P<tag>script|style)\b[^>]*>", re.IGNORECASE)
_SCRIPT_CLOSE = re.compile(r"</(?P<tag>script|style)\s*>", re.IGNORECASE)
# Inline `<?php` / `<?=` regions; quoted strings are skipped while seeking
# the closer so a `?>` inside a string doesn't end the region early.
_PHP_REGION = re.compile(
    r"<\?(?:php|=)(?:[^'\"]|'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\")*?\?>",
    re.IGNORECASE | re.DOTALL,
)


def _mask_embedded_blocks(masked: str, spans: list[tuple[int, int]], starts: list[int]) -> str:
    """Blank out `<script>` / `<style>` regions (JS/CSS, never PHP messages).

    Each block is masked from its own opener to its own closer. Openers
    inside string/heredoc spans are skipped without consuming anything, so
    markup inside PHP strings is left alone; `<?php` / `<?=` regions inside
    a block are carved back out so inline template code still extracts.
    Newlines are preserved, keeping offsets stable for callers.
    """
    php_spans = [match.span() for match in _PHP_REGION.finditer(masked)]
    php_starts = [start for start, _ in php_spans]
    chars = list(masked)
    for opener in _SCRIPT_OPEN.finditer(masked):
        if _in_spans(spans, starts, opener.start()):
            continue
        tag = opener.group("tag").lower()
        for closer in _SCRIPT_CLOSE.finditer(masked, opener.end()):
            if closer.group("tag").lower() != tag:
                continue
            if _in_spans(spans, starts, closer.start()) and _in_spans(
                php_spans, php_starts, closer.start()
            ):
                continue  # Closer inside a PHP string, e.g. $m = '</script>'.
            for i in range(opener.start(), closer.end()):
                if chars[i] != "\n" and not _in_spans(php_spans, php_starts, i):
                    chars[i] = " "
            break
    return "".join(chars)


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

_TRANS_ALTS = "|".join(
    sorted(PHP_TRANS_SINGLE | PHP_KEYWORDS_PLURAL, key=len, reverse=True)
)
_CLASS_NAME = r"(?:\\)?(?:[A-Za-z_][\w]*\\)*[A-Za-z_][\w]*"
# Matches `__trans(...)` / `__pluralTrans(...)` calls (global or leading-
# backslash form, e.g. `\__trans(...)`), any `new [Namespace\]Class(`
# construction (thrown, returned, assigned, or used in a throw-expression
# — all are message carriers unless denylisted below), and constructor-
# chained exception messages (`parent::__construct('...')`, as used by
# exception subclasses that build their own message). The lookbehinds
# reject method/static calls (->, ::), variables and longer identifiers
# (e.g. the `new` in `$renew`).
_PHP_CALL = re.compile(
    rf"(?:(?<![\w$])new\s+(?P<cls>{_CLASS_NAME})"
    rf"|(?<![\w$>:\\])(?:\\)?(?:[A-Za-z_][\w]*\\)*(?P<kw>{_TRANS_ALTS})"
    rf"|(?<![\w$>])(?P<chain>parent|self|static)::\s*__construct)\s*\(\s*"
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


def _read_ternary_literals(
    masked: str, start: int, spans: list[tuple[int, int]], starts: list[int]
) -> tuple[list[str], int] | None:
    """Parse `cond ? 'a' : 'b'` at offset (strict: both branches literals).

    The condition may hold balanced brackets and quoted strings; anything
    else bails out with None. Returns ([branch_a, branch_b], end_offset).
    """
    depth = 0
    i = start
    cond_end: int | None = None
    while i < len(masked):
        char = masked[i]
        if char in ("'", '"'):
            parsed = _read_php_literal(masked, i)
            if parsed is None:
                return None
            # The literal must not cross its containing string/heredoc
            # span (a stray quote inside heredoc text parses garbage).
            idx = bisect.bisect_right(starts, i) - 1
            if (
                idx >= 0
                and spans[idx][0] <= i < spans[idx][1]
                and parsed[1] > spans[idx][1]
            ):
                return None
            i = parsed[1]
            continue
        if _in_spans(spans, starts, i):
            return None
        if char in "([{":
            depth += 1
        elif char in ")]}":
            depth -= 1
            if depth < 0:
                return None
        elif char == "," and depth == 0:
            return None  # Next argument reached before any ternary.
        elif char == "?" and depth == 0:
            nxt = masked[i + 1 : i + 3]
            if nxt.startswith("?") or nxt.startswith("->"):
                i += 2  # `??` / nullsafe `?->`, not the ternary.
                continue
            cond_end = i
            break
        i += 1
    if cond_end is None:
        return None
    # Skip whitespace after `?`, read branch A.
    j = cond_end + 1
    while j < len(masked) and masked[j] in " \t\n\r":
        j += 1
    parsed_a = _read_php_literal(masked, j)
    if parsed_a is None or parsed_a[2]:
        return None
    value_a, j = parsed_a[0], parsed_a[1]
    while j < len(masked) and masked[j] in " \t\n\r":
        j += 1
    if j >= len(masked) or masked[j] != ":" or masked[j + 1 : j + 2] == ":":
        return None
    j += 1
    while j < len(masked) and masked[j] in " \t\n\r":
        j += 1
    parsed_b = _read_php_literal(masked, j)
    if parsed_b is None or parsed_b[2]:
        return None
    return [value_a, parsed_b[0]], parsed_b[1]


def _record_ternary(
    occurrences: list[Occurrence],
    stats: dict,
    ref: str,
    short: str | None,
    variants: list[str],
) -> None:
    for variant in variants:
        occurrences.append(Occurrence(variant, None, ref))
    stats["php"] += 1
    if short is not None:
        stats["ctor_classes"][short] += 1


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
    masked = _mask_embedded_blocks(masked, spans, starts)
    for match in _PHP_CALL.finditer(masked):
        if _in_spans(spans, starts, match.start()):
            continue
        keyword = match.group("kw")
        short: str | None = None
        if keyword is None:
            if match.group("chain") is not None:
                # Constructor-chained exception message.
                short = "__construct"
            else:
                # Generic constructor branch: a message carrier (normally an
                # exception) unless denylisted. The short name is what matters;
                # a leading backslash leaves an empty first segment.
                short = match.group("cls").split("\\")[-1]
                if short.lower() in _NON_MESSAGE_LOWER or short == "class":
                    stats["skipped_ctor"] += 1
                    continue
        if re.search(r"\bfunction\s*$", masked[max(0, match.start() - 200) : match.start()]):
            stats["skipped_defs"] += 1
            continue
        if match.group("arg") is None:
            # A ternary with two literal branches (`$a ? 'x' : 'y'`) carries
            # two message variants; anything else non-literal is skipped.
            # (Never applied to __pluralTrans: a plural entry needs two
            # dedicated literals, not branch variants.)
            tern = (
                None
                if keyword in PHP_KEYWORDS_PLURAL
                else _read_ternary_literals(masked, match.end(), spans, starts)
            )
            if tern is not None and re.match(r"\s*[),]", masked[tern[1] :]) is not None:
                _record_ternary(
                    occurrences,
                    stats,
                    f"{rel}:{_line_no(masked, match.start())}",
                    short,
                    tern[0],
                )
                continue
            # Non-literal first argument (variable, constant, expression...).
            # Only logged for plausible message carriers; `new Finder($x)`
            # style noise would otherwise drown the audit trail.
            stats["skipped_dynamic"] += 1
            if keyword is not None or (
                short is not None
                and ("exception" in short.lower() or short in ("IncompleteRecord", "__construct"))
            ):
                snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
                stats["skipped"].append(f"{rel}:{_line_no(masked, match.start())} {snippet}")
            continue
        # Census of literal-carrying constructions, for drift review.
        if short is not None:
            stats["ctor_classes"][short] += 1
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
        # as a msgid, so skip it instead of recording the literal prefix —
        # unless it opens a literal ternary ('a' === $b ? 'x' : 'y'), whose
        # branches are message variants.
        if re.match(r"\s*[),]", masked[end:]) is None:
            tern = (
                None
                if keyword in PHP_KEYWORDS_PLURAL
                else _read_ternary_literals(masked, arg_start, spans, starts)
            )
            if tern is not None and re.match(r"\s*[),]", masked[tern[1] :]) is not None:
                _record_ternary(
                    occurrences,
                    stats,
                    f"{rel}:{_line_no(masked, match.start())}",
                    short,
                    tern[0],
                )
                continue
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
    stats: dict = {"twig": 0, "php": 0, "skipped_dynamic": 0, "skipped_defs": 0, "skipped_ctor": 0, "ctor_classes": Counter(), "skipped": [], "files": 0}
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
    print(f"skipped non-message ctors: {stats['skipped_ctor']}", file=sys.stderr)
    if stats["ctor_classes"]:
        top = ", ".join(f"{name}x{hits}" for name, hits in stats["ctor_classes"].most_common(20))
        print(f"constructed classes: {top}", file=sys.stderr)
    for item in stats["skipped"]:
        print(f"  skip: {item}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
