#!/usr/bin/env python3
"""End-to-end tests for module translation tooling, using temporary modules."""

from __future__ import annotations

import gettext
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))

from extract_pot import Occurrence, parse_pot_entries, po_escape, write_pot  # noqa: E402


class ModuleTranslationsTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.modules = self.root / 'modules'
        self.templates = self.root / 'templates'
        self.build = self.root / 'build'
        self.core = self.root / 'core.pot'
        write_pot([Occurrence('Save', None, 'core.php:1'), Occurrence('Shared item', 'Shared items', 'core.php:2')], str(self.core))
        for module in ['Example', 'Other']:
            self.write(f'{module}/manifest.json', json.dumps({'manifest_version': 1, 'type': 'module', 'id': module.lower()}))
        self.write('Example/templates/page.twig', "{{ 'Hello :name'|trans }} {{ 'Save'|trans(from: 'core') }}")
        self.write('Example/Service.php', "<?php __trans('Save'); __pluralTrans(':n item', ':n items', $n, from: 'example');")
        self.write('Other/templates/page.twig', "{{ 'Other'|trans }} {{ 'Hello :name'|trans }}")

    def write(self, relative: str, text: str) -> Path:
        path = self.modules / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding='utf-8')
        return path

    def cli(self, *arguments: str | Path) -> subprocess.CompletedProcess[str]:
        return subprocess.run([sys.executable, str(SCRIPTS / 'module_translations.py'), *map(str, arguments)], capture_output=True, text=True)

    def extract(self, *arguments: str) -> subprocess.CompletedProcess[str]:
        return self.cli('extract', self.modules, '--core-pot', self.core, '--output', self.templates, *arguments)

    def template(self, module: str = 'Example') -> Path:
        return self.templates / module / 'locale' / f'{module.lower()}.pot'

    def po(self, entries: list[tuple[str, str | None, list[str], bool]], locale: str = 'fr_FR') -> Path:
        header = [
            'Project-Id-Version: FOSSBilling module example\n',
            'Report-Msgid-Bugs-To: info@fossbilling.org\n',
            'POT-Creation-Date: 2026-01-01 00:00+0000\n',
            'PO-Revision-Date: 2026-01-01 00:00+0000\n',
            'Last-Translator: Test <test@example.com>\n',
            'Language-Team: Test\n',
            f'Language: {locale}\n',
            'MIME-Version: 1.0\n',
            'Content-Type: text/plain; charset=UTF-8\n',
            'Content-Transfer-Encoding: 8bit\n',
            'Plural-Forms: nplurals=2; plural=(n > 1);\n',
        ]
        lines = ['msgid ""', 'msgstr ""', *[f'"{po_escape(line)}"' for line in header], '']
        for msgid, plural, translations, fuzzy in entries:
            if fuzzy:
                lines.append('#, fuzzy')
            lines.append(f'msgid "{po_escape(msgid)}"')
            if plural is not None:
                lines.append(f'msgid_plural "{po_escape(plural)}"')
                lines.extend(f'msgstr[{index}] "{po_escape(value)}"' for index, value in enumerate(translations))
            else:
                lines.append(f'msgstr "{po_escape(translations[0])}"')
            lines.append('')
        return self.write(f'Example/locale/{locale}/LC_MESSAGES/example.po', '\n'.join(lines))

    def require_gettext(self) -> None:
        if shutil.which('msgfmt') is None:
            self.skipTest('GNU gettext msgfmt is not installed')

    def test_extracts_independent_templates_without_modifying_modules(self) -> None:
        before = {path.relative_to(self.modules): path.read_bytes() for path in self.modules.rglob('*') if path.is_file()}
        result = self.extract()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(parse_pot_entries(str(self.template())), {'Hello :name': None, ':n item': ':n items'})
        self.assertEqual(parse_pot_entries(str(self.template('Other'))), {'Hello :name': None, 'Other': None})
        self.assertIn('Project-Id-Version: FOSSBilling module example', self.template().read_text())
        self.assertIn('Example/templates/page.twig:1', self.template().read_text())
        after = {path.relative_to(self.modules): path.read_bytes() for path in self.modules.rglob('*') if path.is_file()}
        self.assertEqual(before, after)

    def test_generation_is_stable_and_check_reports_stale_templates_without_writing(self) -> None:
        self.assertEqual(self.extract().returncode, 0)
        path = self.template()
        path.write_text(path.read_text().replace('POT-Creation-Date:', 'POT-Creation-Date: old'), encoding='utf-8')
        before = path.read_bytes()
        self.assertEqual(self.extract().returncode, 0)
        self.assertEqual(path.read_bytes(), before)
        self.assertEqual(self.extract('--check').returncode, 0)
        self.write('Example/templates/page.twig', "{{ 'Changed'|trans }}")
        result = self.extract('--check')
        self.assertEqual(result.returncode, 1)
        self.assertIn('Hello :name', result.stdout)
        self.assertIn('Changed', result.stdout)
        self.assertEqual(path.read_bytes(), before)

    def test_cross_module_overrides_are_routed_to_the_requested_owner(self) -> None:
        self.write('Example/templates/cross.twig', "{{ 'Owned by Other'|trans(from: 'other') }}")
        self.assertEqual(self.extract().returncode, 0)
        self.assertNotIn('Owned by Other', parse_pot_entries(str(self.template())))
        self.assertIn('Owned by Other', parse_pot_entries(str(self.template('Other'))))

    def test_shared_core_calls_require_an_exact_pinned_core_entry(self) -> None:
        for code in ["__trans('New module message');", "__pluralTrans('Shared item', 'Wrong plural', $n);", "__trans('Shared item');"]:
            with self.subTest(code=code):
                self.write('Example/Service.php', '<?php ' + code)
                result = self.extract()
                self.assertEqual(result.returncode, 1)
                self.assertIn('not a matching shared core entry', result.stderr)
                self.assertFalse(self.templates.exists())

    def test_unknown_and_dynamic_sources_fail_before_updating_any_template(self) -> None:
        self.assertEqual(self.extract().returncode, 0)
        before = self.template().read_bytes()
        for expression in ["from: 'unknown'", 'from: source']:
            with self.subTest(expression=expression):
                self.write('Other/templates/page.twig', "{{ 'Changed'|trans(" + expression + ") }}")
                result = self.extract()
                self.assertEqual(result.returncode, 1)
                self.assertIn('Other/templates/page.twig:1', result.stderr)
                self.assertEqual(self.template().read_bytes(), before)

    def test_conflicting_plural_forms_abort_before_writing_templates(self) -> None:
        self.write('Other/Service.php', "<?php __pluralTrans('One', 'Many', $n, from: 'other'); __pluralTrans('One', 'Others', $n, from: 'other');")
        result = self.extract()
        self.assertEqual(result.returncode, 1)
        self.assertIn('conflicting plurals', result.stderr)
        self.assertFalse(self.templates.exists())

    def test_missing_roots_core_snapshot_and_invalid_manifests_fail(self) -> None:
        self.core.unlink()
        self.assertEqual(self.extract().returncode, 1)
        self.assertFalse(self.templates.exists())
        self.assertEqual(self.cli('extract', self.root / 'missing', '--core-pot', self.core, '--output', self.templates).returncode, 1)
        self.write('Example/manifest.json', json.dumps({'manifest_version': 1, 'type': 'module', 'id': '../example'}))
        result = self.extract()
        self.assertEqual(result.returncode, 1)
        self.assertIn('invalid or reserved module ID', result.stderr)

    def test_single_module_directory_is_supported(self) -> None:
        result = self.cli('extract', self.modules / 'Example', '--core-pot', self.core, '--output', self.templates)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(self.template().is_file())
        self.assertFalse(self.template('Other').exists())

    def test_missing_manifest_cannot_silently_omit_a_modules_strings(self) -> None:
        (self.modules / 'Other/manifest.json').unlink()
        result = self.extract()
        self.assertEqual(result.returncode, 1)
        self.assertIn('missing module manifest', result.stderr)
        self.assertFalse(self.templates.exists())

    def test_api_and_controller_modules_require_manifests(self) -> None:
        for layout in ('Api', 'Controller'):
            with self.subTest(layout=layout):
                path = self.write(f'Undeclared/{layout}/Client.php', "<?php __trans('Save');")
                result = self.extract()
                self.assertEqual(result.returncode, 1)
                self.assertIn('missing module manifest', result.stderr)
                self.assertFalse(self.templates.exists())
                path.unlink()
                path.parent.rmdir()

    def test_single_module_check_leaves_other_templates_alone(self) -> None:
        self.assertEqual(self.extract().returncode, 0)
        other = self.template('Other').read_bytes()
        for options in ([], ['--check']):
            result = self.cli('extract', self.modules / 'Example', '--core-pot', self.core, '--output', self.templates, *options)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(self.template('Other').read_bytes(), other)

    def test_orphaned_templates_require_review_instead_of_silent_deletion(self) -> None:
        self.assertEqual(self.extract().returncode, 0)
        shutil.rmtree(self.modules / 'Other')
        result = self.extract()
        self.assertEqual(result.returncode, 1)
        self.assertIn('review its removal manually', result.stderr)
        self.assertTrue(self.template('Other').exists())

    def test_compile_uses_real_gettext_and_preserves_module_and_locale_layout(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        po = self.po([
            ('Hello :name', None, ['Bonjour :name'], False),
            (':n item', ':n items', [':n objet', ':n objets'], False),
        ])
        before = po.read_bytes()
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 0, result.stderr)
        mo = self.build / 'Example/locale/fr_FR/LC_MESSAGES/example.mo'
        with mo.open('rb') as handle:
            translations = gettext.GNUTranslations(handle)
        self.assertEqual(translations.gettext('Hello :name'), 'Bonjour :name')
        self.assertEqual(translations.ngettext(':n item', ':n items', 2), ':n objets')
        self.assertFalse((self.build / 'Other').exists())
        self.assertEqual(po.read_bytes(), before)

    def test_validation_is_read_only_and_compilation_omits_fuzzy_and_empty_translations(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        self.po([('Hello :name', None, ['incorrect fuzzy placeholder'], True)])
        self.po([('Hello :name', None, [''], False)], locale='de_DE')
        result = self.cli('validate', self.modules, '--templates', self.templates)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse(self.build.exists())
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertFalse(self.build.exists())

    def test_placeholders_can_touch_translated_words_and_english_can_be_intentional(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        self.po([('Hello :name', None, ['你好:name'], False)])
        self.po([('Hello :name', None, ['Hello :name'], False)], locale='de_DE')
        self.po([('Hello :name', None, [':names Profil'], False)], locale='de_AT')
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 0, result.stderr)
        for locale, expected in [('fr_FR', '你好:name'), ('de_DE', 'Hello :name'), ('de_AT', ':names Profil')]:
            with (self.build / f'Example/locale/{locale}/LC_MESSAGES/example.mo').open('rb') as handle:
                self.assertEqual(gettext.GNUTranslations(handle).gettext('Hello :name'), expected)

    def test_placeholder_mismatch_rejects_the_entire_compile_without_partial_outputs(self) -> None:
        self.require_gettext()
        self.write('Example/Pair.php', "<?php __trans('Pair :name :names', from: 'example');")
        self.assertEqual(self.extract().returncode, 0)
        self.po([('Hello :name', None, ['Hallo :name'], False)], locale='de_DE')
        for message, translation in [('Hello :name', 'Bonjour :person'), ('Pair :name :names', ':names')]:
            with self.subTest(message=message):
                self.po([(message, None, [translation], False)])
                result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
                self.assertEqual(result.returncode, 1)
                self.assertIn('placeholders do not match', result.stderr)
                self.assertFalse(self.build.exists())

    def test_wrapped_po_entries_validate_and_obsolete_entries_are_ignored(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        po = self.po([('Hello :name', None, ['Bonjour :name'], False)])
        text = po.read_text().replace('msgid "Hello :name"', 'msgid ""\n"Hello "\n":name"')
        text = text.replace('msgstr "Bonjour :name"', 'msgstr ""\n"Bonjour "\n":name"')
        po.write_text(text + '\n#~ msgid "Removed"\n#~ msgstr "Supprimé"\n', encoding='utf-8')
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 0, result.stderr)
        with (self.build / 'Example/locale/fr_FR/LC_MESSAGES/example.mo').open('rb') as handle:
            translations = gettext.GNUTranslations(handle)
            self.assertEqual(translations.gettext('Hello :name'), 'Bonjour :name')
            self.assertEqual(translations.gettext('Removed'), 'Removed')

    def test_message_contexts_fail_instead_of_generating_unreachable_translations(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        po = self.po([('Hello :name', None, ['Bonjour :name'], False)])
        po.write_text(po.read_text().replace('msgid "Hello :name"', 'msgctxt "module"\nmsgid "Hello :name"'), encoding='utf-8')
        result = self.cli('validate', self.modules, '--templates', self.templates)
        self.assertEqual(result.returncode, 1)
        self.assertIn('message contexts are not supported', result.stderr)

    def test_stale_entries_wrong_plural_pairs_and_invalid_po_files_fail(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        for entry in [
            ('Removed', None, ['Supprimé'], False),
            (':n item', ':n things', [':n objet', ':n objets'], False),
        ]:
            with self.subTest(entry=entry):
                self.po([entry])
                result = self.cli('validate', self.modules, '--templates', self.templates)
                self.assertEqual(result.returncode, 1)
                self.assertIn('stale or mismatched entry', result.stderr)
        self.write('Example/locale/fr_FR/LC_MESSAGES/example.po', 'this is not a PO file')
        result = self.cli('validate', self.modules, '--templates', self.templates)
        self.assertEqual(result.returncode, 1)
        self.assertIn('msgfmt failed', result.stderr)

    def test_invalid_plural_definitions_fail_before_any_build_output(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        for rule, forms in [
            ('nplurals=2; plural=(n > 1);', [':n objet']),
            ('nplurals=2; plural=(n >);', [':n objet', ':n objets']),
            ('nplurals=2; plural=(n / 0);', [':n objet', ':n objets']),
            ('nplurals=2; plural=(n / 0);', ['', ':n objets']),
            ('nplurals=2; plural=5;', [':n objet', ':n objets']),
        ]:
            with self.subTest(rule=rule, forms=forms):
                po = self.po([(':n item', ':n items', forms, False)])
                po.write_text(po.read_text().replace('nplurals=2; plural=(n > 1);', rule), encoding='utf-8')
                before = po.read_bytes()
                for command in ('validate', 'compile'):
                    arguments = ['--output', self.build] if command == 'compile' else []
                    result = self.cli(command, self.modules, '--templates', self.templates, *arguments)
                    self.assertEqual(result.returncode, 1, result.stdout)
                    self.assertIn('msgfmt failed', result.stderr)
                    self.assertFalse(self.build.exists())
                    self.assertEqual(po.read_bytes(), before)

    def test_missing_plural_slots_fail_when_the_first_form_is_empty(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        po = self.po([(':n item', ':n items', ['', ':n objets'], False)])
        po.write_text(po.read_text().replace('nplurals=2; plural=(n > 1);', 'nplurals=3; plural=(n % 3);'), encoding='utf-8')
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 1)
        self.assertIn('missing plural forms', result.stderr)
        self.assertFalse(self.build.exists())

    def test_gettext_format_flags_are_checked_before_compilation(self) -> None:
        self.require_gettext()
        self.write('Example/Format.php', "<?php __trans('Value %s', from: 'example');")
        self.assertEqual(self.extract().returncode, 0)
        po = self.po([('Value %s', None, ['Valeur %d'], False)])
        po.write_text(po.read_text().replace('msgid "Value %s"', '#, c-format\nmsgid "Value %s"'), encoding='utf-8')
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 1)
        self.assertIn('msgfmt failed', result.stderr)
        self.assertFalse(self.build.exists())

    def test_empty_plural_forms_remain_valid_for_runtime_fallback(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        for index, forms in enumerate([[':n objet', ''], ['', ':n objets']]):
            with self.subTest(forms=forms):
                self.po([(':n item', ':n items', forms, False)])
                output = self.build / str(index)
                result = self.cli('compile', self.modules, '--templates', self.templates, '--output', output)
                self.assertEqual(result.returncode, 0, result.stderr)
                with (output / 'Example/locale/fr_FR/LC_MESSAGES/example.mo').open('rb') as handle:
                    translations = gettext.GNUTranslations(handle)
                    self.assertEqual(translations.ngettext(':n item', ':n items', 1), forms[0])
                    self.assertEqual(translations.ngettext(':n item', ':n items', 2), forms[1])

    def test_misnamed_translation_files_and_nonempty_build_directories_fail(self) -> None:
        self.require_gettext()
        self.assertEqual(self.extract().returncode, 0)
        po = self.po([('Hello :name', None, ['Bonjour :name'], False)])
        po.rename(po.with_name('messages.po'))
        result = self.cli('validate', self.modules, '--templates', self.templates)
        self.assertEqual(result.returncode, 1)
        self.assertIn('expected locale/<locale>', result.stderr)
        self.build.mkdir()
        (self.build / 'keep').write_text('existing build')
        result = self.cli('compile', self.modules, '--templates', self.templates, '--output', self.build)
        self.assertEqual(result.returncode, 1)
        self.assertEqual((self.build / 'keep').read_text(), 'existing build')


if __name__ == '__main__':
    unittest.main()
