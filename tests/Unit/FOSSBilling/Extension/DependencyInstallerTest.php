<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Extension\DependencyInstaller;
use FOSSBilling\Extension\ExtensionType;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

function installer(): DependencyInstaller
{
    return new DependencyInstaller(new Filesystem());
}

test('an extension without a composer.json has nothing to install', function (): void {
    $dir = Path::join(sys_get_temp_dir(), 'fossbilling_deps_' . uniqid());
    (new Filesystem())->mkdir($dir);

    expect(installer()->hasDependencies($dir))->toBeFalse()
        ->and(installer()->install($dir))->toBe('');
});

test('dependencies are reported installed only once vendored', function (): void {
    $dir = Path::join(sys_get_temp_dir(), 'fossbilling_deps_' . uniqid());
    $filesystem = new Filesystem();
    $filesystem->mkdir($dir);
    $filesystem->dumpFile(Path::join($dir, 'composer.json'), '{"require":{}}');

    expect(installer()->hasDependencies($dir))->toBeTrue()
        ->and(installer()->isInstalled($dir))->toBeFalse();

    $filesystem->dumpFile(Path::join($dir, 'vendor', 'autoload.php'), '<?php');

    expect(installer()->isInstalled($dir))->toBeTrue();
});

test('the suite bootstrap prepares every bundled extension that declares dependencies', function (): void {
    $withDependencies = [];

    foreach (ExtensionType::cases() as $type) {
        foreach (new DirectoryIterator($type->directory()) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            if (installer()->hasDependencies($entry->getPathname())) {
                $withDependencies[] = $type->value . '/' . $entry->getFilename();
                expect(installer()->isInstalled($entry->getPathname()))
                    ->toBeTrue("{$type->value}/{$entry->getFilename()} has no vendor directory; run composer extensions:install");
            }
        }
    }

    expect($withDependencies)->not->toBeEmpty();
});

test('an extension never vendors a package core already provides', function (): void {
    // The whole point of declaring core's packages as replaced: PHP cannot hold
    // two versions of a class, so a second copy would be silently unused.
    $corePackages = [];
    foreach (json_decode((string) file_get_contents(Path::join(PATH_VENDOR, 'composer', 'installed.json')), true)['packages'] as $package) {
        $corePackages[$package['name']] = true;
    }

    foreach (ExtensionType::cases() as $type) {
        foreach (new DirectoryIterator($type->directory()) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $installed = Path::join($entry->getPathname(), 'vendor', 'composer', 'installed.json');
            if (!is_file($installed)) {
                continue;
            }

            foreach (json_decode((string) file_get_contents($installed), true)['packages'] as $package) {
                expect($corePackages)->not->toHaveKey(
                    $package['name'],
                    sprintf('%s/%s vendors %s, which core already provides', $type->value, $entry->getFilename(), $package['name'])
                );
            }
        }
    }
});

test('a package two extensions vendor at different versions is reported as a conflict', function (): void {
    $filesystem = new Filesystem();
    $base = Path::join(sys_get_temp_dir(), 'fossbilling_conflict_' . uniqid());

    $write = static function (string $dir, string $version) use ($filesystem): string {
        $filesystem->dumpFile(
            Path::join($dir, 'vendor', 'composer', 'installed.json'),
            json_encode(['packages' => [
                ['name' => 'acme/shared', 'version' => $version],
                ['name' => 'acme/private', 'version' => '1.0.0'],
            ]])
        );

        return $dir;
    };

    $ours = $write(Path::join($base, 'ours'), 'v2.0.0');
    $theirs = $write(Path::join($base, 'theirs'), 'v1.0.0');

    $conflicts = installer()->findConflicts($ours, ['gateway/Theirs' => $theirs]);

    expect($conflicts)->toHaveKey('acme/shared')
        ->and($conflicts['acme/shared']['version'])->toBe('2.0.0')
        ->and($conflicts['acme/shared']['conflicts'])->toBe(['gateway/Theirs' => '1.0.0'])
        // A package held at the same version by both is not a conflict.
        ->and($conflicts)->not->toHaveKey('acme/private');
});

test('the bundled extensions do not conflict with each other', function (): void {
    $directories = [];
    foreach (ExtensionType::cases() as $type) {
        foreach (new DirectoryIterator($type->directory()) as $entry) {
            if (!$entry->isDot() && $entry->isDir()) {
                $directories[$type->value . '/' . $entry->getFilename()] = $entry->getPathname();
            }
        }
    }

    foreach ($directories as $label => $directory) {
        $others = array_diff_key($directories, [$label => null]);
        expect(installer()->findConflicts($directory, $others))
            ->toBe([], "{$label} vendors a package another bundled extension holds at a different version");
    }
});
