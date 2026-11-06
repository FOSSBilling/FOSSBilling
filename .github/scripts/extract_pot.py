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
    extract_pot.py <src_dir> <out.pot> --check
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
import tempfile
from collections import Counter
from typing import NamedTuple

# ---------------------------------------------------------------------------
# Configuration (mirrors the historical Poedit runs + project decisions)
# ---------------------------------------------------------------------------

EXCLUDED_DIR_NAMES = {"vendor", "install", "data", "tests", "locale", "node_modules", ".git"}
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
    # Reflection by name takes identifiers, never messages.
    "ReflectionClass",
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
"Project-Id-Version: {project_id}\\n"
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
    """Decode a PHP single-quoted string body (only \\\\ and \\' are special)."""
    return body.replace("\\\\", "\x00").replace("\\'", "'").replace("\x00", "\\")


def _read_twig_literal(text: str, start: int) -> tuple[str, int, bool] | None:
    """Twig 3 decodes C-style escapes in both quote styles, unlike PHP."""
    match = _QUOTED.match(text, start)
    if match is None:
        return None
    literal = match.group()
    body = literal[1:-1].replace("\r\n", "\n").replace("\r", "\n")
    dynamic = literal[0] == '"' and re.search(r"(?<!\\)(?:\\\\)*#\{", body) is not None
    escapes = {"n": "\n", "r": "\r", "t": "\t", "v": "\v", "f": "\f"}
    decoded = bytearray()
    position = 0
    for escape in re.finditer(r"\\(x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)", body, re.DOTALL):
        decoded.extend(body[position:escape.start()].encode("utf-8"))
        value = escape.group(1)
        if value.startswith("x") and len(value) > 1:
            decoded.append(int(value[1:], 16))
        elif value[0] in "01234567":
            decoded.append(int(value, 8) % 256)
        else:
            decoded.extend(escapes.get(value, value).encode("utf-8"))
        position = escape.end()
    decoded.extend(body[position:].encode("utf-8"))
    return decoded.decode("utf-8"), match.end(), dynamic


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
    # A `?>` ends PHP mode even inside a `//` or `#` comment (verified on
    # PHP 8.5; `/* */` is unaffected), so line comments stop before it.
    r"|(?P<linec>//(?:(?!\?>)[^\n])*|#(?:(?!\?>)[^\n])*)"
    r"|(?P<blockc>/\*.*?\*/)"
    r"|(?P<php_close>\?>)",
    re.DOTALL,
)


# Standalone open-tag matcher for markup regions, where string and comment
# alternatives must not apply (a stray quote there is text, not code).
_PHP_OPEN = re.compile(r"<\?(?:php|=)")


def _mask_php_comments(text: str) -> tuple[str, list[tuple[int, int]], list[tuple[int, int]]]:
    """Blank out PHP comments, honouring strings and heredocs (offsets stable).

    Returns (masked, spans, php_spans): spans holds ordinary string literals
    and heredoc bodies so callers can ignore keyword-like text inside string
    content (which real xgettext never extracts); php_spans holds the
    `<?php` / `<?=` regions so callers can ignore markup outside PHP.

    Raw HTML outside PHP regions is copied through verbatim: its quotes and
    `//` sequences are markup, not PHP strings or comments, and must not
    corrupt span tracking.
    """
    out: list[str] = []
    spans: list[tuple[int, int]] = []
    php_spans: list[tuple[int, int]] = []
    pos = 0
    in_php = False
    region_start = 0
    while True:
        if not in_php:
            # Markup text is copied verbatim: only an open tag can end it,
            # so stray quotes here can never start a bogus string span.
            match = _PHP_OPEN.search(text, pos)
            if match is None:
                out.append(text[pos:])
                pos = len(text)
                break
            out.append(text[pos : match.end()])
            pos = match.end()
            in_php = True
            region_start = match.start()
            continue
        match = _PHP_TOKEN.search(text, pos)
        if match is None:
            break
        kind = match.lastgroup
        if kind in ("php_open", "php_close"):
            out.append(text[pos : match.end()])
            pos = match.end()
            if kind == "php_close":
                php_spans.append((region_start, match.end()))
                in_php = False
            continue
        if kind == "heredoc":
            tag = match.group("htag")
            # Flexible heredoc (PHP 7.3+): the indented marker ends at the
            # identifier boundary and the expression may continue on the
            # same line (`)`, `,`, `.`, ...), e.g. `foo(<<<HTML ... HTML)`.
            end = re.search(
                rf"^[ \t]*{re.escape(tag)}(?![A-Za-z0-9_])",
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
    if in_php:
        php_spans.append((region_start, len(text)))
    return "".join(out), spans, php_spans


def _in_spans(spans: list[tuple[int, int]], starts: list[int], offset: int) -> bool:
    """Whether offset falls inside a string/heredoc span (spans are sorted)."""
    idx = bisect.bisect_right(starts, offset) - 1
    return idx >= 0 and spans[idx][0] <= offset < spans[idx][1]


def _span_end(spans: list[tuple[int, int]], starts: list[int], offset: int) -> int | None:
    """End of the span containing offset, or None when outside spans."""
    idx = bisect.bisect_right(starts, offset) - 1
    if idx >= 0 and spans[idx][0] <= offset < spans[idx][1]:
        return spans[idx][1]
    return None


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
    rf"|(?<![\w$>])(?P<chain>parent|self|static)::\s*__construct)\s*\(",
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
    source: str = "core"


def _line_no(text: str, offset: int) -> int:
    return text.count("\n", 0, offset) + 1


_SOURCE_ID = re.compile(r"[a-z][a-z0-9_-]*\Z")
_QUOTED = re.compile(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"", re.DOTALL)
_TWIG_OPEN = re.compile(r"\{\{|\{%")


def _twig_string_spans(text: str) -> list[tuple[int, int]]:
    """HTML attribute quotes must not hide the Twig literals inside them."""
    spans: list[tuple[int, int]] = []
    position = 0
    while opening := _TWIG_OPEN.search(text, position):
        start = opening.end()
        closing = "}}" if opening.group() == "{{" else "%}"
        token = re.compile(_QUOTED.pattern + "|" + re.escape(closing), re.DOTALL)
        position = len(text)
        for match in token.finditer(text, start):
            if match.group() == closing:
                position = match.end()
                break
            spans.append((match.start(), match.end()))
    return spans


def _call_arguments(
    text: str, opening: int, spans: list[tuple[int, int]], starts: list[int]
) -> list[tuple[int, int]] | None:
    """Split a call at top-level commas, leaving nested calls and literals inert."""
    stack = ["("]
    arguments: list[tuple[int, int]] = []
    start = i = opening + 1
    closing = {")": "(", "]": "[", "}": "{"}
    while i < len(text):
        end = _span_end(spans, starts, i)
        if end is not None:
            i = end
            continue
        char = text[i]
        if char in "([{":
            stack.append(char)
        elif char in ")]}":
            if not stack or stack.pop() != closing[char]:
                return None
            if not stack:
                if text[start:i].strip():
                    arguments.append((start, i))
                return arguments
        elif char == "," and len(stack) == 1:
            arguments.append((start, i))
            start = i + 1
        i += 1
    return None


def _bind_arguments(
    text: str, arguments: list[tuple[int, int]], names: tuple[str, ...], twig: bool = False
) -> dict[str, tuple[int, int]]:
    bound: dict[str, tuple[int, int]] = {}
    named = False
    separator = r"(?::(?!:)|=(?!=|>))" if twig else r":(?!:)"
    for index, (start, end) in enumerate(arguments):
        match = re.match(rf"\s*([A-Za-z_][A-Za-z0-9_]*)\s*{separator}\s*", text[start:end])
        if match:
            named = True
            name = match.group(1)
            start += match.end()
        else:
            if named:
                raise ValueError("positional argument after named argument")
            name = names[index] if index < len(names) else str(index)
        if name in bound:
            raise ValueError(f"duplicate argument {name!r}")
        bound[name] = (start, end)
    return bound


def _translation_source(
    text: str, argument: tuple[int, int] | None, default: str, ref: str, stats: dict, twig: bool = False
) -> str | None:
    if argument is None:
        return default
    start, end = argument
    start += len(text[start:end]) - len(text[start:end].lstrip())
    value = text[start:end].strip()
    if value.lower() == "null" or (twig and value == "none"):
        # Explicit null suppresses Twig's inferred source at runtime.
        return "core"
    parsed = _read_twig_literal(text, start) if twig else _read_php_literal(text, start)
    if parsed is not None and not text[parsed[1]:end].strip():
        source = parsed[0]
        dynamic = parsed[2]
        if not dynamic and _SOURCE_ID.fullmatch(source):
            return source
    stats["source_errors"].append(f"{ref}: from must be a literal lowercase source ID or null")
    return None


def extract_twig(rel: str, text: str, occurrences: list[Occurrence], stats: dict, module_id: str | None = None) -> None:
    masked = _mask_twig_comments(text)
    spans = _twig_string_spans(masked)
    starts = [start for start, _ in spans]
    for match in _TWIG_TRANS.finditer(masked):
        span_end = _span_end(spans, starts, match.start())
        if span_end is not None and spans[bisect.bisect_right(starts, match.start()) - 1][0] != match.start():
            continue
        ref = f"{rel}:{_line_no(masked, match.start())}"
        parsed = _read_twig_literal(masked, match.start())
        if parsed[2]:
            stats["skipped_dynamic"] += 1
            stats["skipped"].append(f"{ref} interpolated Twig string")
            continue
        msgid = parsed[0]
        arguments: dict[str, tuple[int, int]] = {}
        opening = match.end()
        while opening < len(masked) and masked[opening].isspace():
            opening += 1
        if masked[opening:opening + 1] == "(":
            call = _call_arguments(masked, opening, spans, starts)
            try:
                if call is None:
                    raise ValueError("unbalanced trans arguments")
                arguments = _bind_arguments(masked, call, ("values", "from"), twig=True)
                if any(masked[start:end].lstrip().startswith("...") for start, end in call):
                    raise ValueError("argument unpacking prevents resolving the translation source")
            except ValueError as error:
                stats["source_errors"].append(f"{ref}: {error}")
                continue
        source = _translation_source(masked, arguments.get("from"), module_id or "core", ref, stats, twig=True)
        if source is None:
            continue
        occurrences.append(Occurrence(msgid, None, ref, source))
        stats["twig"] += 1


def _balanced_close(
    masked: str, start: int, spans: list[tuple[int, int]], starts: list[int]
) -> int | None:
    """End offset just past the bracket matching the opener at offset."""
    depth = 0
    i = start
    while i < len(masked):
        end = _span_end(spans, starts, i)
        if end is not None:
            i = end  # String/heredoc content is inert text; skip over it.
            continue
        char = masked[i]
        if char in ("'", '"'):
            return None  # Unbalanced quote outside any span: invalid PHP.
        if char in "([{":
            depth += 1
        elif char in ")]}":
            depth -= 1
            if depth == 0:
                return i + 1
            if depth < 0:
                return None
        i += 1
    return None


def _read_ternary_literals(
    masked: str, start: int, spans: list[tuple[int, int]], starts: list[int]
) -> tuple[list[str], int] | None:
    """Parse `cond ? 'a' : 'b'` at offset (strict: both branches literals).

    The condition may hold balanced brackets, strings and heredocs; anything
    else bails out with None. Returns ([branch_a, branch_b], end_offset).
    """
    # A fully parenthesized argument (`($c ? 'x' : 'y')`) nests the ternary
    # one level deep; unwrap such layers so the scan below sees it top-level.
    i = start
    while True:
        while i < len(masked) and masked[i] in " \t\n\r":
            i += 1
        if i >= len(masked) or masked[i] != "(":
            break
        end = _balanced_close(masked, i, spans, starts)
        if end is None or re.match(r"\s*[),]", masked[end:]) is None:
            break
        i += 1
    depth = 0
    cond_end: int | None = None
    while i < len(masked):
        end = _span_end(spans, starts, i)
        if end is not None:
            i = end  # String/heredoc content is inert text; skip over it.
            continue
        char = masked[i]
        if char in ("'", '"'):
            return None  # Unbalanced quote outside any span: invalid PHP.
        if char in "([{":
            depth += 1
        elif char in ")]}":
            depth -= 1
            if depth < 0:
                return None
        elif char == "," and depth == 0:
            return None  # Next argument reached before any ternary.
        elif char == "=" and depth == 0 and masked[i + 1 : i + 2] == ">":
            return None  # Arrow function: its body is code, not our message.
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


def _php_messages(
    text: str, argument: tuple[int, int], spans: list[tuple[int, int]], starts: list[int], allow_ternary: bool
) -> list[str] | None:
    start, end = argument
    start += len(text[start:end]) - len(text[start:end].lstrip())
    literal = _read_php_literal(text, start)
    if literal is not None and not literal[2] and not text[literal[1]:end].strip():
        return [literal[0]]
    if allow_ternary:
        ternary = _read_ternary_literals(text, start, spans, starts)
        if ternary is not None and re.fullmatch(r"\s*(?:\)\s*)*", text[ternary[1]:end]):
            return ternary[0]
    return None


def extract_php(rel: str, text: str, occurrences: list[Occurrence], stats: dict) -> None:
    masked, spans, php_spans = _mask_php_comments(text)
    starts = [start for start, _ in spans]
    php_starts = [start for start, _ in php_spans]
    for match in _PHP_CALL.finditer(masked):
        if _in_spans(spans, starts, match.start()):
            continue
        if not _in_spans(php_spans, php_starts, match.start()):
            # Markup outside PHP tags (inline handlers, text nodes): not code.
            stats["skipped_markup"] += 1
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
                # `class` can never name a real class, so any casing of it is
                # the anonymous-class keyword (PHP names are case-insensitive).
                low = short.lower()
                if low in _NON_MESSAGE_LOWER or low == "class":
                    stats["skipped_ctor"] += 1
                    continue
        if re.search(r"\bfunction\s*$", masked[max(0, match.start() - 200) : match.start()]):
            stats["skipped_defs"] += 1
            continue
        if keyword == "__trans":
            names = ("msgid", "values", "from")
        elif keyword == "__pluralTrans":
            names = ("msgid", "msgidPlural", "number", "values", "from")
        elif short is not None and short.lower() in ("payment_exception", "registrar_exception", "server_exception"):
            names = ("message", "variables", "code", "from")
        elif short is not None and short.lower() in ("baseexception", "informationexception", "exception", "__construct"):
            names = ("message", "variables", "code", "protected", "from")
        else:
            # Unknown constructors retain the historical first-message policy,
            # but their later positional parameters are not our exception API.
            names = ("message",)
        ref = f"{rel}:{_line_no(masked, match.start())}"
        call = _call_arguments(masked, match.end() - 1, spans, starts)
        if call is None:
            stats["source_errors"].append(f"{ref}: unbalanced translation arguments")
            continue
        try:
            arguments = _bind_arguments(masked, call, names)
        except ValueError as error:
            stats["source_errors"].append(f"{ref}: {error}")
            continue
        message = arguments.get(names[0])
        variants = (
            _php_messages(masked, message, spans, starts, keyword not in PHP_KEYWORDS_PLURAL)
            if message else None
        )
        if variants is None:
            stats["skipped_dynamic"] += 1
            if keyword is not None or (
                short is not None and ("exception" in short.lower() or short in ("IncompleteRecord", "__construct"))
            ):
                snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
                stats["skipped"].append(f"{ref} {snippet}")
            continue
        if "from" in names and any(masked[start:end].lstrip().startswith("...") for start, end in call):
            stats["source_errors"].append(f"{ref}: argument unpacking prevents resolving the translation source")
            continue
        if short is not None:
            stats["ctor_classes"][short] += 1
        plural: str | None = None
        if keyword in PHP_KEYWORDS_PLURAL:
            argument = arguments.get("msgidPlural")
            second = _php_messages(masked, argument, spans, starts, False) if argument else None
            if second is None:
                stats["skipped_dynamic"] += 1
                snippet = masked[match.start() : match.start() + 60].replace("\n", " ")
                stats["skipped"].append(f"{ref} {snippet}")
                continue
            plural = second[0]
        source = _translation_source(masked, arguments.get("from"), "core", ref, stats)
        if source is None:
            continue
        for variant in variants:
            occurrences.append(Occurrence(variant, plural, ref, source))
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


def extract_all(src_dir: str, module_id: str | None = None) -> tuple[list[Occurrence], dict]:
    if not os.path.isdir(src_dir):
        raise ValueError(f"source directory does not exist: {src_dir}")
    if module_id is not None and (not _SOURCE_ID.fullmatch(module_id) or module_id == "core"):
        raise ValueError(f"invalid module ID: {module_id!r}")
    occurrences: list[Occurrence] = []
    stats: dict = {"twig": 0, "php": 0, "skipped_dynamic": 0, "skipped_defs": 0, "skipped_ctor": 0, "skipped_markup": 0, "ctor_classes": Counter(), "skipped": [], "files": 0, "source_errors": []}
    for full, rel in iter_source_files(src_dir):
        with open(full, encoding="utf-8", errors="replace") as handle:
            text = handle.read()
        stats["files"] += 1
        if full.endswith(".twig"):
            inferred = module_id
            parts = rel.split("/")
            if inferred is None and len(parts) >= 3 and parts[0] == "modules":
                candidate = parts[1].lower()
                if parts[1] == candidate[0].upper() + candidate[1:] and _SOURCE_ID.fullmatch(candidate):
                    inferred = candidate
            extract_twig(rel, text, occurrences, stats, inferred)
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


def write_pot(occurrences: list[Occurrence], out_path: str, project_id: str = "4", strict: bool = False) -> int:
    merged: dict[str, dict] = {}
    for occ in occurrences:
        entry = merged.setdefault(occ.msgid, {"refs": [], "plural": None, "plurals_seen": set()})
        entry["refs"].append(occ.ref)
        if occ.plural is not None:
            entry["plurals_seen"].add(occ.plural)
            if entry["plural"] is None:
                entry["plural"] = occ.plural
            elif entry["plural"] != occ.plural:
                if strict:
                    raise ValueError(f"conflicting plurals for {occ.msgid!r}: {entry['plural']!r} vs {occ.plural!r}")
                print(
                    f"warning: conflicting plurals for {occ.msgid!r}: "
                    f"{entry['plural']!r} vs {occ.plural!r} (keeping first)",
                    file=sys.stderr,
                )
    date = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M+0000")
    with open(out_path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(HEADER_TEMPLATE.format(date=date, project_id=po_escape(project_id)))
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


def normalized_pot(path: str) -> str:
    """Compare content and references without timestamp-only template churn."""
    with open(path, encoding="utf-8") as handle:
        return "\n".join(line for line in handle.read().splitlines() if not line.startswith('"POT-Creation-Date:'))


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
    parser.add_argument("--check", action="store_true", help="Check an existing template without modifying it")
    args = parser.parse_args(argv)

    if args.diff:
        return cmd_diff(args.diff[0], args.diff[1])
    if not args.src_dir or not args.out_pot:
        parser.error("src_dir and out_pot are required (or use --diff)")
    try:
        occurrences, stats = extract_all(args.src_dir)
    except ValueError as error:
        parser.error(str(error))
    if stats["source_errors"]:
        for error in stats["source_errors"]:
            print(f"error: {error}", file=sys.stderr)
        return 1
    if args.check:
        with tempfile.TemporaryDirectory() as temporary:
            generated = os.path.join(temporary, "messages.pot")
            count = write_pot(occurrences, generated)
            if not os.path.isfile(args.out_pot):
                print(f"missing template: {args.out_pot}", file=sys.stderr)
                return 1
            if normalized_pot(generated) != normalized_pot(args.out_pot):
                cmd_diff(args.out_pot, generated)
                print(f"template needs regeneration: {args.out_pot}", file=sys.stderr)
                return 1
    else:
        count = write_pot(occurrences, args.out_pot)
    print(f"files scanned: {stats['files']}", file=sys.stderr)
    print(f"twig hits: {stats['twig']}, php hits: {stats['php']}", file=sys.stderr)
    print(f"unique msgids written: {count}", file=sys.stderr)
    print(f"skipped non-literals: {stats['skipped_dynamic']}, skipped defs: {stats['skipped_defs']}", file=sys.stderr)
    print(f"skipped non-message ctors: {stats['skipped_ctor']}, skipped markup: {stats['skipped_markup']}", file=sys.stderr)
    if stats["ctor_classes"]:
        top = ", ".join(f"{name}x{hits}" for name, hits in stats["ctor_classes"].most_common(20))
        print(f"constructed classes: {top}", file=sys.stderr)
    for item in stats["skipped"]:
        print(f"  skip: {item}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
