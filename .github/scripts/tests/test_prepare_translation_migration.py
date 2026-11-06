#!/usr/bin/env python3
"""Offline migration tests; no Crowdin account or live project is accessed."""

from __future__ import annotations

import gettext
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

import polib

SCRIPTS = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPTS))

from extract_pot import extract_all, parse_pot_entries, write_pot  # noqa: E402
from prepare_translation_migration import DEFAULT_PROFILE, seed_translations  # noqa: E402


HEADER = '''msgid ""
msgstr ""
"Project-Id-Version: Core\\n"
"POT-Creation-Date: 2026-01-01 00:00+0000\\n"
"PO-Revision-Date: 2026-01-01 00:00+0000\\n"
"Last-Translator: Original translator <test@example.com>\\n"
"Language-Team: French\\n"
"Language: fr_FR\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=3; plural=(n == 1 ? 0 : n == 2 ? 1 : 2);\\n"
"X-Crowdin-Language: fr\\n"
"X-Crowdin-Project-ID: 4\\n"
'''


class SeedTranslationsTest(unittest.TestCase):
    def test_exact_matching_comments_flags_headers_and_source_equal_translations(self) -> None:
        donor = polib.pofile(HEADER + '''
# Translator comment
#: old/source.php:1
msgid "Exact"
msgstr "Exact translation"

#, fuzzy, php-format
msgid "Fuzzy"
msgstr "Preserve for review"

msgid "Same"
msgstr "Same"

msgid "lowercase"
msgstr "No casing guess"

#~ msgid "Obsolete"
#~ msgstr "Do not revive"

msgctxt "a different context"
msgid "Context"
msgstr "Do not copy"
''')
        templates = polib.pofile('\n\n'.join(f'#: Example/page.twig:{index}\nmsgid "{message}"\nmsgstr ""' for index, message in enumerate(['Exact', 'Fuzzy', 'Same', 'Lowercase', 'Obsolete', 'Context'], 1)))
        seed, imports, audit = seed_translations(donor, templates, 'example', 'fr_FR', 'a' * 40)
        self.assertIn('# Translator comment', seed)
        self.assertIn('#: Example/page.twig:1', seed)
        self.assertNotIn('old/source.php', seed)
        self.assertIn('Original translator <test@example.com>', seed)
        self.assertNotIn('X-Crowdin-Project-ID', seed)
        self.assertIn('X-FOSSBilling-Seed-Revision: ' + 'a' * 40, seed)
        self.assertIn('#, fuzzy, php-format', seed)
        self.assertIn('Preserve for review', seed)
        self.assertNotIn('Preserve for review', imports)
        self.assertIn('msgstr "Same"', imports)
        self.assertNotIn('No casing guess', seed)
        self.assertNotIn('Do not revive', seed)
        self.assertNotIn('Do not copy', seed)
        self.assertEqual([row['status'] for row in audit], ['translated', 'fuzzy', 'translated', 'missing', 'missing', 'missing'])

    def test_plural_pairs_are_exact_and_missing_entries_use_the_locale_plural_count(self) -> None:
        donor = polib.pofile(HEADER.replace('Plural-Forms: ', 'Plural-Forms:') + '''
msgid "One"
msgid_plural "Wrong plural"
msgstr[0] "Wrong 0"
msgstr[1] "Wrong 1"
msgstr[2] "Wrong 2"

msgid "Item"
msgid_plural "Items"
msgstr[0] ""
msgstr[1] "Two items"
msgstr[2] "Many items"
''')
        templates = polib.pofile('''msgid "One"
msgid_plural "Many"
msgstr[0] ""
msgstr[1] ""

msgid "Item"
msgid_plural "Items"
msgstr[0] ""
msgstr[1] ""
''')
        seed, imports, audit = seed_translations(donor, templates, 'example', 'fr_FR', 'a' * 40)
        self.assertNotIn('Wrong 0', seed)
        self.assertIn('msgstr[2] ""', seed)
        self.assertIn('msgstr[2] "Many items"', seed)
        self.assertEqual(polib.pofile(seed).find('Item').msgstr_plural, {0: '', 1: 'Two items', 2: 'Many items'})
        self.assertEqual(polib.pofile(imports).find('Item').msgstr_plural, {0: '', 1: 'Two items', 2: 'Many items'})
        self.assertEqual([row['status'] for row in audit], ['plural_mismatch', 'translated'])


@unittest.skipUnless(shutil.which('msgfmt'), 'GNU gettext is required')
class PrepareMigrationTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.app = self.root / 'app'
        self.locale = self.root / 'locale'
        self.output = self.root / 'prepared'
        self.profile = json.loads(DEFAULT_PROFILE.read_text())
        self.profile['locale_revision'] = 'a' * 40
        self.profile_dir = self.root / 'profile'
        self.profile_dir.mkdir()
        self.profile_path = self.profile_dir / 'profile.json'
        self.profile_path.write_text(json.dumps(self.profile))
        for name in ['crowdin-core.example.yml', 'crowdin-modules.example.yml']:
            shutil.copyfile(DEFAULT_PROFILE.with_name(name), self.profile_dir / name)
        for module in self.profile['modules']:
            self.write_app(f'modules/{module}/manifest.json', json.dumps({'manifest_version': 1, 'type': 'module', 'id': module.lower()}))
        self.write_app('modules/Branding/templates/page.twig', "{{ 'Powered by'|trans }} {{ 'FOSSBilling'|trans }}")
        self.write_app('modules/Cookieconsent/Controller/Admin.php', "<?php __trans('Cookie Consent');")
        service_before = self.profile['source_changes'][1]['before']
        self.write_app('modules/Cookieconsent/Service.php', '<?php return ' + service_before + ';')
        twig_before = self.profile['source_changes'][2]['before']
        self.write_app('modules/Cookieconsent/templates/admin/mod_cookieconsent_index.html.twig', "{{ 'Cookie Consent Settings'|trans }} {{ 'Edit Cookie Consent Message Text'|trans }} {{ 'Update'|trans }} {% if custom %}Custom" + twig_before)
        self.write_app('core/Shared.php', "<?php __trans('Update'); __trans('Core only');")
        self.locale.mkdir()
        original, _ = extract_all(str(self.app / 'src'))
        write_pot(original, str(self.locale / 'messages.pot'))
        for name in ['LICENSE.md', 'LICENSE.translations']:
            (self.locale / name).write_text('Fixture licence\n')
        po = self.locale / 'fr_FR/LC_MESSAGES/messages.po'
        po.parent.mkdir(parents=True)
        po.write_text(HEADER + '''
msgid "Powered by"
msgstr "Propulsé par"

msgid "FOSSBilling"
msgstr "FOSSBilling"

msgid "Cookie Consent"
msgstr "Consentement aux cookies"

msgid "Cookie Consent Settings"
msgstr "Paramètres des cookies"

#, fuzzy
msgid "Edit Cookie Consent Message Text"
msgstr "A revoir"

msgid "Update"
msgstr "Mettre à jour"
''', encoding='utf-8')
        english = self.locale / 'en_US/LC_MESSAGES/messages.po'
        english.parent.mkdir(parents=True)
        english.write_text(HEADER)

    def write_app(self, relative: str, text: str) -> Path:
        path = self.app / 'src' / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding='utf-8')
        return path

    def run_prepare(self, output: Path | None = None) -> subprocess.CompletedProcess[str]:
        return subprocess.run([
            sys.executable, str(SCRIPTS / 'prepare_translation_migration.py'),
            '--app-root', str(self.app), '--locale-root', str(self.locale),
            '--profile', str(self.profile_path), '--output', str(output or self.output),
        ], capture_output=True, text=True)

    def input_bytes(self) -> dict[str, bytes]:
        return {str(path.relative_to(self.root)): path.read_bytes() for base in [self.app, self.locale] for path in base.rglob('*') if path.is_file()}

    def test_prepares_auditable_seeds_imports_core_cleanup_and_source_patch_without_mutating_inputs(self) -> None:
        before = self.input_bytes()
        result = self.run_prepare()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.input_bytes(), before)
        report = json.loads((self.output / 'report.json').read_text())
        self.assertEqual(report['status'], 'prepared_offline')
        self.assertFalse(report['locale_git_revision_verified'])
        self.assertEqual(report['languages'], {'fr_FR': {'crowdin_language_id': 'fr'}})
        self.assertIn('Update', report['core_cleanup']['shared_retained'])
        self.assertNotIn('Update', report['core_cleanup']['removed'])
        self.assertIn('Cookie Consent', report['core_cleanup']['removed'])
        core = parse_pot_entries(str(self.output / 'core/messages.pot'))
        self.assertEqual(core, {'Core only': None, 'Update': None})
        self.assertIn("from: 'cookieconsent'", (self.output / 'module-source.patch').read_text())
        module = self.output / 'modules/Cookieconsent'
        self.assertIn('A revoir', (module / 'locale/fr_FR/LC_MESSAGES/cookieconsent.po').read_text())
        self.assertNotIn('A revoir', (self.output / 'crowdin-import/Cookieconsent/locale/fr_FR/LC_MESSAGES/cookieconsent.po').read_text())
        self.assertFalse((module / 'locale/en_US').exists())
        self.assertTrue((module / 'LICENSE.translations').exists())
        self.assertIn('FOSSBilling contributors', (module / 'TRANSLATION_ATTRIBUTION.md').read_text())
        with (self.output / 'compiled/Cookieconsent/locale/fr_FR/LC_MESSAGES/cookieconsent.mo').open('rb') as handle:
            translations = gettext.GNUTranslations(handle)
            self.assertEqual(translations.gettext('Cookie Consent'), 'Consentement aux cookies')
            self.assertEqual(translations.gettext('Edit Cookie Consent Message Text'), 'Edit Cookie Consent Message Text')
        settings = json.loads((self.output / 'crowdin-project-settings.json').read_text())
        self.assertEqual(settings['core']['existing_project_id'], 4)
        self.assertIsNone(settings['modules']['project_id'])

    def test_configuration_maps_each_source_to_its_own_po_path(self) -> None:
        self.assertEqual(self.run_prepare().returncode, 0)
        config = (self.output / 'modules/crowdin.example.yml').read_text()
        self.assertIn('project_id_env: CROWDIN_MODULES_PROJECT_ID', config)
        self.assertNotIn('project_id: 4', config)
        for name in ['Branding', 'Cookieconsent']:
            source = f'{name}/locale/{name.lower()}.pot'
            translation = f'{name}/locale/%locale_with_underscore%/LC_MESSAGES/{name.lower()}.po'
            self.assertIn('source: /' + source, config)
            self.assertIn('translation: /' + translation, config)
            self.assertTrue((self.output / 'modules' / source).is_file())
            self.assertTrue((self.output / 'crowdin-import' / translation.replace('%locale_with_underscore%', 'fr_FR')).is_file())

    def test_source_drift_invalid_revision_and_existing_outputs_fail_without_writing(self) -> None:
        self.write_app('modules/Cookieconsent/Controller/Admin.php', '<?php __trans("Changed");')
        result = self.run_prepare()
        self.assertEqual(result.returncode, 1)
        self.assertIn('source drift', result.stderr)
        self.assertFalse(self.output.exists())
        self.profile['locale_revision'] = 'main'
        self.profile_path.write_text(json.dumps(self.profile))
        self.assertIn('full locale commit SHA', self.run_prepare().stderr)
        self.assertFalse(self.output.exists())
        self.profile['locale_revision'] = 'a' * 40
        self.profile_path.write_text(json.dumps(self.profile))
        self.output.mkdir()
        (self.output / 'keep').write_text('existing data')
        result = self.run_prepare()
        self.assertEqual(result.returncode, 1)
        self.assertIn('new or empty review directory', result.stderr)
        self.assertEqual((self.output / 'keep').read_text(), 'existing data')

    def test_output_cannot_be_written_inside_either_input(self) -> None:
        for output in [self.app / 'src/prepared', self.locale / 'prepared']:
            result = self.run_prepare(output)
            self.assertEqual(result.returncode, 1)
            self.assertIn('must not be inside', result.stderr)
            self.assertFalse(output.exists())

    def test_invalid_donor_fails_without_partial_output(self) -> None:
        (self.locale / 'fr_FR/LC_MESSAGES/messages.po').write_text('not valid PO')
        result = self.run_prepare()
        self.assertEqual(result.returncode, 1)
        self.assertFalse(self.output.exists())

    @unittest.skipUnless(shutil.which('git'), 'Git is required')
    def test_git_snapshot_must_match_the_pin_and_have_clean_input_files(self) -> None:
        def git(*arguments: str) -> subprocess.CompletedProcess[str]:
            return subprocess.run(['git', '-C', str(self.locale), *arguments], check=True, capture_output=True, text=True)

        git('init', '--quiet')
        git('add', '.')
        git('-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.com', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '--quiet', '-m', 'Locale fixture')
        self.assertIn('does not match pinned revision', self.run_prepare().stderr)
        self.profile['locale_revision'] = git('rev-parse', 'HEAD').stdout.strip()
        self.profile_path.write_text(json.dumps(self.profile))
        self.assertEqual(self.run_prepare().returncode, 0)
        self.assertTrue(json.loads((self.output / 'report.json').read_text())['locale_git_revision_verified'])
        po = self.locale / 'fr_FR/LC_MESSAGES/messages.po'
        po.write_text(po.read_text() + '\n# Changed after snapshot\n')
        other_output = self.root / 'dirty-output'
        result = self.run_prepare(other_output)
        self.assertEqual(result.returncode, 1)
        self.assertIn('clean checkout', result.stderr)
        self.assertFalse(other_output.exists())

    @unittest.skipUnless(shutil.which('git'), 'Git is required')
    def test_ignored_donor_inputs_cannot_be_claimed_as_verified_snapshot_files(self) -> None:
        def git(*arguments: str) -> subprocess.CompletedProcess[str]:
            return subprocess.run(['git', '-C', str(self.locale), *arguments], check=True, capture_output=True, text=True)

        (self.locale / '.gitignore').write_text('de_DE/\n')
        git('init', '--quiet')
        git('add', '.')
        git('-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.com', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '--quiet', '-m', 'Locale fixture')
        self.profile['locale_revision'] = git('rev-parse', 'HEAD').stdout.strip()
        self.profile_path.write_text(json.dumps(self.profile))
        donor = self.locale / 'de_DE/LC_MESSAGES/messages.po'
        donor.parent.mkdir(parents=True)
        shutil.copyfile(self.locale / 'fr_FR/LC_MESSAGES/messages.po', donor)
        result = self.run_prepare()
        self.assertEqual(result.returncode, 1)
        self.assertIn('clean checkout', result.stderr)
        self.assertFalse(self.output.exists())


if __name__ == '__main__':
    unittest.main()
