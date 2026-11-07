<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Tools\I18n\Extractor;
use FOSSBilling\Tools\I18n\TranslationCatalog;
use Symfony\Component\Process\Process;

function migrationFixture(TranslationFixture $f): array
{
    $directory = dirname(__DIR__) . '/migrations/branding-cookieconsent';
    $profile = json_decode($f->fs->readFile("$directory/profile.json"), true, flags: JSON_THROW_ON_ERROR);
    $profile['locale_revision'] = str_repeat('a', 40);
    $profilePath = $f->write('profile/profile.json', json_encode($profile, JSON_THROW_ON_ERROR));
    foreach (['crowdin-core.example.yml', 'crowdin-modules.example.yml'] as $name) {
        $f->fs->copy("$directory/$name", "$f->root/profile/$name");
    }
    foreach ($profile['modules'] as $module) {
        $f->write("app/src/modules/$module/manifest.json", json_encode(['manifest_version' => 1, 'type' => 'module', 'id' => strtolower($module)], JSON_THROW_ON_ERROR));
    }
    $f->write('app/src/modules/Branding/templates/page.twig', "{{ 'Powered by'|trans }} {{ 'FOSSBilling'|trans }}");
    $f->write('app/src/modules/Cookieconsent/Controller/Admin.php', "<?php __trans('Cookie Consent');");
    $f->write('app/src/modules/Cookieconsent/Service.php', '<?php return ' . $profile['source_changes'][1]['before'] . ';');
    $f->write('app/src/modules/Cookieconsent/templates/admin/mod_cookieconsent_index.html.twig', "{{ 'Cookie Consent Settings'|trans }} {{ 'Edit Cookie Consent Message Text'|trans }} {{ 'Update'|trans }} {% if custom %}Custom" . $profile['source_changes'][2]['before']);
    $f->write('app/src/core/Shared.php', "<?php __trans('Update'); __trans('Core only');");
    $locale = "$f->root/locale";
    TranslationCatalog::write(Extractor::extract("$f->root/app/src")['occurrences'], "$locale/messages.pot");
    foreach (['LICENSE.md', 'LICENSE.translations'] as $name) {
        $f->write("locale/$name", "Fixture licence\n");
    }
    $header = <<<'PO_WRAP'
        # Original header comment
        msgid ""
        msgstr ""
        "Project-Id-Version: Core\n"
        "POT-Creation-Date: 2026-01-01 00:00+0000\n"
        "PO-Revision-Date: 2026-01-01 00:00+0000\n"
        "Last-Translator: Original translator <test@example.com>\n"
        "Language-Team: French\n"
        "Language: fr_FR\n"
        "MIME-Version: 1.0\n"
        "Content-Type: text/plain; charset=UTF-8\n"
        "Content-Transfer-Encoding: 8bit\n"
        "Plural-Forms: nplurals=3; plural=(n == 1 ? 0 : n == 2 ? 1 : 2);\n"
        "X-Crowdin-Language: fr\n"
        "X-Crowdin-Project-ID: 4\n"

        PO_WRAP;
    $po = $f->write('locale/fr_FR/LC_MESSAGES/messages.po', $header . <<<'PO_WRAP'

        # Translator comment
        #. Extracted comment
        #: old/source.php:1
        msgid "Powered by"
        msgstr "Propulsé par"

        msgid "FOSSBilling"
        msgstr "FOSSBilling"

        msgid "Cookie Consent"
        msgstr "Consentement aux cookies"

        msgid "Cookie Consent Settings"
        msgstr "Paramètres des cookies"

        #, fuzzy, php-format
        msgid "Edit Cookie Consent Message Text"
        msgstr "A revoir"

        msgid "Update"
        msgstr "Mettre à jour"

        PO_WRAP);
    $f->write('locale/en_US/LC_MESSAGES/messages.po', $header);

    return ['app' => "$f->root/app", 'locale' => $locale, 'profile' => $profilePath, 'output' => "$f->root/prepared", 'po' => $po];
}

function prepareMigration(TranslationFixture $f, array $paths, ?string $output = null): Process
{
    return $f->cli(['prepare-migration', $paths['app'], $paths['locale'], $output ?? $paths['output'], '--profile', $paths['profile']]);
}

test('prepares offline seeds, import copies, compiled translations and core cleanup without changing inputs', function (): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    $app = $f->snapshot($p['app']);
    $locale = $f->snapshot($p['locale']);
    $result = prepareMigration($f, $p);
    expect($result->getExitCode())->toBe(0, $result->getErrorOutput());
    expect($f->snapshot($p['app']))->toBe($app)->and($f->snapshot($p['locale']))->toBe($locale);
    $report = json_decode($f->fs->readFile($p['output'] . '/report.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['locale_git_revision_verified'])->toBeFalse()
        ->and($report['languages'])->toBe(['fr_FR' => ['crowdin_language_id' => 'fr']])
        ->and($report['core_cleanup']['shared_retained'])->toContain('Update')
        ->and($report['core_cleanup']['removed'])->toContain('Cookie Consent')->not->toContain('Update')
        ->and(TranslationCatalog::entries($p['output'] . '/core/messages.pot'))->toBe(['Core only' => null, 'Update' => null]);
    expect($f->fs->readFile($p['output'] . '/module-source.patch'))->toContain("from: 'cookieconsent'");
    $seed = $f->fs->readFile($p['output'] . '/modules/Branding/locale/fr_FR/LC_MESSAGES/branding.po');
    expect($seed)->toContain('# Translator comment', '#. Extracted comment', '# Original header comment', '#: Branding/templates/page.twig:1', 'msgstr "FOSSBilling"', 'Original translator <test@example.com>', 'Language-Team: French', 'PO-Revision-Date: 2026-01-01', 'X-FOSSBilling-Seed-Revision: ' . str_repeat('a', 40))
        ->not->toContain('old/source.php', 'X-Crowdin-', '#, fuzzy');
    $cookie = $p['output'] . '/modules/Cookieconsent';
    expect($f->fs->readFile("$cookie/locale/fr_FR/LC_MESSAGES/cookieconsent.po"))->toContain('A revoir', '#, fuzzy, php-format')
        ->and($f->fs->readFile($p['output'] . '/crowdin-import/Cookieconsent/locale/fr_FR/LC_MESSAGES/cookieconsent.po'))->not->toContain('A revoir')
        ->and($f->fs->exists("$cookie/locale/en_US"))->toBeFalse()
        ->and($f->fs->exists("$cookie/LICENSE.translations"))->toBeTrue()
        ->and($f->fs->readFile("$cookie/TRANSLATION_ATTRIBUTION.md"))->toContain('FOSSBilling contributors');
    $mo = $f->readMo($p['output'] . '/compiled/Cookieconsent/locale/fr_FR/LC_MESSAGES/cookieconsent.mo');
    expect($mo['Cookie Consent'])->toBe('Consentement aux cookies')->and($mo)->not->toHaveKey('Edit Cookie Consent Message Text');
    $patch = new Process(['git', 'apply', '--check', '--directory=src/modules', $p['output'] . '/module-source.patch'], $p['app']);
    $patch->run();
    expect($patch->getExitCode())->toBe(0, $patch->getErrorOutput());
    $settings = json_decode($f->fs->readFile($p['output'] . '/crowdin-project-settings.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($settings['core']['existing_project_id'])->toBe(4)->and($settings['modules']['project_id'])->toBeNull();
    $config = $f->fs->readFile($p['output'] . '/modules/crowdin.example.yml');
    expect($config)->toContain('project_id_env: CROWDIN_MODULES_PROJECT_ID')->not->toContain('project_id: 4');
    foreach (['Branding', 'Cookieconsent'] as $name) {
        $id = strtolower($name);
        expect($config)->toContain("source: /$name/locale/$id.pot", "translation: /$name/locale/%locale_with_underscore%/LC_MESSAGES/$id.po")
            ->and($f->fs->exists($p['output'] . "/crowdin-import/$name/locale/fr_FR/LC_MESSAGES/$id.po"))->toBeTrue();
    }
});

test('seeds only exact context-free active message and plural pairs', function (): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    $f->write('app/src/modules/Branding/Extra.php', "<?php __pluralTrans('One', 'Many', \$n, from: 'branding'); __pluralTrans('Item', 'Items', \$n, from: 'branding'); __trans('Lowercase', from: 'branding'); __trans('Obsolete', from: 'branding'); __trans('Context', from: 'branding');");
    $f->fs->appendToFile($p['po'], <<<'PO_WRAP'

        msgid "One"
        msgid_plural "Wrong plural"
        msgstr[0] "Wrong 0"
        msgstr[1] "Wrong 1"
        msgstr[2] "Wrong 2"

        msgid "Item"
        msgid_plural "Items"
        msgstr[0] "One item"
        msgstr[1] "Two items"
        msgstr[2] "Many items"

        msgid "lowercase"
        msgstr "No casing guess"

        #~ msgid "Obsolete"
        #~ msgstr "Do not revive"

        msgctxt "a different context"
        msgid "Context"
        msgstr "Do not copy"

        PO_WRAP);
    $result = prepareMigration($f, $p);
    expect($result->getExitCode())->toBe(0, $result->getErrorOutput());
    $seed = $f->fs->readFile($p['output'] . '/modules/Branding/locale/fr_FR/LC_MESSAGES/branding.po');
    expect($seed)->toContain('msgstr[2] ""', 'msgstr[2] "Many items"')->not->toContain('Wrong 0', 'No casing guess', 'Do not revive', 'Do not copy');
    $report = json_decode($f->fs->readFile($p['output'] . '/report.json'), true, flags: JSON_THROW_ON_ERROR);
    $statuses = array_column($report['modules']['branding']['locales']['fr_FR']['entries'], 'status', 'msgid');
    expect($statuses['One'])->toBe('plural_mismatch')->and($statuses['Item'])->toBe('translated')->and($statuses['Lowercase'])->toBe('missing');
});

test('preserves first-empty fuzzy plural flags and omits fuzzy imports and compiled entries', function (): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    $f->write('app/src/modules/Branding/Extra.php', "<?php __pluralTrans('Item', 'Items', \$n, from: 'branding');");
    $f->fs->appendToFile($p['po'], "\n#, fuzzy\nmsgid \"Item\"\nmsgid_plural \"Items\"\nmsgstr[0] \"\"\nmsgstr[1] \"Two items\"\nmsgstr[2] \"Many items\"\n");
    $result = prepareMigration($f, $p);
    expect($result->getExitCode())->toBe(0, $result->getErrorOutput());
    expect($f->fs->readFile($p['output'] . '/modules/Branding/locale/fr_FR/LC_MESSAGES/branding.po'))->toContain('#, fuzzy', 'msgstr[2] "Many items"')
        ->and($f->fs->readFile($p['output'] . '/crowdin-import/Branding/locale/fr_FR/LC_MESSAGES/branding.po'))->not->toContain('Many items')
        ->and($f->readMo($p['output'] . '/compiled/Branding/locale/fr_FR/LC_MESSAGES/branding.mo'))->not->toHaveKey("Item\0Items");
});

test('rejects non-fuzzy partial plurals using the shared build validator before publishing', function (): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    $f->write('app/src/modules/Branding/Extra.php', "<?php __pluralTrans('Item', 'Items', \$n, from: 'branding');");
    $f->fs->appendToFile($p['po'], "\nmsgid \"Item\"\nmsgid_plural \"Items\"\nmsgstr[0] \"\"\nmsgstr[1] \"Two items\"\nmsgstr[2] \"Many items\"\n");
    $result = prepareMigration($f, $p);
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('incomplete plural translation')
        ->and($f->fs->exists($p['output']))->toBeFalse();
});

test('rejects drift, invalid revisions, invalid donors and nonempty output without partial writes', function (string $case, string $error): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    if ($case === 'drift') {
        $f->write('app/src/modules/Cookieconsent/Controller/Admin.php', '<?php __trans("Changed");');
    } elseif ($case === 'revision') {
        $profile = json_decode($f->fs->readFile($p['profile']), true, flags: JSON_THROW_ON_ERROR);
        $profile['locale_revision'] = 'main';
        $f->fs->dumpFile($p['profile'], json_encode($profile, JSON_THROW_ON_ERROR));
    } elseif ($case === 'donor') {
        $f->fs->dumpFile($p['po'], 'not valid PO');
    } else {
        $f->fs->dumpFile($p['output'] . '/keep', 'existing data');
    }
    $result = prepareMigration($f, $p);
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain($error);
    if ($case === 'output') {
        expect($f->snapshot($p['output']))->toBe(['keep' => 'existing data']);
    } else {
        expect($f->fs->exists($p['output']))->toBeFalse();
    }
})->with([
    ['drift', 'source drift'], ['revision', 'full locale commit SHA'], ['donor', 'msgfmt'], ['output', 'new or empty review directory'],
]);

test('rejects output within either input including through a symlink', function (string $input, bool $symlink): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    $root = $input === 'app' ? $p['app'] . '/src' : $p['locale'];
    if ($symlink) {
        $f->fs->symlink($root, "$f->root/link");
        $root = "$f->root/link";
    }
    $output = "$root/nested/prepared";
    $result = prepareMigration($f, $p, $output);
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('must not be inside')
        ->and($f->fs->exists($output))->toBeFalse();
})->with([['app', false], ['locale', false], ['app', true], ['locale', true]]);

test('verifies the pinned Git revision and rejects changed, untracked or ignored translation inputs', function (string $change): void {
    $f = $this->fixture;
    $p = migrationFixture($f);
    $git = static fn (array $args): string => (new Process(['git', ...$args], $p['locale']))->mustRun()->getOutput();
    $f->write('locale/.gitignore', "de_DE/\n");
    $git(['init', '--quiet']);
    $git(['add', '.']);
    $git(['-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.com', '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '--quiet', '-m', 'Locale fixture']);
    expect(prepareMigration($f, $p)->getErrorOutput())->toContain('does not match pinned revision');
    $profile = json_decode($f->fs->readFile($p['profile']), true, flags: JSON_THROW_ON_ERROR);
    $profile['locale_revision'] = trim($git(['rev-parse', 'HEAD']));
    $f->fs->dumpFile($p['profile'], json_encode($profile, JSON_THROW_ON_ERROR));
    $result = prepareMigration($f, $p);
    expect($result->getExitCode())->toBe(0, $result->getErrorOutput());
    $report = json_decode($f->fs->readFile($p['output'] . '/report.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($report['locale_git_revision_verified'])->toBeTrue();
    if ($change === 'changed') {
        $f->fs->appendToFile($p['po'], "\n# Changed after snapshot\n");
    } else {
        $locale = $change === 'ignored' ? 'de_DE' : 'es_ES';
        $f->fs->copy($p['po'], $p['locale'] . "/$locale/LC_MESSAGES/messages.po");
    }
    $result = prepareMigration($f, $p, "$f->root/dirty-output");
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('clean checkout')
        ->and($f->fs->exists("$f->root/dirty-output"))->toBeFalse();
})->with(['changed', 'untracked', 'ignored']);
