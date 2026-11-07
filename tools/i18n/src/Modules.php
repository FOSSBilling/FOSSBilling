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

final class Modules
{
    /** @return list<array{id: string, path: string}> */
    public static function discover(string $root): array
    {
        $fs = new Filesystem();
        if (!is_dir($root)) {
            throw new \RuntimeException("modules directory does not exist: $root");
        }
        $directories = $fs->exists(Path::join($root, 'manifest.json')) ? [$root] : array_map(static fn ($file) => $file->getPathname(), iterator_to_array((new Finder())->directories()->in($root)->depth('== 0')->sortByName(), false));
        $modules = [];
        foreach ($directories as $directory) {
            $manifest = Path::join($directory, 'manifest.json');
            if (!$fs->exists($manifest)) {
                foreach (['Service.php', 'templates', 'Api', 'Controller'] as $name) {
                    if ($fs->exists(Path::join($directory, $name))) {
                        throw new \RuntimeException("missing module manifest: $manifest");
                    }
                }

                continue;
            }
            $data = json_decode($fs->readFile($manifest), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['manifest_version'] ?? null) !== 1 || ($data['type'] ?? null) !== 'module') {
                throw new \RuntimeException("$manifest: expected a v1 module manifest");
            }
            $id = $data['id'] ?? null;
            if (!is_string($id) || !preg_match('/\A[a-z][a-z0-9_-]*\z/', $id) || $id === 'core') {
                throw new \RuntimeException("$manifest: invalid or reserved module ID");
            }
            if (basename($directory) !== ucfirst($id)) {
                throw new \RuntimeException("$manifest: directory must match Ucfirst(id)");
            }
            $modules[] = ['id' => $id, 'path' => $directory];
        }
        if (!$modules) {
            throw new \RuntimeException("no module manifests found in $root");
        }

        return $modules;
    }

    public static function template(string $root, array $module): string
    {
        return Path::join($root, basename($module['path']), 'locale', $module['id'] . '.pot');
    }

    public static function extract(string $root, string $corePot, string $output, bool $check): int
    {
        $fs = new Filesystem();
        $modules = self::discover($root);
        $core = TranslationCatalog::entries($corePot);
        $sources = array_fill_keys(array_column($modules, 'id'), []);
        $errors = [];
        foreach ($modules as $module) {
            $result = Extractor::extract($module['path'], $module['id']);
            foreach ($result['source_errors'] as $error) {
                $errors[] = basename($module['path']) . '/' . $error;
            }
            foreach ($result['occurrences'] as $occurrence) {
                $occurrence[2] = basename($module['path']) . '/' . $occurrence[2];
                [$msgid, $plural, $ref, $from] = $occurrence;
                if ($from === 'core') {
                    if (!array_key_exists($msgid, $core) || $core[$msgid] !== $plural) {
                        $errors[] = "$ref: '$msgid' is not a matching shared core entry; use the module's from argument";
                    }
                } elseif (array_key_exists($from, $sources)) {
                    $sources[$from][] = $occurrence;
                } else {
                    $errors[] = "$ref: unknown module translation source '$from'";
                }
            }
        }
        $known = array_map(static fn ($module): string => self::template($output, $module), $modules);
        if (is_dir($output)) {
            $single = $fs->exists(Path::join($root, 'manifest.json'));
            foreach ((new Finder())->useUnixPaths()->files()->in($output)->name('*.pot')->sortByName() as $file) {
                $relative = $file->getRelativePathname();
                if (!preg_match('~^([^/]+)/locale/[^/]+\.pot$~', $relative, $matches) || ($single && $matches[1] !== basename($root))) {
                    continue;
                }
                if (!in_array(Path::join($output, $relative), $known, true)) {
                    $errors[] = "$file: template has no corresponding module; review its removal manually";
                }
            }
        }
        if ($errors) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        return TemporaryDirectory::run(static function ($temporary) use ($modules, $sources, $output, $check, $fs): int {
            $changed = [];
            foreach ($modules as $module) {
                $generated = Path::join($temporary, $module['id'] . '.pot');
                $count = TranslationCatalog::write($sources[$module['id']], $generated, 'FOSSBilling module ' . $module['id'], true);
                $target = self::template($output, $module);
                if (!$fs->exists($target) || TranslationCatalog::normalized($target) !== TranslationCatalog::normalized($generated)) {
                    $changed[] = [$generated, $target];
                    echo $module['id'] . ": $count entries; " . ($check ? 'needs regeneration' : 'updated') . "\n";
                    if ($fs->exists($target)) {
                        echo TranslationCatalog::diff($target, $generated);
                    }
                } else {
                    echo $module['id'] . ": $count entries; unchanged\n";
                }
            }
            if ($check) {
                return (int) (bool) $changed;
            }
            foreach ($changed as [$generated, $target]) {
                $fs->copy($generated, $target, true);
            }

            return 0;
        });
    }
}
