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

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Coordinates the post-update finalization flow.
 *
 * The single state file under PATH_DATA, update-finalization.json, records both
 * pending finalization work and the last version that completed finalization.
 * If the file is missing or contains a completed version that does not match the
 * currently installed files, the current code creates a pending state. This is
 * how updates from old versions and manual file uploads are forced through
 * finalization even though they could not create pending state before files changed.
 */
class UpdateFinalization implements InjectionAwareInterface
{
    public const string STATE_FILENAME = 'update-finalization.json';

    private const string STATUS_PENDING = 'pending';
    private const string STATUS_FINALIZED = 'finalized';
    private const string STATUS_COMPLETE = 'complete';

    private ?\Pimple\Container $di = null;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getStatePath(): string
    {
        return Path::join(PATH_DATA, self::STATE_FILENAME);
    }

    public function isRequired(bool $ensure = true): bool
    {
        if ($ensure) {
            $this->ensureCurrentVersionFinalization();
        }

        return $this->stateRequiresFinalization($this->readState());
    }

    public function getStatus(bool $ensure = true): array
    {
        if ($ensure) {
            $this->ensureCurrentVersionFinalization();
        }

        $state = $this->readState();

        return [
            'required' => $this->stateRequiresFinalization($state),
            'status' => $state['status'] ?? 'not_required',
            'current_version' => Version::VERSION,
            'state' => $state,
            'pending_patches' => $this->getAvailablePatchCount(),
        ];
    }

    public function ensureCurrentVersionFinalization(): ?array
    {
        $state = $this->readState();
        if ($this->isCompleteForCurrentVersion($state)) {
            return null;
        }

        // Pending/finalized states contain the original maintenance-mode state,
        // so keep them intact until the admin completes finalization.
        if ($this->stateRequiresFinalization($state)) {
            return $state;
        }

        $fromVersion = is_string($state['version'] ?? null) ? $state['version'] : null;

        return $this->createPendingState(
            $fromVersion,
            Version::VERSION,
            [
                'source' => $fromVersion !== null ? 'version-state-mismatch' : 'missing-finalization-state',
                'branch' => Config::getProperty('update_branch', 'release'),
            ]
        );
    }

    public function createPendingState(?string $fromVersion, string $targetVersion, array $context = []): array
    {
        $existing = $this->readState();
        if ($this->stateRequiresFinalization($existing)) {
            return $existing;
        }

        $state = [
            'status' => self::STATUS_PENDING,
            'from_version' => $fromVersion,
            'target_version' => $targetVersion,
            'branch' => $context['branch'] ?? Config::getProperty('update_branch', 'release'),
            'update_type' => $context['update_type'] ?? $this->detectUpdateType($fromVersion, $targetVersion),
            'source' => $context['source'] ?? 'update',
            'created_at' => date(DATE_ATOM),
            'finalized_at' => null,
            'completed_at' => null,
            'maintenance_mode' => $this->getCurrentMaintenanceMode(),
        ];

        // Write the pending state before enabling maintenance mode so a failed
        // config write still leaves recoverable evidence that finalization is pending.
        $this->writeState($state);
        $this->enableMaintenanceMode();

        return $state;
    }

    /**
     * Runs config/database patches with the currently loaded codebase.
     *
     * This is intentionally separate from completeFinalization(): after patches
     * run, the admin still has a review step before maintenance mode is restored.
     */
    public function finalizeUpdate(): array
    {
        $state = $this->ensureCurrentVersionFinalization();

        try {
            $this->clearCache();

            $patcher = new UpdatePatcher();
            if ($this->di instanceof \Pimple\Container) {
                $patcher->setDi($this->di);
            }
            $patcher->applyConfigPatches();
            $patcher->applyCorePatches();

            $this->filesystem->remove(Path::join(PATH_ROOT, 'install'));
            $this->clearCache();
        } catch (IOException $e) {
            error_log($e->getMessage());

            throw new Exception('Unable to clear cache and/or remove install folder while finalizing the update. Further details are available in the error log.');
        }

        if ($state !== null) {
            $state['status'] = self::STATUS_FINALIZED;
            $state['finalized_at'] = date(DATE_ATOM);
            $this->writeState($state);
        }

        return $this->getStatus(false);
    }

    public function completeFinalization(): void
    {
        $state = $this->readState();
        if ($state === null || $this->isCompleteForCurrentVersion($state)) {
            // Nothing is pending, but writing a complete state makes fresh/manual
            // states explicit and prevents the fallback from recreating pending work.
            $this->writeCompleteState();

            return;
        }

        if (($state['status'] ?? null) !== self::STATUS_FINALIZED) {
            throw new InformationException('Update finalization must be run before it can be completed.');
        }

        $pendingPatches = $this->getAvailablePatchCount();
        if ($pendingPatches !== null && $pendingPatches > 0) {
            throw new InformationException('There are still pending update patches. Run finalization before completing the update.');
        }

        $state['completed_at'] = date(DATE_ATOM);
        // Restore the exact maintenance-mode state captured when the pending
        // state was created. If maintenance was already enabled before the update,
        // it stays enabled.
        $this->restoreMaintenanceMode($state);
        $this->writeCompleteState($state);
        $this->clearCache();
    }

    public function finalizeAndComplete(): void
    {
        // CLI patching has no review screen, so it performs both web steps.
        $this->finalizeUpdate();
        $this->completeFinalization();
    }

    public function writeCompleteState(?array $state = null): void
    {
        $this->filesystem->mkdir(PATH_DATA, 0o755);
        $this->filesystem->dumpFile($this->getStatePath(), self::encodeJson([
            'status' => self::STATUS_COMPLETE,
            'version' => Version::VERSION,
            'from_version' => $state['from_version'] ?? null,
            'target_version' => $state['target_version'] ?? Version::VERSION,
            'finalized_at' => $state['finalized_at'] ?? date(DATE_ATOM),
            'completed_at' => $state['completed_at'] ?? date(DATE_ATOM),
        ]));
    }

    public function isAdminPathAllowed(string $path): bool
    {
        $path = trim($path, '/');

        return in_array($path, [
            'staff/login',
            'system/update/finalize',
        ], true);
    }

    public function isAdminApiCallAllowed(string $class, string $method): bool
    {
        $call = str_starts_with($method, $class . '_') ? $method : "{$class}_{$method}";

        return in_array($call, [
            'system_update_finalization_status',
            'system_finalize_update',
            'system_complete_update_finalization',
            'profile_logout',
        ], true);
    }

    private function readState(): ?array
    {
        return $this->readJsonFile($this->getStatePath());
    }

    private function readJsonFile(string $path): ?array
    {
        if (!$this->filesystem->exists($path)) {
            return null;
        }

        try {
            $decoded = json_decode($this->filesystem->readFile($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException|IOException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function writeState(array $state): void
    {
        $this->filesystem->mkdir(PATH_DATA, 0o755);
        $this->filesystem->dumpFile($this->getStatePath(), self::encodeJson($state));
    }

    private function getCurrentMaintenanceMode(): array
    {
        $maintenanceMode = Config::getProperty('maintenance_mode', []);

        return is_array($maintenanceMode) ? $maintenanceMode : [];
    }

    private function enableMaintenanceMode(): void
    {
        $config = Config::getConfig();
        $maintenanceMode = $config['maintenance_mode'] ?? [];
        if (!is_array($maintenanceMode)) {
            $maintenanceMode = [];
        }

        $maintenanceMode['enabled'] = true;
        // Keep login and finalization reachable while every other public/client
        // route remains protected by maintenance mode.
        $maintenanceMode['allowed_urls'] = array_values(array_unique(array_merge(
            (array) ($maintenanceMode['allowed_urls'] ?? []),
            $this->getFinalizationAllowedUrls()
        )));

        $config['maintenance_mode'] = $maintenanceMode;
        Config::setConfig($config);
    }

    private function restoreMaintenanceMode(array $state): void
    {
        $config = Config::getConfig();
        $previous = $state['maintenance_mode'] ?? [];

        $config['maintenance_mode'] = is_array($previous) ? $previous : [];
        Config::setConfig($config);
    }

    private function getFinalizationAllowedUrls(): array
    {
        $adminPrefix = defined('ADMIN_PREFIX') ? rtrim(ADMIN_PREFIX, '/') : '/admin';

        return [
            $adminPrefix . '/staff/login',
            $adminPrefix . '/system/update/finalize',
        ];
    }

    private function getAvailablePatchCount(): ?int
    {
        try {
            // Patch counting depends on the database being reachable. During early
            // recovery paths it is better to show an unknown count than to block the page.
            $patcher = new UpdatePatcher();
            if ($this->di instanceof \Pimple\Container) {
                $patcher->setDi($this->di);
            }

            return $patcher->availablePatches();
        } catch (\Throwable) {
            return null;
        }
    }

    private function clearCache(): void
    {
        $this->filesystem->remove(PATH_CACHE);
        $this->filesystem->mkdir(PATH_CACHE, 0o755);
    }

    private function stateRequiresFinalization(?array $state): bool
    {
        return in_array($state['status'] ?? null, [self::STATUS_PENDING, self::STATUS_FINALIZED], true);
    }

    private function isCompleteForCurrentVersion(?array $state): bool
    {
        return ($state['status'] ?? null) === self::STATUS_COMPLETE
            && ($state['version'] ?? null) === Version::VERSION;
    }

    private function detectUpdateType(?string $fromVersion, string $targetVersion): ?int
    {
        if ($fromVersion === null || $fromVersion === '' || Version::isPreviewVersion($fromVersion) || Version::isPreviewVersion($targetVersion)) {
            return null;
        }

        return Version::getUpdateType($targetVersion, $fromVersion);
    }

    private static function encodeJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
