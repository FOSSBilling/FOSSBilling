<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\System\Config;
use FOSSBilling\System\Version;
use FOSSBilling\Update\Finalization;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

beforeEach(function (): void {
    $filesystem = new Filesystem();
    $statePath = Path::join(PATH_DATA, Finalization::STATE_FILENAME);
    $configBackupPath = Path::changeExtension(PATH_CONFIG, 'old.php');

    $this->updateFinalizationFilesystem = $filesystem;
    $this->updateFinalizationStatePath = $statePath;
    $this->updateFinalizationOriginalConfig = $filesystem->readFile(PATH_CONFIG);
    $this->updateFinalizationOriginalState = $filesystem->exists($statePath) ? $filesystem->readFile($statePath) : null;
    $this->updateFinalizationOriginalConfigBackup = $filesystem->exists($configBackupPath) ? $filesystem->readFile($configBackupPath) : null;

    $filesystem->remove($statePath);
});

afterEach(function (): void {
    $filesystem = $this->updateFinalizationFilesystem;
    $configBackupPath = Path::changeExtension(PATH_CONFIG, 'old.php');

    $filesystem->dumpFile(PATH_CONFIG, $this->updateFinalizationOriginalConfig);
    if ($this->updateFinalizationOriginalConfigBackup === null) {
        $filesystem->remove($configBackupPath);
    } else {
        $filesystem->dumpFile($configBackupPath, $this->updateFinalizationOriginalConfigBackup);
    }

    if ($this->updateFinalizationOriginalState === null) {
        $filesystem->remove($this->updateFinalizationStatePath);
    } else {
        $filesystem->dumpFile($this->updateFinalizationStatePath, $this->updateFinalizationOriginalState);
    }

    clearstatcache(true, PATH_CONFIG);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(PATH_CONFIG, true);
    }
});

test('creates one pending state and keeps it unchanged across repeated checks', function (): void {
    $config = Config::getConfig();
    $config['maintenance_mode'] = [
        'enabled' => false,
        'allowed_urls' => ['/existing-finalization-url'],
        'allowed_ips' => ['127.0.0.1'],
    ];
    Config::setConfig($config, false);

    $finalization = new Finalization();
    $state = $finalization->ensureCurrentVersionFinalization();
    $stateAgain = $finalization->ensureCurrentVersionFinalization();

    expect($state)->toBeArray()
        ->and($stateAgain)->toBe($state)
        ->and($state['status'])->toBe('pending')
        ->and($state['source'])->toBe('missing-finalization-state')
        ->and($state['target_version'])->toBe(Version::VERSION)
        ->and($finalization->isRequired(false))->toBeTrue();

    $maintenanceMode = Config::getProperty('maintenance_mode');
    expect($maintenanceMode['enabled'])->toBeTrue()
        ->and($maintenanceMode['allowed_urls'])->toContain('/existing-finalization-url')
        ->and($maintenanceMode['allowed_urls'])->toContain(rtrim((string) ADMIN_PREFIX, '/') . '/system/update/finalize');
});

test('does not re-run a finalized update before the session service is initialized', function (): void {
    $this->updateFinalizationFilesystem->dumpFile(
        $this->updateFinalizationStatePath,
        json_encode([
            'status' => 'finalized',
            'target_version' => Version::VERSION,
        ], JSON_THROW_ON_ERROR)
    );

    $finalization = new Finalization();

    $finalization->finalizePendingUpdate();

    $state = json_decode(
        (string) $this->updateFinalizationFilesystem->readFile($this->updateFinalizationStatePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($state['status'])->toBe('finalized');
});

test('serializes finalization with an exclusive lock', function (): void {
    $finalization = new Finalization();
    $lockPath = Path::join(PATH_DATA, 'update-finalization.lock');
    $lockMethod = new ReflectionMethod(Finalization::class, 'withFinalizationLock');
    $lockHeld = false;

    $lockMethod->invoke($finalization, function () use (&$lockHeld, $lockPath): void {
        $handle = fopen($lockPath, 'c');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the finalization lock for testing.');
        }

        try {
            $lockHeld = !flock($handle, LOCK_EX | LOCK_NB);
        } finally {
            fclose($handle);
        }
    });

    expect($lockHeld)->toBeTrue();
});

test('removes the install directory unless the environment is explicitly dev or test', function (): void {
    $shouldRemove = new ReflectionMethod(Finalization::class, 'shouldRemoveInstallDirectory');
    $finalization = new Finalization();

    withAppEnv(null, fn () => expect($shouldRemove->invoke($finalization))->toBeTrue());
    withAppEnv('staging', fn () => expect($shouldRemove->invoke($finalization))->toBeTrue());
    withAppEnv('prod', fn () => expect($shouldRemove->invoke($finalization))->toBeTrue());
    withAppEnv('dev', fn () => expect($shouldRemove->invoke($finalization))->toBeFalse());
    withAppEnv('test', fn () => expect($shouldRemove->invoke($finalization))->toBeFalse());
});

test('completion restores captured maintenance mode and records the current version', function (): void {
    $config = Config::getConfig();
    $config['maintenance_mode'] = [
        'enabled' => true,
        'allowed_urls' => ['/temporary-finalization-url'],
        'allowed_ips' => [],
    ];
    Config::setConfig($config, false);

    $state = [
        'status' => 'finalized',
        'from_version' => '0.8.4',
        'target_version' => Version::VERSION,
        'branch' => 'release',
        'update_type' => null,
        'source' => 'test',
        'created_at' => date(DATE_ATOM),
        'finalized_at' => date(DATE_ATOM),
        'completed_at' => null,
        'maintenance_mode' => [
            'enabled' => false,
            'allowed_urls' => ['/existing-maintenance-exception'],
            'allowed_ips' => ['127.0.0.1'],
        ],
    ];
    $this->updateFinalizationFilesystem->dumpFile(
        $this->updateFinalizationStatePath,
        json_encode($state, JSON_THROW_ON_ERROR)
    );

    $finalization = new Finalization();
    $finalization->completeFinalization();

    $completedState = json_decode(
        (string) $this->updateFinalizationFilesystem->readFile($this->updateFinalizationStatePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($completedState['status'])->toBe('complete')
        ->and($completedState['version'])->toBe(Version::VERSION)
        ->and($finalization->isRequired(false))->toBeFalse()
        ->and(Config::getProperty('maintenance_mode'))->toBe($state['maintenance_mode']);
});
