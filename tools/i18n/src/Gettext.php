<?php

declare(strict_types=1);

namespace FOSSBilling\Tools\I18n;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class Gettext
{
    public static function placeholders(string $text, array $expected = []): array
    {
        usort($expected, static fn ($a, $b): int => strlen($b) <=> strlen($a));
        $choices = array_map(static fn ($value): string => preg_quote($value, '~'), $expected);
        $choices[] = '(?<!:):[A-Za-z_][A-Za-z0-9_]*';
        preg_match_all('~' . implode('|', $choices) . '~', $text, $matches);
        $set = array_values(array_unique($matches[0]));
        sort($set, SORT_STRING);

        return $set;
    }

    private static function validate(string $po, string $canonical, string $active, array $expected): bool
    {
        foreach (Catalogue::read($canonical) as $entry) {
            $msgid = $entry['msgid'];
            if (array_key_exists('msgctxt', $entry)) {
                throw new \RuntimeException("$po: message contexts are not supported by the module translation runtime");
            }
            if ($msgid === '') {
                continue;
            }
            if (!array_key_exists($msgid, $expected) || $expected[$msgid] !== ($entry['msgid_plural'] ?? null)) {
                throw new \RuntimeException("$po: stale or mismatched entry '$msgid'; merge the current module template");
            }
        }
        $entries = Catalogue::read($active);
        $header = '';
        foreach ($entries as $entry) {
            if ($entry['msgid'] === '') {
                $header = $entry['msgstr'] ?? '';
            }
        }
        preg_match('/\bnplurals\s*=\s*(\d+)/', $header, $match);
        $pluralCount = (int) ($match[1] ?? 0);
        $usable = false;
        foreach ($entries as $entry) {
            $msgid = $entry['msgid'];
            if ($msgid === '') {
                continue;
            }
            $plural = $entry['msgid_plural'] ?? null;
            $allowed = [self::placeholders($msgid)];
            if ($plural !== null && $plural !== '') {
                $allowed[] = self::placeholders($plural);
                $slots = [];
                foreach ($entry as $key => $value) {
                    if (preg_match('/^msgstr\[(\d+)\]$/D', $key, $slot)) {
                        $slots[(int) $slot[1]] = $value;
                    }
                }
                $forms = array_values($slots);
                $hasTranslations = count(array_filter($forms, static fn ($value): bool => $value !== '')) > 0;
                $indices = array_keys($slots);
                sort($indices, SORT_NUMERIC);
                if ($hasTranslations && ($pluralCount === 0 || $indices !== range(0, $pluralCount - 1))) {
                    throw new \RuntimeException("$po: missing plural forms for '$msgid'; expected $pluralCount slots");
                }
                if ($hasTranslations && in_array('', $forms, true)) {
                    throw new \RuntimeException("$po: incomplete plural translation for '$msgid'; translate every form or leave all forms empty");
                }
            } else {
                $forms = [$entry['msgstr'] ?? ''];
            }
            foreach ($forms as $translation) {
                if ($translation === '') {
                    continue;
                }
                $valid = false;
                foreach ($allowed as $source) {
                    if (self::placeholders($translation, $source) === $source) {
                        $valid = true;

                        break;
                    }
                }
                if (!$valid) {
                    throw new \RuntimeException("$po: placeholders do not match '$msgid'");
                }
                $usable = true;
            }
        }

        return $usable;
    }

    private static function run(array $command, string $po): void
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException($po . ': ' . basename($command[0]) . " failed\n" . trim($process->getErrorOutput()));
        }
    }

    public static function build(string $root, string $templates, ?string $output): int
    {
        $fs = new Filesystem();
        $finder = new ExecutableFinder();
        $commands = [];
        foreach (['msgfmt', 'msgcat', 'msgattrib'] as $name) {
            $commands[$name] = $finder->find($name);
            if ($commands[$name] === null) {
                throw new \RuntimeException('GNU gettext msgfmt, msgcat, and msgattrib are required to validate or compile translations');
            }
        }
        if ($output !== null && $fs->exists($output) && (!is_dir($output) || count((new Finder())->in($output)->depth('== 0')->ignoreDotFiles(false)->ignoreVCS(false)) > 0)) {
            throw new \RuntimeException("compile output must be a new or empty staging directory: $output");
        }
        $modules = Modules::discover($root);

        return TemporaryDirectory::run(static function ($temporary) use ($modules, $templates, $output, $fs, $commands): int {
            $checked = 0;
            $compiled = [];
            foreach ($modules as $module) {
                $expected = Catalogue::entries(Modules::template($templates, $module));
                $localeRoot = Path::join($module['path'], 'locale');
                if (!is_dir($localeRoot)) {
                    continue;
                }
                foreach ((new Finder())->useUnixPaths()->files()->in($localeRoot)->name('*.po')->sortByName() as $po) {
                    $relative = $po->getRelativePathname();
                    if (!preg_match('~\A[a-z]{2,3}(?:_[A-Za-z0-9]+)*/LC_MESSAGES/' . preg_quote($module['id'], '~') . '\.po\z~', $relative)) {
                        throw new \RuntimeException("$po: expected locale/<locale>/LC_MESSAGES/{$module['id']}.po");
                    }
                    $path = $po->getPathname();
                    $mo = Path::join($temporary, "$checked.mo");
                    $canonical = Path::join($temporary, "$checked.po");
                    $filtered = Path::join($temporary, "$checked-filtered.po");
                    $active = Path::join($temporary, "$checked-active.po");
                    self::run([$commands['msgfmt'], '--check', '--check-format', $path, '-o', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'], $path);
                    // Filter the original, before msgcat can drop first-empty fuzzy plural flags.
                    self::run([$commands['msgattrib'], '--no-fuzzy', '--no-obsolete', '--force-po', $path, '-o', $filtered], $path);
                    self::run([$commands['msgcat'], '--no-wrap', '--force-po', '--to-code=UTF-8', $path, '-o', $canonical], $path);
                    self::run([$commands['msgcat'], '--no-wrap', '--force-po', '--to-code=UTF-8', $filtered, '-o', $active], $path);
                    if (self::validate($path, $canonical, $active, $expected)) {
                        // Older gettext preserves input encoding: always compile normalized UTF-8.
                        self::run([$commands['msgfmt'], '--check', '--check-format', $active, '-o', $mo], $path);
                        $compiled[] = [$mo, Path::join(basename($module['path']), 'locale', substr($relative, 0, -3) . '.mo')];
                    }
                    ++$checked;
                }
            }
            // Publish only after every catalogue has passed validation.
            if ($output !== null) {
                foreach ($compiled as [$mo, $relative]) {
                    $fs->copy($mo, Path::join($output, $relative), true);
                }
            }
            echo "Validated $checked PO files; " . count($compiled) . " contain usable translations.\n";

            return 0;
        });
    }
}
