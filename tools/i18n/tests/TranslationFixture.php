<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Tools\I18n\TranslationCatalogue;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final readonly class TranslationFixture
{
    public string $root;
    public string $modules;
    public string $templates;
    public string $build;
    public string $core;
    public Filesystem $fs;

    public function __construct()
    {
        $this->fs = new Filesystem();
        $this->root = Path::join(sys_get_temp_dir(), 'fossbilling-i18n-test-' . bin2hex(random_bytes(10)));
        $this->fs->mkdir($this->root);
        $this->modules = Path::join($this->root, 'modules');
        $this->templates = Path::join($this->root, 'templates');
        $this->build = Path::join($this->root, 'build');
        $this->core = Path::join($this->root, 'core.pot');
    }

    public function remove(): void
    {
        $this->fs->remove($this->root);
    }

    public function write(string $relative, string $content): string
    {
        $path = Path::join($this->root, $relative);
        $this->fs->dumpFile($path, $content);

        return $path;
    }

    public function snapshot(string $root): array
    {
        $result = [];
        foreach ((new Finder())->files()->in($root)->sortByName() as $file) {
            $result[$file->getRelativePathname()] = $file->getContents();
        }

        return $result;
    }

    public function setupModules(): void
    {
        TranslationCatalogue::write([['Save', null, 'core.php:1', 'core'], ['Shared item', 'Shared items', 'core.php:2', 'core']], $this->core);
        foreach (['Example', 'Other'] as $module) {
            $this->write("modules/$module/manifest.json", json_encode(['manifest_version' => 1, 'type' => 'module', 'id' => strtolower($module)], JSON_THROW_ON_ERROR));
        }
        $this->write('modules/Example/templates/page.twig', "{{ 'Hello :name'|trans }} {{ 'Save'|trans(from: 'core') }}");
        $this->write('modules/Example/Service.php', "<?php __trans('Save'); __pluralTrans(':n item', ':n items', \$n, from: 'example');");
        $this->write('modules/Other/templates/page.twig', "{{ 'Other'|trans }} {{ 'Hello :name'|trans }}");
    }

    public function cli(array $arguments, array $env = []): Process
    {
        $repo = dirname(__DIR__, 3);
        $process = new Process([PHP_BINARY, Path::join($repo, 'tools/i18n/console.php'), ...$arguments], $repo, ['COLUMNS' => '1000', ...$env]);
        $process->run();

        return $process;
    }

    public function extract(array $extra = [], ?string $root = null): Process
    {
        return $this->cli(['extract-modules', $root ?? $this->modules, $this->core, $this->templates, ...$extra]);
    }

    public function translate(string $command, array $env = []): Process
    {
        $arguments = [$command, $this->modules, $this->templates];
        if ($command === 'compile') {
            $arguments[] = $this->build;
        }

        return $this->cli($arguments, $env);
    }

    public function template(string $module = 'Example'): string
    {
        return Path::join($this->templates, $module, 'locale', strtolower($module) . '.pot');
    }

    public function mo(): string
    {
        return Path::join($this->build, 'Example/locale/fr_FR/LC_MESSAGES/example.mo');
    }

    public function po(array $entries, string $locale = 'fr_FR', string $rule = 'nplurals=2; plural=(n > 1);'): string
    {
        $header = ['Project-Id-Version: FOSSBilling module example', 'Report-Msgid-Bugs-To: info@fossbilling.org', 'POT-Creation-Date: 2026-01-01 00:00+0000', 'PO-Revision-Date: 2026-01-01 00:00+0000', 'Last-Translator: Test <test@example.com>', 'Language-Team: Test', "Language: $locale", 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit', "Plural-Forms: $rule"];
        $text = "msgid \"\"\nmsgstr \"\"\n";
        foreach ($header as $line) {
            $text .= '"' . TranslationCatalogue::escape($line . "\n") . "\"\n";
        }
        foreach ($entries as [$msgid, $plural, $forms, $fuzzy]) {
            $text .= "\n" . ($fuzzy ? "#, fuzzy\n" : '') . 'msgid "' . TranslationCatalogue::escape($msgid) . "\"\n";
            if ($plural !== null) {
                $text .= 'msgid_plural "' . TranslationCatalogue::escape($plural) . "\"\n";
                foreach ($forms as $i => $form) {
                    $text .= "msgstr[$i] \"" . TranslationCatalogue::escape($form) . "\"\n";
                }
            } else {
                $text .= 'msgstr "' . TranslationCatalogue::escape($forms[0]) . "\"\n";
            }
        }

        return $this->write("modules/Example/locale/$locale/LC_MESSAGES/example.po", $text);
    }

    /** Read raw MO bytes without charset conversion, so UTF-8 regressions cannot be hidden. */
    public function readMo(string $path): array
    {
        $data = $this->fs->readFile($path);
        $format = str_starts_with($data, "\xde\x12\x04\x95") ? 'V' : 'N';
        $header = unpack($format . 'magic/' . $format . 'revision/' . $format . 'count/' . $format . 'originals/' . $format . 'translations', substr($data, 0, 20));
        if ($header['magic'] !== 0x950412DE) {
            throw new RuntimeException('Invalid MO magic');
        }
        $entries = [];
        for ($i = 0; $i < $header['count']; ++$i) {
            $original = unpack($format . 'length/' . $format . 'offset', substr($data, $header['originals'] + $i * 8, 8));
            $translation = unpack($format . 'length/' . $format . 'offset', substr($data, $header['translations'] + $i * 8, 8));
            $entries[substr($data, $original['offset'], $original['length'])] = substr($data, $translation['offset'], $translation['length']);
        }

        return $entries;
    }
}
