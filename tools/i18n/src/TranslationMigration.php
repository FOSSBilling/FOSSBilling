<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\I18n;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/** Prepare review files locally; never contact Crowdin or modify the source inputs. */
final class TranslationMigration
{
    private static function run(array $command, ?string $cwd = null): string
    {
        return (new Process($command, $cwd))->mustRun()->getOutput();
    }

    private static function checkPo(string $path): void
    {
        self::run(['msgfmt', '--check', '--check-format', $path, '-o', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null']);
    }

    private static function key(array $entry): string
    {
        return json_encode([$entry['msgctxt'] ?? null, $entry['msgid'], $entry['msgid_plural'] ?? null], JSON_THROW_ON_ERROR);
    }

    /** GNU gettext handles encoding, obsolete entries and normalization; retain its comment blocks. */
    private static function readPo(string $path): array
    {
        return TemporaryDirectory::run(static function ($temporary) use ($path): array {
            $filtered = Path::join($temporary, 'filtered.po');
            $canonical = Path::join($temporary, 'canonical.po');
            // Record membership before msgcat can drop first-empty fuzzy plural flags.
            self::run(['msgattrib', '--only-fuzzy', '--no-obsolete', '--force-po', $path, '-o', $filtered]);
            self::run(['msgcat', '--no-wrap', '--force-po', '--to-code=UTF-8', $filtered, '-o', $canonical]);
            $fuzzy = array_fill_keys(array_map(self::key(...), TranslationCatalog::read($canonical)), true);
            self::run(['msgattrib', '--no-obsolete', '--force-po', $path, '-o', $filtered]);
            self::run(['msgcat', '--no-wrap', '--force-po', '--to-code=UTF-8', $filtered, '-o', $canonical]);
            $blocks = preg_split('/\n\s*\n/', trim((new Filesystem())->readFile($canonical)));
            $entries = TranslationCatalog::read($canonical);
            if (count($entries) !== count($blocks)) {
                throw new \RuntimeException("$path: unexpected GNU gettext output");
            }
            $result = [];
            foreach ($entries as $index => $entry) {
                $comments = array_values(array_filter(explode("\n", $blocks[$index]), static fn ($line): bool => str_starts_with($line, '#')));
                if ($entry['msgid'] !== '' && isset($fuzzy[self::key($entry)]) && !preg_grep('/^#,.*\bfuzzy\b/', $comments)) {
                    $comments[] = '#, fuzzy';
                }
                $result[] = ['fields' => $entry, 'comments' => $comments];
            }

            return $result;
        });
    }

    private static function metadata(array $entries): array
    {
        $metadata = [];
        foreach ($entries as $entry) {
            if ($entry['fields']['msgid'] !== '') {
                continue;
            }
            foreach (explode("\n", $entry['fields']['msgstr'] ?? '') as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $metadata[$name] = ltrim($value);
                }
            }
        }

        return $metadata;
    }

    private static function render(array $entry): string
    {
        $text = $entry['comments'] ? implode("\n", $entry['comments']) . "\n" : '';
        foreach ($entry['fields'] as $name => $value) {
            $text .= $name . ' "' . TranslationCatalog::escape($value) . "\"\n";
        }

        return $text;
    }

    private static function emptyEntry(array $template, int $plurals): array
    {
        $entry = $template;
        $entry['comments'] = array_values(array_filter($entry['comments'], static fn ($line): bool => !str_starts_with($line, '#,')));
        $entry['fields'] = array_filter($entry['fields'], static fn ($name): bool => !str_starts_with($name, 'msgstr'), ARRAY_FILTER_USE_KEY);
        if (isset($entry['fields']['msgid_plural'])) {
            for ($i = 0; $i < $plurals; ++$i) {
                $entry['fields']["msgstr[$i]"] = '';
            }
        } else {
            $entry['fields']['msgstr'] = '';
        }

        return $entry;
    }

    private static function seed(array $donor, array $templates, string $module, string $locale, string $revision): array
    {
        $metadata = self::metadata($donor);
        if (!preg_match('/\bnplurals\s*=\s*(\d+)/', $metadata['Plural-Forms'] ?? '', $match) || (int) $match[1] < 1) {
            throw new \RuntimeException("$locale: invalid donor plural count");
        }
        $plurals = (int) $match[1];
        $metadata = array_filter($metadata, static fn ($name): bool => !str_starts_with($name, 'X-Crowdin-'), ARRAY_FILTER_USE_KEY);
        $metadata = array_replace($metadata, [
            'Project-Id-Version' => "FOSSBilling module $module", 'Content-Type' => 'text/plain; charset=UTF-8',
            'Language' => $locale, 'X-Generator' => 'FOSSBilling translation migration', 'X-FOSSBilling-Seed-Revision' => $revision,
        ]);
        $header = '';
        foreach ($metadata as $name => $value) {
            $header .= "$name: $value\n";
        }
        $comments = ['# Selected from FOSSBilling/locale; translations remain CC BY 4.0.'];
        $entries = $ids = [];
        foreach ($donor as $entry) {
            $fields = $entry['fields'];
            if ($fields['msgid'] === '') {
                array_push($comments, ...$entry['comments']);
            } elseif (!array_key_exists('msgctxt', $fields)) {
                $entries[self::key($fields)] = $entry;
                $ids[$fields['msgid']] = true;
            }
        }
        $seed = self::render(['fields' => ['msgid' => '', 'msgstr' => $header], 'comments' => $comments]);
        $imports = $seed;
        $audit = [];
        foreach ($templates as $template) {
            $fields = $template['fields'];
            if ($fields['msgid'] === '') {
                continue;
            }
            $entry = $entries[self::key($fields)] ?? null;
            if ($entry === null) {
                $status = isset($ids[$fields['msgid']]) ? 'plural_mismatch' : 'missing';
                $entry = self::emptyEntry($template, $plurals);
            } else {
                $forms = array_filter($entry['fields'], static fn ($name): bool => str_starts_with($name, 'msgstr'), ARRAY_FILTER_USE_KEY);
                $status = preg_grep('/^#,.*\bfuzzy\b/', $entry['comments']) ? 'fuzzy' : (count(array_filter($forms, static fn ($value): bool => $value !== '')) ? 'translated' : 'untranslated');
                $entry['comments'] = array_values(array_filter($entry['comments'], static fn ($line): bool => !str_starts_with($line, '#:')));
                array_push($entry['comments'], ...preg_grep('/^#:/', $template['comments']));
            }
            $seed .= "\n" . self::render($entry);
            $imports .= "\n" . self::render($status === 'fuzzy' ? self::emptyEntry($template, $plurals) : $entry);
            $audit[] = ['msgid' => $fields['msgid'], 'plural' => $fields['msgid_plural'] ?? null, 'status' => $status];
        }

        return [$seed, $imports, $audit];
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    public static function prepare(string $app, string $localeRoot, string $profilePath, string $output): array
    {
        $fs = new Filesystem();
        $app = Path::makeAbsolute($app, getcwd());
        $localeRoot = realpath($localeRoot) ?: Path::makeAbsolute($localeRoot, getcwd());
        $source = realpath(Path::join($app, 'src'));
        // Resolve existing parents as well as the final path, so symlinks cannot bypass the input boundary.
        $parent = Path::makeAbsolute($output, getcwd());
        $suffix = [];
        while (!$fs->exists($parent)) {
            array_unshift($suffix, basename($parent));
            $parent = dirname($parent);
        }
        $output = Path::join($fs->readlink($parent, true), ...$suffix);
        $profile = json_decode($fs->readFile($profilePath), true, flags: JSON_THROW_ON_ERROR);
        $revision = $profile['locale_revision'];
        if (!preg_match('/\A[0-9a-f]{40}\z/', $revision)) {
            throw new \RuntimeException('profile must pin a full locale commit SHA');
        }
        if ($source === false || !is_dir($localeRoot)) {
            throw new \RuntimeException('application src/ and locale checkout must exist');
        }
        if (Path::isBasePath($source, $output) || Path::isBasePath($localeRoot, $output)) {
            throw new \RuntimeException('migration output must not be inside either source input');
        }
        if ($fs->exists($output) && (!is_dir($output) || count((new Finder())->in($output)->depth('== 0')->ignoreDotFiles(false)->ignoreVCS(false)))) {
            throw new \RuntimeException('migration output must be a new or empty review directory');
        }
        $verified = false;
        if ($fs->exists(Path::join($localeRoot, '.git'))) {
            if (trim(self::run(['git', 'rev-parse', 'HEAD'], $localeRoot)) !== $revision) {
                throw new \RuntimeException("locale checkout does not match pinned revision $revision");
            }
            if (self::run(['git', 'status', '--porcelain', '--untracked-files=all', '--ignored', '--', 'messages.pot', 'LICENSE.md', 'LICENSE.translations', '*/LC_MESSAGES/messages.po'], $localeRoot) !== '') {
                throw new \RuntimeException('locale inputs differ from the pinned Git snapshot; use a clean checkout');
            }
            $verified = true;
        }
        $report = [
            'status' => 'prepared_offline', 'profile' => $profile['name'], 'locale_repository' => $profile['locale_repository'],
            'locale_revision' => $revision, 'locale_git_revision_verified' => $verified, 'input_sha256' => [],
            'modules' => [], 'languages' => [], 'source_changes' => [],
            'pending_release_decisions' => [
                'Create the Modules Crowdin project and supply its project ID; existing project 4 is not reused.',
                'Set module version and requires.fossbilling to the first released core version supporting this runtime.',
                'Verify source/target languages and shared translation-memory assignments in Crowdin before importing.',
                'PO seeds do not carry Crowdin approvals, votes, or review history; retain the original project as provenance.',
                'Do not apply the candidate core template until module packages and the new project exports are verified.',
            ],
        ];
        $original = Extractor::extract($source);
        if ($original['source_errors']) {
            throw new \RuntimeException(implode("\n", $original['source_errors']));
        }
        $hash = hash_init('sha256');
        foreach ((new Finder())->useUnixPaths()->files()->in($source)->name(['*.php', '*.twig'])->exclude(['vendor', 'install', 'data', 'tests', 'locale', 'node_modules', '.git'])->notName('load.php')->sortByName() as $file) {
            hash_update($hash, $file->getRelativePathname() . "\0" . $file->getContents() . "\0");
        }
        $report['app_source_sha256'] = hash_final($hash);
        $toolRoot = dirname(__DIR__);
        foreach ((new Finder())->useUnixPaths()->files()->in(Path::join($toolRoot, 'src'))->name('*.php')->sortByName() as $file) {
            $report['input_sha256']['tooling/src/' . $file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        foreach (['composer.json', 'composer.lock', 'bootstrap.php', 'bin/console'] as $name) {
            $report['input_sha256']["tooling/$name"] = hash_file('sha256', Path::join($toolRoot, $name));
        }
        $report['input_sha256']['profile.json'] = hash_file('sha256', $profilePath);
        foreach (['messages.pot', 'LICENSE.md', 'LICENSE.translations'] as $name) {
            $report['input_sha256']["locale/$name"] = hash_file('sha256', Path::join($localeRoot, $name));
        }
        $donors = (new Finder())->useUnixPaths()->files()->in($localeRoot)->path('~^[^/]+/LC_MESSAGES/~')->name('messages.po')->sortByName();
        if (!count($donors)) {
            throw new \RuntimeException('locale snapshot contains no PO files');
        }

        return TemporaryDirectory::run(static function ($stage) use ($source, $original, $profile, $profilePath, $revision, $localeRoot, $donors, $output, $fs, $report): array {
            $moduleRoot = Path::join($stage, 'modules');
            foreach ($profile['modules'] as $name) {
                if (!preg_match('/\A[A-Z][a-zA-Z0-9_-]*\z/', $name)) {
                    throw new \RuntimeException("invalid module directory '$name'");
                }
                $fs->mirror(Path::join($source, 'modules', $name), Path::join($moduleRoot, $name));
            }
            $patch = '';
            foreach ($profile['source_changes'] as $change) {
                $path = $change['path'];
                if (Path::isAbsolute($path) || in_array('..', explode('/', $path), true) || !in_array(explode('/', $path)[0], $profile['modules'], true)) {
                    throw new \RuntimeException('source change must address a selected module');
                }
                $target = Path::join($moduleRoot, $path);
                $before = $fs->readFile($target);
                if (substr_count($before, $change['before']) !== 1) {
                    throw new \RuntimeException("$path: source change must match exactly once; review source drift");
                }
                $fs->dumpFile(Path::join($stage, 'before'), $before);
                $fs->dumpFile($target, str_replace($change['before'], $change['after'], $before));
                $diff = new Process(['diff', '-u', '--label', "a/$path", '--label', "b/$path", Path::join($stage, 'before'), $target]);
                $diff->run();
                if (!in_array($diff->getExitCode(), [0, 1], true)) {
                    throw new \RuntimeException($diff->getErrorOutput());
                }
                $patch .= $diff->getOutput();
                $report['source_changes'][] = $path;
            }
            $fs->remove(Path::join($stage, 'before'));
            $fs->dumpFile(Path::join($stage, 'module-source.patch'), $patch);
            $retained = array_values(array_filter($original['occurrences'], static function ($occurrence) use ($profile): bool {
                foreach ($profile['modules'] as $name) {
                    if (str_starts_with($occurrence[2], "modules/$name/")) {
                        return false;
                    }
                }

                return true;
            }));
            $core = Path::join($stage, 'core');
            TranslationCatalog::write($retained, Path::join($core, 'messages.pot'));
            $coreEntries = TranslationCatalog::entries(Path::join($core, 'messages.pot'));
            Modules::extract($moduleRoot, Path::join($core, 'messages.pot'), $moduleRoot, false);
            $beforeEntries = array_unique(array_column($original['occurrences'], 0));
            $removed = array_values(array_diff($beforeEntries, array_keys($coreEntries)));
            sort($removed, SORT_STRING);
            $shared = $templates = [];
            $modules = Modules::discover($moduleRoot);
            foreach ($modules as $module) {
                $id = $module['id'];
                $template = Modules::template($moduleRoot, $module);
                $templates[$id] = self::readPo($template);
                $moduleEntries = TranslationCatalog::entries($template);
                array_push($shared, ...array_keys(array_intersect_key($moduleEntries, $coreEntries)));
                $fs->copy($template, Modules::template(Path::join($stage, 'crowdin-import'), $module));
                $report['modules'][$id] = ['entries' => count($moduleEntries), 'locales' => []];
                foreach (['LICENSE.translations', 'LICENSE.md'] as $name) {
                    $fs->copy(Path::join($localeRoot, $name), Path::join($module['path'], $name), true);
                }
                $fs->dumpFile(Path::join($module['path'], 'TRANSLATION_ATTRIBUTION.md'), "# Translation attribution\n\nTranslations are CC BY 4.0, copyright the FOSSBilling contributors. Code remains Apache-2.0. See LICENSE.translations and LICENSE.md.\n\nSelected, without rewriting translated text, from {$profile['locale_repository']}/tree/$revision. Translator comments and fuzzy flags are retained where present. Original translation history remains at https://translate.fossbilling.org.\n");
            }
            $shared = array_values(array_unique($shared));
            sort($shared, SORT_STRING);
            $report['core_cleanup'] = ['before' => count($beforeEntries), 'after' => count($coreEntries), 'removed' => $removed, 'shared_retained' => $shared];
            foreach ($donors as $po) {
                $locale = explode('/', $po->getRelativePathname())[0];
                $report['input_sha256']['locale/' . $po->getRelativePathname()] = hash_file('sha256', $po->getPathname());
                if ($locale === $profile['source_locale']) {
                    continue;
                }
                self::checkPo($po->getPathname());
                $donor = self::readPo($po->getPathname());
                $report['languages'][$locale] = ['crowdin_language_id' => self::metadata($donor)['X-Crowdin-Language'] ?? null];
                foreach ($modules as $module) {
                    $id = $module['id'];
                    [$seed, $import, $audit] = self::seed($donor, $templates[$id], $id, $locale, $revision);
                    $relative = basename($module['path']) . "/locale/$locale/LC_MESSAGES/$id.po";
                    foreach (['modules' => $seed, 'crowdin-import' => $import] as $root => $text) {
                        $target = Path::join($stage, $root, $relative);
                        $fs->dumpFile($target, $text);
                        self::checkPo($target);
                    }
                    $report['modules'][$id]['locales'][$locale] = ['counts' => array_count_values(array_column($audit, 'status')), 'entries' => $audit];
                }
            }
            Gettext::build($moduleRoot, $moduleRoot, Path::join($stage, 'compiled'));
            foreach ([['crowdin-core.example.yml', 'core'], ['crowdin-modules.example.yml', 'modules'], ['crowdin-modules.example.yml', 'crowdin-import']] as [$name, $directory]) {
                $config = Path::join(dirname($profilePath), $name);
                $fs->copy($config, Path::join($stage, $directory, 'crowdin.example.yml'));
                $report['input_sha256'][$name] = hash_file('sha256', $config);
            }
            $settings = [
                'core' => ['existing_project_id' => 4, 'desired_display_name' => 'FOSSBilling Core', 'repository' => 'FOSSBilling/locale'],
                'modules' => [
                    'project_id' => null, 'desired_display_name' => 'FOSSBilling Modules', 'repository' => 'FOSSBilling/modules',
                    'source_language' => 'Copy the existing core project source language', 'target_languages_from_donor_headers' => $report['languages'],
                    'automatic_existing_translation_import' => false, 'shared_translation_memory' => 'Assign the existing core translation memory after review',
                ],
            ];
            $fs->dumpFile(Path::join($stage, 'crowdin-project-settings.json'), self::json($settings));
            $fs->dumpFile(Path::join($stage, 'report.json'), self::json($report));
            $fs->dumpFile(Path::join($stage, 'REPORT.md'), self::markdownReport($report));
            $fs->mirror($stage, $output);

            return $report;
        });
    }

    public static function markdownReport(array $report): string
    {
        $verified = $report['locale_git_revision_verified'] ? 'verified Git snapshot' : 'unverified export';
        $lines = [
            '# Branding and Cookieconsent translation migration', '',
            'Prepared offline. No source checkout or live Crowdin project was modified.', '',
            "Locale snapshot: [{$report['locale_revision']}]({$report['locale_repository']}/tree/{$report['locale_revision']}).", '',
            "Locale revision: $verified. Target locale folders: " . count($report['languages']) . '; source English is not imported as a target.', '',
            "Application source SHA-256: `{$report['app_source_sha256']}`.", '',
            '| Module | Source entries | Locales with usable seeded translations |', '| --- | ---: | ---: |',
        ];
        foreach ($report['modules'] as $module => $data) {
            $usable = count(array_filter($data['locales'], static fn ($locale): bool => !empty($locale['counts']['translated'])));
            $lines[] = "| $module | {$data['entries']} | $usable |";
        }
        array_push($lines,
            '', '## Review artifacts', '',
            '- `modules/`: candidate module copies with templates, seeded PO files, and translation licences.',
            '- `module-source.patch`: staged Cookieconsent source changes; the app checkout is unchanged.',
            '- `crowdin-import/`: import copies with fuzzy work excluded, including intentional source-equal translations.',
            '- `compiled/`: validated MO files; no percentage-based language cutoff.',
            '- `core/messages.pot`: candidate core template for after module removal; do not upload it now.',
            '- `crowdin-project-settings.json`: proposed project settings, including donor language IDs.',
            '- `report.json`: per-entry migration disposition and input SHA-256 hashes.', '',
            '## Candidate core cleanup', '',
            "Core entries: {$report['core_cleanup']['before']} before, {$report['core_cleanup']['after']} after.", '',
            'Only strings with no remaining application source reference are removed:', '',
        );
        foreach ($report['core_cleanup']['removed'] as $msgid) {
            $lines[] = '- ' . trim(self::json([$msgid]), "[]\n ");
        }
        array_push($lines, '', 'Shared strings retained in core:', '');
        foreach ($report['core_cleanup']['shared_retained'] as $msgid) {
            $lines[] = '- ' . trim(self::json([$msgid]), "[]\n ");
        }
        array_push($lines, '', '## Decisions before activation', '');
        foreach ($report['pending_release_decisions'] as $decision) {
            $lines[] = "- $decision";
        }
        array_push($lines, '', 'Exact msgid AND plural matches are copied. Missing, differently cased, contextual, obsolete, or changed-plural entries are not guessed. Fuzzy work stays in module seeds for review but is blank in the import copies and omitted from compiled translations. Stored administrator-written cookie notices are unchanged.', '');

        return implode("\n", $lines);
    }
}
