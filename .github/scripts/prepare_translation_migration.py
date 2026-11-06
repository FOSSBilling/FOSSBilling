#!/usr/bin/env python3
"""Prepare an offline module/Crowdin migration bundle from a pinned locale checkout."""

from __future__ import annotations

import argparse
from collections import Counter
from copy import deepcopy
import difflib
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile

import polib

from extract_pot import extract_all, iter_source_files, parse_pot_entries, write_pot
from module_translations import compile_translations, discover_modules, module_occurrences, template_path

DEFAULT_PROFILE = Path(__file__).resolve().parents[2] / 'tools/i18n/migrations/branding-cookieconsent/profile.json'


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def check_po(path: Path) -> None:
    result = subprocess.run(['msgfmt', '--check', '--check-format', str(path), '-o', os.devnull], capture_output=True, text=True)
    if result.returncode:
        raise ValueError(f'{path}: msgfmt failed: {result.stderr.strip()}')


def empty_entry(template: polib.POEntry, plurals: int) -> polib.POEntry:
    entry = deepcopy(template)
    entry.msgstr = ''
    entry.msgstr_plural = {index: '' for index in range(plurals)} if entry.msgid_plural else {}
    return entry


def seed_translations(
    donor: polib.POFile, templates: polib.POFile, module_id: str, locale: str, revision: str,
) -> tuple[str, str, list[dict]]:
    if not donor.metadata:
        raise ValueError(f'{locale}: missing donor PO header')
    count = re.search(r'nplurals\s*=\s*(\d+)', donor.metadata.get('Plural-Forms', ''))
    if count is None or int(count.group(1)) < 1:
        raise ValueError(f'{locale}: invalid donor plural count')
    plurals = int(count.group(1))
    entries = {}
    for entry in donor:
        if entry.obsolete or entry.msgctxt is not None:
            continue
        key = (entry.msgid, entry.msgid_plural or None)
        if key in entries:
            raise ValueError(f'{locale}: duplicate donor entry {key!r}')
        entries[key] = entry

    seed = polib.POFile(encoding='utf-8', wrapwidth=0)
    seed.header = 'Selected from FOSSBilling/locale; translations remain CC BY 4.0.\n' + donor.header
    seed.metadata = {key: value for key, value in donor.metadata.items() if not key.startswith('X-Crowdin-')}
    seed.metadata.update({
        'Project-Id-Version': f'FOSSBilling module {module_id}',
        'Content-Type': 'text/plain; charset=UTF-8',
        'Language': locale,
        'X-Generator': 'FOSSBilling prepare_translation_migration.py',
        'X-FOSSBilling-Seed-Revision': revision,
    })
    imports = deepcopy(seed)
    audit: list[dict] = []
    donor_ids = {key[0] for key in entries}
    for template in templates:
        key = (template.msgid, template.msgid_plural or None)
        source = entries.get(key)
        if source is None:
            status = 'plural_mismatch' if template.msgid in donor_ids else 'missing'
            entry = empty_entry(template, plurals)
        else:
            forms = source.msgstr_plural.values() if source.msgid_plural else [source.msgstr]
            status = 'fuzzy' if source.fuzzy else ('translated' if any(forms) else 'untranslated')
            entry = deepcopy(source)
            entry.occurrences = template.occurrences.copy()
        seed.append(entry)
        imports.append(empty_entry(template, plurals) if status == 'fuzzy' else deepcopy(entry))
        audit.append({'msgid': key[0], 'plural': key[1], 'status': status})
    return str(seed), str(imports), audit


def prepare(app: Path, locale_root: Path, profile_path: Path, output: Path) -> dict:
    profile = json.loads(profile_path.read_text(encoding='utf-8'))
    revision = profile['locale_revision']
    if not re.fullmatch(r'[0-9a-f]{40}', revision):
        raise ValueError('profile must pin a full locale commit SHA')
    source = app / 'src'
    if not source.is_dir() or not locale_root.is_dir():
        raise ValueError('application src/ and locale checkout must exist')
    if output.resolve().is_relative_to(source.resolve()) or output.resolve().is_relative_to(locale_root.resolve()):
        raise ValueError('migration output must not be inside either source input')
    if output.exists() and (not output.is_dir() or any(output.iterdir())):
        raise ValueError('migration output must be a new or empty review directory')
    if shutil.which('msgfmt') is None:
        raise ValueError('GNU gettext msgfmt is required')
    verified = False
    if (locale_root / '.git').exists():
        actual = subprocess.run(['git', '-C', str(locale_root), 'rev-parse', 'HEAD'], check=True, capture_output=True, text=True).stdout.strip()
        if actual != revision:
            raise ValueError(f'locale checkout does not match pinned revision {revision}')
        dirty = subprocess.run([
            'git', '-C', str(locale_root), 'status', '--porcelain', '--untracked-files=all', '--ignored',
            '--', 'messages.pot', 'LICENSE.md', 'LICENSE.translations', '*/LC_MESSAGES/messages.po',
        ], check=True, capture_output=True, text=True)
        if dirty.stdout:
            raise ValueError('locale inputs differ from the pinned Git snapshot; use a clean checkout')
        verified = True

    report = {
        'status': 'prepared_offline', 'profile': profile['name'],
        'locale_repository': profile['locale_repository'], 'locale_revision': revision,
        'locale_git_revision_verified': verified, 'input_sha256': {},
        'modules': {}, 'languages': {}, 'source_changes': [],
        'pending_release_decisions': [
            'Create the Modules Crowdin project and supply its project ID; existing project 4 is not reused.',
            'Set module version and requires.fossbilling to the first released core version supporting this runtime.',
            'Verify source/target languages and shared translation-memory assignments in Crowdin before importing.',
            'PO seeds do not carry Crowdin approvals, votes, or review history; retain the original project as provenance.',
            'Do not apply the candidate core template until module packages and the new project exports are verified.',
        ],
    }
    original, stats = extract_all(str(source))
    if stats['source_errors']:
        raise ValueError('\n'.join(stats['source_errors']))
    app_digest = hashlib.sha256()
    for full, relative in iter_source_files(str(source)):
        app_digest.update(relative.encode() + b'\0' + Path(full).read_bytes() + b'\0')
    report['app_source_sha256'] = app_digest.hexdigest()
    for name in ('extract_pot.py', 'module_translations.py', 'prepare_translation_migration.py', 'requirements-i18n.txt'):
        report['input_sha256'][f'tooling/{name}'] = digest(Path(__file__).with_name(name))
    report['input_sha256']['profile.json'] = digest(profile_path)
    for name in ('messages.pot', 'LICENSE.md', 'LICENSE.translations'):
        report['input_sha256'][f'locale/{name}'] = digest(locale_root / name)
    donors = sorted(locale_root.glob('*/LC_MESSAGES/messages.po'))
    if not donors:
        raise ValueError('locale snapshot contains no PO files')

    with tempfile.TemporaryDirectory(prefix='fossbilling-migration-') as temporary:
        stage = Path(temporary)
        module_root = stage / 'modules'
        for name in profile['modules']:
            if not re.fullmatch(r'[A-Z][a-zA-Z0-9_-]*', name):
                raise ValueError(f'invalid module directory {name!r}')
            shutil.copytree(source / 'modules' / name, module_root / name)
        patches: list[str] = []
        for change in profile['source_changes']:
            path = Path(change['path'])
            if path.is_absolute() or '..' in path.parts or path.parts[0] not in profile['modules']:
                raise ValueError('source change must address a selected module')
            target = module_root / path
            before = target.read_text(encoding='utf-8')
            if before.count(change['before']) != 1:
                raise ValueError(f'{path}: source change must match exactly once; review source drift')
            after = before.replace(change['before'], change['after'])
            target.write_text(after, encoding='utf-8')
            patches.extend(difflib.unified_diff(before.splitlines(True), after.splitlines(True), f'a/{path}', f'b/{path}'))
            report['source_changes'].append(str(path))
        (stage / 'module-source.patch').write_text(''.join(patches), encoding='utf-8')

        modules = discover_modules(module_root)
        by_source, core_refs = module_occurrences(modules)
        prefixes = tuple(f'modules/{name}/' for name in profile['modules'])
        retained = [occ for occ in original if not occ.ref.startswith(prefixes)]
        core = stage / 'core'
        core.mkdir()
        write_pot(retained, str(core / 'messages.pot'), strict=True)
        core_entries = parse_pot_entries(str(core / 'messages.pot'))
        for occurrence in core_refs:
            if occurrence.msgid not in core_entries or core_entries[occurrence.msgid] != occurrence.plural:
                raise ValueError(f'{occurrence.ref}: core reference would be lost after module removal')
        before_entries = {occ.msgid for occ in original}
        report['core_cleanup'] = {
            'before': len(before_entries), 'after': len(core_entries),
            'removed': sorted(before_entries - core_entries.keys()),
            'shared_retained': sorted({occ.msgid for entries in by_source.values() for occ in entries} & core_entries.keys()),
        }
        templates = {}
        for module in modules:
            path = template_path(module_root, module)
            path.parent.mkdir(parents=True, exist_ok=True)
            write_pot(by_source[module.id], str(path), f'FOSSBilling module {module.id}', strict=True)
            templates[module.id] = polib.pofile(str(path))
            import_template = stage / 'crowdin-import' / path.relative_to(module_root)
            import_template.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(path, import_template)
            report['modules'][module.id] = {'entries': len(parse_pot_entries(str(path))), 'locales': {}}
            shutil.copyfile(locale_root / 'LICENSE.translations', module.path / 'LICENSE.translations')
            shutil.copyfile(locale_root / 'LICENSE.md', module.path / 'LICENSE.md')
            (module.path / 'TRANSLATION_ATTRIBUTION.md').write_text(
                f'# Translation attribution\n\nTranslations are CC BY 4.0, copyright the FOSSBilling contributors. '
                f'Code remains Apache-2.0. See LICENSE.translations and LICENSE.md.\n\n'
                f'Selected, without rewriting translated text, from {profile["locale_repository"]}/tree/{revision}. '
                f'Translator comments and fuzzy flags are retained where present. '
                f'Original translation history remains at https://translate.fossbilling.org.\n', encoding='utf-8',
            )

        for po in donors:
            locale = po.parts[-3]
            report['input_sha256'][f'locale/{locale}/LC_MESSAGES/messages.po'] = digest(po)
            if locale == profile['source_locale']:
                continue
            check_po(po)
            donor = polib.pofile(str(po))
            report['languages'][locale] = {'crowdin_language_id': donor.metadata.get('X-Crowdin-Language')}
            for module in modules:
                seeded, importable, audit = seed_translations(donor, templates[module.id], module.id, locale, revision)
                relative = Path(module.path.name) / 'locale' / locale / 'LC_MESSAGES' / f'{module.id}.po'
                for root, text in ((module_root, seeded), (stage / 'crowdin-import', importable)):
                    target = root / relative
                    target.parent.mkdir(parents=True, exist_ok=True)
                    target.write_text(text, encoding='utf-8')
                    # Validate even import-only files before making the bundle available.
                    check_po(target)
                report['modules'][module.id]['locales'][locale] = {'counts': dict(Counter(row['status'] for row in audit)), 'entries': audit}
        compile_translations(modules, module_root, stage / 'compiled')
        config_root = profile_path.parent
        for source_name, target in (
            ('crowdin-core.example.yml', core / 'crowdin.example.yml'),
            ('crowdin-modules.example.yml', module_root / 'crowdin.example.yml'),
            ('crowdin-modules.example.yml', stage / 'crowdin-import/crowdin.example.yml'),
        ):
            shutil.copyfile(config_root / source_name, target)
            report['input_sha256'][source_name] = digest(config_root / source_name)
        settings = {
            'core': {'existing_project_id': 4, 'desired_display_name': 'FOSSBilling Core', 'repository': 'FOSSBilling/locale'},
            'modules': {
                'project_id': None, 'desired_display_name': 'FOSSBilling Modules', 'repository': 'FOSSBilling/modules',
                'source_language': 'Copy the existing core project source language',
                'target_languages_from_donor_headers': report['languages'],
                'automatic_existing_translation_import': False,
                'shared_translation_memory': 'Assign the existing core translation memory after review',
            },
        }
        (stage / 'crowdin-project-settings.json').write_text(json.dumps(settings, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
        (stage / 'report.json').write_text(json.dumps(report, indent=2, ensure_ascii=False, sort_keys=True) + '\n', encoding='utf-8')
        (stage / 'REPORT.md').write_text(markdown_report(report), encoding='utf-8')
        output.parent.mkdir(parents=True, exist_ok=True)
        shutil.copytree(stage, output, dirs_exist_ok=True)
    return report


def markdown_report(report: dict) -> str:
    lines = [
        '# Branding and Cookieconsent translation migration', '',
        'Prepared offline. No source checkout or live Crowdin project was modified.', '',
        f'Locale snapshot: [{report["locale_revision"]}]({report["locale_repository"]}/tree/{report["locale_revision"]}).', '',
        f'Locale revision: {"verified Git snapshot" if report["locale_git_revision_verified"] else "unverified export"}. '
        f'Target locale folders: {len(report["languages"])}; source English is not imported as a target.', '',
        f'Application source SHA-256: `{report["app_source_sha256"]}`.', '',
        '| Module | Source entries | Locales with usable seeded translations |',
        '| --- | ---: | ---: |',
    ]
    for module, data in report['modules'].items():
        usable = sum(bool(locale['counts'].get('translated')) for locale in data['locales'].values())
        lines.append(f'| {module} | {data["entries"]} | {usable} |')
    lines.extend([
        '', '## Review artifacts', '',
        '- `modules/`: candidate module copies with templates, seeded PO files, and translation licences.',
        '- `module-source.patch`: staged Cookieconsent source changes; the app checkout is unchanged.',
        '- `crowdin-import/`: import copies with fuzzy work excluded, including intentional source-equal translations.',
        '- `compiled/`: validated MO files; no percentage-based language cutoff.',
        '- `core/messages.pot`: candidate core template for after module removal; do not upload it now.',
        '- `crowdin-project-settings.json`: proposed project settings, including donor language IDs.',
        '- `report.json`: per-entry migration disposition and input SHA-256 hashes.', '',
        '## Candidate core cleanup', '',
        f'Core entries: {report["core_cleanup"]["before"]} before, {report["core_cleanup"]["after"]} after.', '',
        'Only strings with no remaining application source reference are removed:', '',
    ])
    lines.extend(f'- {json.dumps(msgid, ensure_ascii=False)}' for msgid in report['core_cleanup']['removed'])
    lines.extend(['', 'Shared strings retained in core:', ''])
    lines.extend(f'- {json.dumps(msgid, ensure_ascii=False)}' for msgid in report['core_cleanup']['shared_retained'])
    lines.extend(['', '## Decisions before activation', ''])
    lines.extend(f'- {decision}' for decision in report['pending_release_decisions'])
    lines.extend([
        '', 'Exact msgid AND plural matches are copied. Missing, differently cased, contextual, obsolete, or changed-plural entries are not guessed. '
        'Fuzzy work stays in module seeds for review but is blank in the import copies and omitted from compiled translations. '
        'Stored administrator-written cookie notices are unchanged.', '',
    ])
    return '\n'.join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--app-root', required=True, type=Path)
    parser.add_argument('--locale-root', required=True, type=Path)
    parser.add_argument('--profile', type=Path, default=DEFAULT_PROFILE)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args(argv)
    try:
        prepare(args.app_root, args.locale_root, args.profile, args.output)
    except (ValueError, OSError, KeyError, subprocess.CalledProcessError) as error:
        print(f'error: {error}', file=sys.stderr)
        return 1
    print(f'Prepared offline migration bundle: {args.output}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
