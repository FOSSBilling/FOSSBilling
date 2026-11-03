#!/usr/bin/env python3
"""Fixture tests for extract_pot.py (stdlib only, no pytest needed).

Run:  python3 .github/scripts/tests/test_extract_pot.py
"""

from __future__ import annotations

import os
import sys
import tempfile

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from extract_pot import extract_all  # noqa: E402


def msgids_for(php: str) -> set[str]:
    with tempfile.TemporaryDirectory() as tmp:
        path = os.path.join(tmp, "Case.php")
        with open(path, "w", encoding="utf-8") as handle:
            handle.write("<?php\n" + php + "\n")
        occurrences, _ = extract_all(tmp)
    return {occ.msgid for occ in occurrences}


def check(name: str, php: str, expected: set[str]) -> None:
    got = msgids_for(php)
    assert got == expected, f"{name}:\n  expected {sorted(expected)}\n  got      {sorted(got)}"
    print(f"ok: {name}")


def main() -> int:
    # Fully-qualified, short-name and assign-then-throw constructions.
    check(
        "throw fqn BaseException",
        "throw new \\FOSSBilling\\Core\\Exception\\BaseException('Cart not found');",
        {"Cart not found"},
    )
    check(
        "throw short BaseException",
        "throw new BaseException('Cache configuration is invalid.');",
        {"Cache configuration is invalid."},
    )
    check(
        "assigned then rendered (index.php pattern)",
        "$e = new FOSSBilling\\Core\\Exception\\BaseException('Page :url not found', [':url' => $url], 404);\n"
        "$response = $app->show404($e);",
        {"Page :url not found"},
    )
    check(
        "returned factory (ServiceTransaction pattern)",
        "return new \\Payment_Exception('Custom payments must be approved.', [], 7002);",
        {"Custom payments must be approved."},
    )
    check(
        "throw-expression",
        "$country ??= throw new IncompleteRecord('Unknown country ISO code');",
        {"Unknown country ISO code"},
    )
    # SPL / misc exception classes need no allowlisting.
    check(
        "RuntimeException",
        "throw new \\RuntimeException('Unable to encrypt data.');",
        {"Unable to encrypt data."},
    )
    check(
        "LogicException",
        "throw new \\LogicException('License is not active', 1006);",
        {"License is not active"},
    )
    check(
        "InvalidArgumentException",
        "throw new InvalidArgumentException('Invalid email address');",
        {"Invalid email address"},
    )
    check(
        "legacy underscore exceptions",
        "throw new Server_Exception('Invalid server manager.');",
        {"Invalid server manager."},
    )
    check(
        "leading-backslash trans call",
        "'display_name' => \\__trans('Delete email history'),",
        {"Delete email history"},
    )
    check(
        "sentinel-taking exception excluded",
        "throw new AuthenticationRequiredException('client');",
        set(),
    )
    check(
        "parent-constructed exception message",
        "class LoginRequired extends \\RuntimeException {\n"
        "    public function __construct() {\n"
        "        parent::__construct('Client is not logged in');\n"
        "    }\n"
        "}",
        {"Client is not logged in"},
    )
    check(
        "ternary literal branches",
        "throw new \\RuntimeException($area === 'admin' ? 'Admin area' : 'Client area');",
        {"Admin area", "Client area"},
    )
    check(
        "ternary in chained constructor",
        "parent::__construct($area === 'admin' ? 'Admin is not logged in' : 'Client is not logged in');",
        {"Admin is not logged in", "Client is not logged in"},
    )
    check(
        "coalescing fallback is not a ternary",
        "throw new \\RuntimeException($message ?? 'Unknown error');",
        set(),
    )
    check(
        "ternary with dynamic branch skipped",
        "throw new \\RuntimeException($ok ? getMessage() : 'Fallback');",
        set(),
    )
    # Non-message constructors stay out.
    check("DateTime", "$d = new \\DateTime('now');", set())
    check("DateTimeImmutable", "$d = new \\DateTimeImmutable('2026-01-01');", set())
    check(
        "Response",
        "$r = new Response('<html>hi</html>');",
        set(),
    )
    check("DOMDocument", "$d = new \\DOMDocument('1.0', 'UTF-8');", set())
    check("anonymous class", "$o = new class('not a message') extends Foo {};", set())
    # Existing guards are preserved.
    check(
        "concatenated first arg skipped",
        "throw new \\RuntimeException('No items for ID: ' . $id);",
        set(),
    )
    check(
        "interpolated double-quoted skipped",
        'throw new \\RuntimeException("Hello $name");',
        set(),
    )
    check(
        "dynamic first arg skipped",
        "throw new \\RuntimeException($message);",
        set(),
    )
    check(
        "trans helpers intact",
        "__trans('Visible string');\n__pluralTrans('One apple', 'Many apples', $n);",
        {"Visible string", "One apple"},
    )
    check(
        "catch/instanceof/class-const not matched",
        "} catch (\\Exception $e) {\n"
        "if ($e instanceof \\RuntimeException) {\n"
        "$c = \\RuntimeException::class;\n}",
        set(),
    )
    check(
        "keyword inside string not matched",
        "$s = 'throw new \\RuntimeException(\\'fake\\')';",
        set(),
    )
    check(
        "new-substring identifier not matched",
        "// renew the thing\n$renew = 1;",
        set(),
    )
    print("all fixture tests passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
