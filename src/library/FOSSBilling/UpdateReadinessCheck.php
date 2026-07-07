<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Pre-flight check that verifies the web server user has sufficient
 * filesystem permissions to perform a FOSSBilling update.
 *
 * Unlike {@see Requirements} which is geared at first-time installation,
 * this class is shaped around what an update actually does: it writes
 * a state file, flips config.php, downloads an archive, and extracts it
 * over the live tree.
 */
final readonly class UpdateReadinessCheck
{
    private Filesystem $filesystem;
    private string $configDir;
    private string $installDir;

    public function __construct(
        private string $pathRoot,
        private string $pathData,
        string $pathConfig,
    ) {
        $this->filesystem = new Filesystem();
        $this->configDir = Path::getDirectory($pathConfig);
        $this->installDir = Path::join($this->pathRoot, 'install');
    }

    /**
     * Run all checks. Returns a structured result safe to expose to the
     * admin UI and to serialize as JSON.
     *
     * @return array{can_update: bool, issues: list<array{path: string, type: string, reason: string, message: string}>}
     */
    public function check(): array
    {
        $issues = [];

        $configFile = Path::join($this->configDir, 'config.php');
        $issues = array_merge($issues, $this->checkFile($configFile, 'config.php (used to enable maintenance mode and persist configuration)'));

        $dataFile = Path::join($this->pathData, 'update-finalization.json');
        $issues = array_merge($issues, $this->checkFileOrParentDir($dataFile, 'data/update-finalization.json (used to track the update state machine)'));

        $writableFolders = [
            $this->pathData => 'data/',
            Path::join($this->pathData, 'cache') => 'data/cache/ (download destination for the update archive)',
            Path::join($this->pathData, 'log') => 'data/log/',
            Path::join($this->pathData, 'uploads') => 'data/uploads/',
            Path::join($this->pathRoot, 'vendor') => 'vendor/ (extraction overwrites Composer dependencies)',
            Path::join($this->pathRoot, 'library') => 'library/',
            Path::join($this->pathRoot, 'modules') => 'modules/',
            Path::join($this->pathRoot, 'themes') => 'themes/',
            Path::join($this->pathRoot, 'public') => 'public/',
            Path::join($this->pathRoot, 'locale') => 'locale/',
        ];

        foreach ($writableFolders as $path => $description) {
            $issues = array_merge($issues, $this->checkFolder($path, $description));
        }

        // The install/ folder must be removable. We test it explicitly because
        // is_writable() on a directory does not guarantee the directory can be
        // deleted.
        $issues = array_merge($issues, $this->checkRemovable($this->installDir, 'install/ (removed by the update)'));

        return [
            'can_update' => $issues === [],
            'issues' => $issues,
        ];
    }

    /**
     * @return list<array{path: string, type: string, reason: string, message: string}>
     */
    private function checkFile(string $path, string $description): array
    {
        if (!$this->filesystem->exists($path)) {
            if (!$this->filesystem->exists($this->configDir) || !$this->isWritable($this->configDir)) {
                return [['path' => $path, 'type' => 'file', 'reason' => 'missing', 'message' => sprintf('Required file does not exist and its parent directory is not writable (%s).', $description)]];
            }

            return [];
        }

        if (!$this->isWritable($path)) {
            return [['path' => $path, 'type' => 'file', 'reason' => 'not_writable', 'message' => sprintf('File is not writable by the web server user (%s).', $description)]];
        }

        return [];
    }

    /**
     * @return list<array{path: string, type: string, reason: string, message: string}>
     */
    private function checkFileOrParentDir(string $path, string $description): array
    {
        if ($this->filesystem->exists($path)) {
            if (!$this->isWritable($path)) {
                return [['path' => $path, 'type' => 'file', 'reason' => 'not_writable', 'message' => sprintf('File is not writable by the web server user (%s).', $description)]];
            }

            return [];
        }

        $parent = Path::getDirectory($path);
        if (!$this->filesystem->exists($parent) || !$this->isWritable($parent)) {
            return [['path' => $path, 'type' => 'file', 'reason' => 'not_writable', 'message' => sprintf('File does not exist and its parent directory is not writable (%s).', $description)]];
        }

        return [];
    }

    /**
     * @return list<array{path: string, type: string, reason: string, message: string}>
     */
    private function checkFolder(string $path, string $description): array
    {
        if (!$this->filesystem->exists($path)) {
            return [['path' => $path, 'type' => 'folder', 'reason' => 'missing', 'message' => sprintf('Required folder does not exist (%s).', $description)]];
        }

        if (!$this->isWritable($path)) {
            return [['path' => $path, 'type' => 'folder', 'reason' => 'not_writable', 'message' => sprintf('Folder is not writable by the web server user (%s).', $description)]];
        }

        // Write/delete a probe file to surface SELinux denials and other
        // permissions problems that is_writable() does not catch.
        $probe = Path::join($path, '.fossbilling_update_readiness_check');

        try {
            $this->filesystem->dumpFile($probe, 'probe');
            $this->filesystem->remove($probe);
        } catch (\Throwable) {
            return [['path' => $path, 'type' => 'folder', 'reason' => 'not_writable', 'message' => sprintf('Folder is reported as writable but a test write failed (%s). This is often caused by SELinux or filesystem ACL restrictions.', $description)]];
        }

        return [];
    }

    /**
     * @return list<array{path: string, type: string, reason: string, message: string}>
     */
    private function checkRemovable(string $path, string $description): array
    {
        if (!$this->filesystem->exists($path)) {
            return [];
        }

        if (!$this->isWritable($path)) {
            return [['path' => $path, 'type' => 'folder', 'reason' => 'not_removable', 'message' => sprintf('Folder is not writable and cannot be removed by the update (%s).', $description)]];
        }

        $parent = Path::getDirectory($path);
        if ($parent === '' || !$this->isWritable($parent)) {
            return [['path' => $path, 'type' => 'folder', 'reason' => 'not_removable', 'message' => sprintf('Folder exists but its parent directory is not writable, so it cannot be removed (%s).', $description)]];
        }

        return [];
    }

    private function isWritable(string $path): bool
    {
        if ($path === '' || !@is_writable($path)) {
            return false;
        }

        return true;
    }
}
