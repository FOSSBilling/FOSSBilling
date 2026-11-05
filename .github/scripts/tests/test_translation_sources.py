#!/usr/bin/env python3
"""Source-routing and command-line regression tests (Python stdlib only)."""

from __future__ import annotations

from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))

from extract_pot import Occurrence, extract_all, parse_pot_entries, write_pot  # noqa: E402


class TranslationSourcesTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.src = self.root / "src"
        self.src.mkdir()

    def write(self, name: str, content: str) -> None:
        path = self.src / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")

    def entries(self, module_id: str | None = None) -> set[tuple[str, str | None, str]]:
        occurrences, stats = extract_all(str(self.src), module_id)
        self.assertEqual(stats["source_errors"], [])
        return {(occ.msgid, occ.plural, occ.source) for occ in occurrences}

    def cli(self, *arguments: str) -> subprocess.CompletedProcess[str]:
        return subprocess.run([sys.executable, str(SCRIPTS / "extract_pot.py"), *map(str, arguments)], capture_output=True, text=True)

    def test_php_sources_named_positional_and_reordered_arguments(self) -> None:
        self.write("modules/Example/Service.php", """<?php
            __trans('Shared');
            __trans('Named', from: 'example');
            __trans('Positional', ['from' => 'ignored', 'nested' => foo(1, 2)], 'other');
            __trans(from: 'example', values: [':n' => foo('from: other', [1, 2])], msgid: 'Reordered');
            __trans('Null', from: null);
            __trans('Explicit core', from: 'core');
            __pluralTrans(':n item', ':n items', $n, [':n' => $n], 'example');
            __pluralTrans(number: $n, from: 'other', msgidPlural: 'Many', msgid: 'One');
            __trans($dynamic, from: $source);
            __trans($dynamic, ...$arguments);
            new Unrelated(...$arguments);
            new Unrelated('Constructor message', ...$arguments);
        """)
        self.assertEqual(self.entries(), {
            ('Shared', None, 'core'), ('Named', None, 'example'), ('Positional', None, 'other'),
            ('Reordered', None, 'example'), ('Null', None, 'core'), ('Explicit core', None, 'core'),
            (':n item', ':n items', 'example'), ('One', 'Many', 'other'),
            ('Constructor message', None, 'core'),
        })

    def test_exceptions_and_chained_constructors(self) -> None:
        self.write("Case.php", """<?php
            new InformationException('Named', from: 'example');
            new BaseException('Positional', [], 42, true, 'example');
            new Payment_Exception('Payment', null, 42, 'example');
            new Registrar_Exception('Registrar', 42, from: 'example');
            new server_exception('Server', [], 42, 'example');
            new CustomException(from: 'other', message: 'Custom');
            new Unrelated('Unrelated', [], 0, true, 'not-a-source-parameter');
            parent::__construct('Parent', null, 42, true, 'example');
            new InformationException($failed ? 'Failed' : 'Success', from: 'example');
            new InformationException('admin' === $area ? 'Admin' : 'Client', from: 'example');
        """)
        self.assertEqual(self.entries(), {(message, None, 'example') for message in ['Named', 'Positional', 'Payment', 'Registrar', 'Server', 'Parent', 'Failed', 'Success', 'Admin', 'Client']} | {('Custom', None, 'other'), ('Unrelated', None, 'core')})

    def test_twig_inference_and_overrides(self) -> None:
        self.write("modules/Example/templates/client/page.twig", """
            {{ 'Inferred'|trans }}
            {{ 'Placeholders'|trans(values: {':n': nested(1, 2), 'from': 'other'}) }}
            {{ 'Override'|trans(from: 'other') }}
            {{ 'Equals syntax'|trans(from = 'other') }}
            {{ 'Positional'|trans({'from': 'not-a-source'}, 'other') }}
            {{ 'Core'|trans(from: 'core') }}
            {{ 'Null'|trans(from: null) }}
            {{ 'None'|trans(from: none) }}
            {{ 'Dollar $literal'|trans }}
            <input placeholder="{{ 'HTML quotes'|trans }}">
            {{ "Text containing 'Fake'|trans(from: 'other')" }}
            {# {{ 'Comment'|trans(from: 'other') }} #}
        """)
        self.write("themes/shared.twig", "{{ 'Layout'|trans }}")
        self.assertEqual(self.entries(), {
            ('Inferred', None, 'example'), ('Placeholders', None, 'example'), ('Override', None, 'other'),
            ('Equals syntax', None, 'other'), ('Positional', None, 'other'), ('Core', None, 'core'),
            ('Null', None, 'core'), ('None', None, 'core'), ('Dollar $literal', None, 'example'),
            ('HTML quotes', None, 'example'), ('Layout', None, 'core'),
        })

    def test_standalone_module_context_only_changes_twig_defaults(self) -> None:
        self.write("templates/page.twig", "{{ 'Implicit'|trans }}")
        self.write("Service.php", "<?php __trans('Shared'); __trans('Own', from: 'example');")
        self.assertEqual(self.entries('example'), {('Implicit', None, 'example'), ('Shared', None, 'core'), ('Own', None, 'example')})

    def test_twig_escapes_use_twig_rules_and_only_unescaped_interpolation_is_dynamic(self) -> None:
        self.write('page.twig', r'''{{ 'Hello\n:name'|trans }}
            {{ "Quote\'s"|trans }}
            {{ '\xC3\xA9'|trans(from: 'ex\x61mple') }}
            {{ "Literal \#{label}"|trans }}
            {{ 'Single #{literal}'|trans }}
            {{ "Dynamic #{label}"|trans }}
            {{ "Dynamic \\#{label}"|trans }}
        ''')
        self.assertEqual(self.entries('example'), {
            ('Hello\n:name', None, 'example'), ("Quote's", None, 'example'),
            ('é', None, 'example'), ('Literal #{label}', None, 'example'),
            ('Single #{literal}', None, 'example'),
        })

    def test_comments_and_nested_arguments_do_not_override_source(self) -> None:
        self.write("Case.php", """<?php
            __trans('A', ['from' => 'other', 'nested' => bar(from: 'other')], /* from: 'wrong' */ from: 'example');
            __trans('B', values: [':x' => fn ($a, $b) => [$a, $b]], from: 'example');
            __trans('C', values: ['text' => "from: 'other', )"], from: 'example');
        """)
        self.assertEqual(self.entries(), {(message, None, 'example') for message in ['A', 'B', 'C']})

    def test_dynamic_invalid_and_ambiguous_sources_fail_without_overwriting_output(self) -> None:
        cases = [
            ("Case.php", "<?php __trans('Message', from: $source);"),
            ("Case.php", "<?php __trans('Message', null, 'ex' . $source);"),
            ("Case.php", "<?php __trans('Message', from: \"ex$source\");"),
            ("Case.php", "<?php __trans('Message', from: '../Other');"),
            ("Case.php", "<?php __trans('Message', from: 'Example');"),
            ("Case.php", "<?php __trans('Message', ...$options);"),
            ("Case.php", "<?php __trans('Message', null, 'example', from: 'other');"),
            ("Case.twig", "{{ 'Message'|trans(from: source) }}"),
            ("Case.twig", "{{ 'Message'|trans(from: 'ex' ~ source) }}"),
            ("Case.twig", "{{ 'Message'|trans(from: \"#{source}\") }}"),
            ("Case.twig", "{{ 'Message'|trans(from: '') }}"),
        ]
        for name, content in cases:
            with self.subTest(content=content):
                for old in self.src.iterdir():
                    old.unlink()
                self.write(name, content)
                target = self.root / 'messages.pot'
                target.write_text('keep existing template', encoding='utf-8')
                result = self.cli(self.src, target)
                self.assertEqual(result.returncode, 1, result.stderr)
                self.assertIn('error:', result.stderr)
                self.assertEqual(target.read_text(), 'keep existing template')

    def test_translation_inputs_and_test_sources_are_not_extracted(self) -> None:
        self.write('Service.php', "<?php __trans('Visible');")
        for directory in ['locale', 'tests', 'vendor', 'node_modules', 'modules/Example/locale']:
            self.write(f'{directory}/input.php', "<?php __trans('Ignore');")
        self.assertEqual(self.entries(), {('Visible', None, 'core')})

    def test_combined_cli_remains_compatible_and_source_filter_is_opt_in(self) -> None:
        self.write('modules/Example/page.twig', "{{ 'Module'|trans }}")
        self.write('Service.php', "<?php __trans('Core');")
        target = self.root / 'messages.pot'
        result = self.cli(self.src, target)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(parse_pot_entries(str(target)), {'Core': None, 'Module': None})
        result = self.cli(self.src, target, '--source', 'example')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(parse_pot_entries(str(target)), {'Module': None})

    def test_check_is_read_only_ignores_dates_and_detects_reference_changes(self) -> None:
        self.write('Service.php', "<?php __trans('Original');")
        target = self.root / 'messages.pot'
        self.assertEqual(self.cli(self.src, target).returncode, 0)
        original = target.read_text()
        target.write_text(original.replace('POT-Creation-Date:', 'POT-Creation-Date: changed'), encoding='utf-8')
        before = target.read_bytes()
        self.assertEqual(self.cli(self.src, target, '--check').returncode, 0)
        self.assertEqual(target.read_bytes(), before)
        self.write('Service.php', "<?php\n\n__trans('Original');")
        result = self.cli(self.src, target, '--check')
        self.assertEqual(result.returncode, 1)
        self.assertIn('needs regeneration', result.stderr)
        self.assertEqual(target.read_bytes(), before)

    def test_missing_root_cannot_generate_an_empty_template(self) -> None:
        target = self.root / 'messages.pot'
        result = self.cli(self.root / 'missing', target)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(target.exists())

    def test_conflicting_plural_templates_can_be_rejected(self) -> None:
        with self.assertRaisesRegex(ValueError, 'conflicting plurals'):
            write_pot([Occurrence('One', 'Many', 'a:1'), Occurrence('One', 'Others', 'b:1')], str(self.root / 'conflict.pot'), strict=True)


if __name__ == '__main__':
    unittest.main()
