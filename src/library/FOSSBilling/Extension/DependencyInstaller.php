<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension;

use Composer\Console\Application;
use FOSSBilling\InformationException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Installs an extension's own Composer dependencies into its bundle.
 *
 * Composer runs inside this process rather than as a subprocess, because the
 * hosting FOSSBilling most often runs on has no shell. Every package core
 * already ships is declared as replaced, so an extension can never vendor a
 * second copy of something core provides: PHP cannot hold two versions of a
 * class, and the copy core loaded would win in a way that is invisible until
 * it breaks. Declaring them makes Composer refuse an incompatible extension
 * up front, with a resolver error naming the conflict.
 */
final class DependencyInstaller
{
    /**
     * The generated manifest Composer is actually run against. The extension's
     * own composer.json is the author's file and is never written to.
     */
    private const string EFFECTIVE_MANIFEST = 'composer-effective.json';

    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Whether the extension declares any Composer dependencies of its own.
     */
    public function hasDependencies(string $directory): bool
    {
        return $this->filesystem->exists(Path::join($directory, 'composer.json'));
    }

    /**
     * Whether those dependencies are present on the disk.
     */
    public function isInstalled(string $directory): bool
    {
        return $this->filesystem->exists(Path::join($directory, 'vendor', 'autoload.php'));
    }

    /**
     * Resolve and install the extension's dependencies.
     *
     * @return string Composer's output, for logging or display
     *
     * @throws InformationException if the environment cannot support it, or resolution fails
     */
    public function install(string $directory): string
    {
        if (!$this->hasDependencies($directory)) {
            return '';
        }

        $this->assertEnvironmentSupported($directory);
        $this->writeEffectiveManifest($directory);

        $previousCwd = getcwd();
        $previousComposer = getenv('COMPOSER');

        // Composer resolves relative to the working directory and the COMPOSER
        // env var names the manifest within it.
        chdir($directory);
        putenv('COMPOSER=' . self::EFFECTIVE_MANIFEST);
        putenv('COMPOSER_HOME=' . $this->composerHome());
        putenv('COMPOSER_NO_INTERACTION=1');
        // Composer restarts itself through a subprocess to drop Xdebug, which
        // it cannot do without a shell.
        putenv('COMPOSER_ALLOW_XDEBUG=1');

        $output = new BufferedOutput();

        try {
            $application = new Application();
            $application->setAutoExit(false);

            $exitCode = $application->run(new ArrayInput([
                'command' => 'update',
                '--no-scripts' => true,
                '--no-plugins' => true,
                '--no-progress' => true,
                '--no-audit' => true,
                '--prefer-dist' => true,
                '--optimize-autoloader' => true,
            ]), $output);
        } catch (\Throwable $e) {
            throw new InformationException('Installing the dependencies failed: :error', [':error' => $e->getMessage()]);
        } finally {
            chdir($previousCwd === false ? PATH_ROOT : $previousCwd);
            putenv($previousComposer === false ? 'COMPOSER' : 'COMPOSER=' . $previousComposer);
        }

        $log = $output->fetch();

        if ($exitCode !== 0) {
            throw new InformationException("The dependencies could not be installed:\n:output", [':output' => $log]);
        }

        return $log;
    }

    /**
     * The packages an extension has vendored, and at which versions.
     *
     * @return array<string, string>
     */
    public function installedPackages(string $directory): array
    {
        $file = Path::join($directory, 'vendor', 'composer', 'installed.json');
        if (!$this->filesystem->exists($file)) {
            return [];
        }

        $packages = [];
        foreach ($this->readJson($file)['packages'] ?? [] as $package) {
            if (isset($package['name'], $package['version'])) {
                $packages[(string) $package['name']] = ltrim((string) $package['version'], 'v');
            }
        }

        return $packages;
    }

    /**
     * Packages this extension vendors at a different version to another extension.
     *
     * Only one version of a class can exist in a PHP process, so where two
     * extensions disagree the one loaded first wins and the other silently runs
     * against a version it did not ask for. That cannot be resolved from here,
     * but it can be reported rather than left to surface as a strange bug.
     *
     * @param array<string, string> $otherDirectories other extensions, keyed by label
     *
     * @return array<string, array{version: string, conflicts: array<string, string>}>
     */
    public function findConflicts(string $directory, array $otherDirectories): array
    {
        $ours = $this->installedPackages($directory);
        if ($ours === []) {
            return [];
        }

        $conflicts = [];
        foreach ($otherDirectories as $label => $otherDirectory) {
            foreach ($this->installedPackages($otherDirectory) as $package => $version) {
                if (isset($ours[$package]) && $ours[$package] !== $version) {
                    $conflicts[$package]['version'] = $ours[$package];
                    $conflicts[$package]['conflicts'][$label] = $version;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Fail before touching the network when the environment cannot finish the job.
     */
    private function assertEnvironmentSupported(string $directory): void
    {
        if (!class_exists(Application::class)) {
            throw new InformationException('Composer is not available, so extension dependencies cannot be installed automatically.');
        }

        // Without a shell Composer unpacks archives through ext-zip.
        if (!function_exists('proc_open') && !extension_loaded('zip')) {
            throw new InformationException('Installing extension dependencies needs either the zip extension or the proc_open function, and neither is available.');
        }

        if (!is_writable($directory)) {
            throw new InformationException('The extension directory :dir is not writable.', [':dir' => $directory]);
        }
    }

    private function composerHome(): string
    {
        $home = Path::join(PATH_CACHE, 'composer');
        $this->filesystem->mkdir($home);

        return $home;
    }

    /**
     * Merge the extension's requirements with everything core already provides.
     */
    private function writeEffectiveManifest(string $directory): void
    {
        $authored = $this->readJson(Path::join($directory, 'composer.json'));

        $effective = [
            'require' => $authored['require'] ?? [],
            'replace' => $this->corePackages(),
            'config' => [
                'vendor-dir' => 'vendor',
                'allow-plugins' => false,
            ],
            'prefer-stable' => true,
        ];

        if (isset($authored['autoload'])) {
            $effective['autoload'] = $authored['autoload'];
        }

        $this->filesystem->dumpFile(
            Path::join($directory, self::EFFECTIVE_MANIFEST),
            json_encode($effective, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Every package core has installed, at the exact version it installed.
     *
     * Read from the disk rather than through Composer\InstalledVersions: running
     * Composer in this process reloads that registry with whatever was just
     * installed, so after preparing one extension it reports that extension's
     * packages as present. The next extension would then be told core provides
     * them, and be unable to install its own.
     *
     * @return array<string, string>
     */
    private function corePackages(): array
    {
        $installed = $this->readJson(Path::join(PATH_VENDOR, 'composer', 'installed.json'));

        $packages = [];
        foreach ($installed['packages'] ?? [] as $package) {
            if (isset($package['name'], $package['version'])) {
                $packages[(string) $package['name']] = ltrim((string) $package['version'], 'v');
            }
        }

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new InformationException('Could not read :file.', [':file' => $file]);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InformationException('The file :file is not valid JSON: :error', [':file' => $file, ':error' => $e->getMessage()]);
        }

        return is_array($data) ? $data : [];
    }
}
