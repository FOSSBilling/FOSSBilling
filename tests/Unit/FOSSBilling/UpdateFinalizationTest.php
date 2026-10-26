<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Config;
use FOSSBilling\InformationException;
use FOSSBilling\UpdateFinalization;
use FOSSBilling\UpdatePatcher;
use FOSSBilling\Version;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

beforeEach(function (): void {
    $filesystem = new Filesystem();
    $statePath = Path::join(PATH_DATA, UpdateFinalization::STATE_FILENAME);
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

    $finalization = new UpdateFinalization();
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

    $finalization = new UpdateFinalization();

    $finalization->finalizePendingUpdate();

    $state = json_decode(
        $this->updateFinalizationFilesystem->readFile($this->updateFinalizationStatePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($state['status'])->toBe('finalized');
});

test('serializes finalization with an exclusive lock', function (): void {
    $finalization = new UpdateFinalization();
    $lockPath = Path::join(PATH_DATA, 'update-finalization.lock');
    $lockMethod = new ReflectionMethod(UpdateFinalization::class, 'withFinalizationLock');
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
    $shouldRemove = new ReflectionMethod(UpdateFinalization::class, 'shouldRemoveInstallDirectory');
    $finalization = new UpdateFinalization();

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

    $finalization = new UpdateFinalization();
    $finalization->completeFinalization();

    $completedState = json_decode(
        $this->updateFinalizationFilesystem->readFile($this->updateFinalizationStatePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($completedState['status'])->toBe('complete')
        ->and($completedState['version'])->toBe(Version::VERSION)
        ->and($finalization->isRequired(false))->toBeFalse()
        ->and(Config::getProperty('maintenance_mode'))->toBe($state['maintenance_mode']);
});

function stubUpdatePatcherWithPendingPatches(): object
{
    return new class extends UpdatePatcher {
        public int $pendingPatches = 1;
        public bool $corePatchesApplied = false;

        public function availablePatches(): int
        {
            return $this->pendingPatches;
        }

        public function patchStatus(): array
        {
            return [
                'current' => 120,
                'latest' => 124,
                'pending' => $this->pendingPatches,
            ];
        }

        public function applyConfigPatches(bool $force = false): void
        {
        }

        public function applyCorePatches(bool $force = false): void
        {
            $this->corePatchesApplied = true;
            $this->pendingPatches = 0;
        }
    };
}

function finalizationWithStubPatcher(object $stubPatcher): UpdateFinalization
{
    $finalization = new class extends UpdateFinalization {
        public object $stubPatcher;

        protected function createPatcher(): UpdatePatcher
        {
            return $this->stubPatcher;
        }
    };
    $finalization->stubPatcher = $stubPatcher;

    return $finalization;
}

function seedFinalizedState(Filesystem $filesystem, string $statePath): void
{
    $filesystem->dumpFile(
        $statePath,
        json_encode([
            'status' => 'finalized',
            'target_version' => Version::VERSION,
        ], JSON_THROW_ON_ERROR)
    );
}

test('re-runs finalization when the state is finalized but patches are still pending', function (): void {
    seedFinalizedState($this->updateFinalizationFilesystem, $this->updateFinalizationStatePath);

    $stubPatcher = stubUpdatePatcherWithPendingPatches();
    $finalization = finalizationWithStubPatcher($stubPatcher);
    $finalization->finalizePendingUpdate();

    expect($stubPatcher->corePatchesApplied)->toBeTrue();

    $state = json_decode(
        $this->updateFinalizationFilesystem->readFile($this->updateFinalizationStatePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($state['status'])->toBe('finalized');
});

test('completion reports the pending patch levels instead of a bare message', function (): void {
    seedFinalizedState($this->updateFinalizationFilesystem, $this->updateFinalizationStatePath);

    $finalization = finalizationWithStubPatcher(stubUpdatePatcherWithPendingPatches());

    expect(function () use ($finalization): void {
        $finalization->completeFinalization();
    })->toThrow(InformationException::class, 'There are still 1 pending update patches (database level 120, code level 124)');
});

test('reports schema drift state alongside the finalization status', function (): void {
    $finalization = new UpdateFinalization();
    $status = $finalization->getStatus(false);

    // No database is reachable without DI, so the drift check degrades to
    // "in sync" rather than blocking the status call.
    expect($status['schema_drift'])->toBe([
        'out_of_sync' => false,
        'last_sync_failure' => null,
    ]);
});

test('application readers share a lock that blocks an updater until active work drains', function (): void {
    $service = new UpdateFinalization();
    $service->acquireRuntimeLock();
    $probe = fopen(Path::join(PATH_DATA, 'update-finalization.lock'), 'c');

    try {
        expect(flock($probe, LOCK_EX | LOCK_NB))->toBeFalse();
        expect((new ReflectionMethod($service, 'withFinalizationLock'))->invoke($service, fn () => 'exclusive'))->toBe('exclusive');
        expect(flock($probe, LOCK_EX | LOCK_NB))->toBeFalse();
        $service->releaseRuntimeLock();
        expect(flock($probe, LOCK_EX | LOCK_NB))->toBeTrue();
    } finally {
        $service->releaseRuntimeLock();
        flock($probe, LOCK_UN);
        fclose($probe);
    }
});

test('normal finalization migrates JSON and clears retired previews before restoring maintenance mode', function (): void {
    $config = Config::getConfig();
    $config['maintenance_mode']['enabled'] = false;
    Config::setConfig($config, false);
    $c = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $c->executeStatement('CREATE TABLE email_template (id INTEGER PRIMARY KEY, vars TEXT)');
    $c->insert('product', ['id' => 1, 'config' => '{broken']);
    $c->insert('email_template', ['id' => 1, 'vars' => 'old-live-reset-token']);
    $di = new Pimple\Container([
        'em' => FOSSBilling\Doctrine\EntityManagerFactory::create($c),
        'crypt' => new FOSSBilling\Crypt(),
        'cache' => new Symfony\Component\Cache\Adapter\ArrayAdapter(),
    ]);
    $patcher = new class extends UpdatePatcher {
        public function applyConfigPatches(bool $force = false): void
        {
        }

        public function applyCorePatches(bool $force = false): void
        {
            $this->applyJsonMigrations();
        }

        public function patchStatus(): array
        {
            return ['current' => 133, 'latest' => 133, 'pending' => 0];
        }

        public function availablePatches(): int
        {
            return 0;
        }
    };
    $patcher->setDi($di);
    $finalization = finalizationWithStubPatcher($patcher);
    $finalization->setDi($di);
    expect(fn () => $finalization->finalizeAndComplete())->toThrow(FOSSBilling\Doctrine\JsonMigrationException::class)
        ->and(Config::getProperty('maintenance_mode.enabled'))->toBeTrue()
        ->and(json_decode(file_get_contents($this->updateFinalizationStatePath), true)['status'])->toBe('pending')
        ->and($c->fetchOne('SELECT vars FROM email_template'))->toBe('old-live-reset-token');
    $c->update('product', ['config' => ''], ['id' => 1]);
    $finalization->finalizeAndComplete();
    expect($c->fetchOne('SELECT config FROM product'))->toBeNull()
        ->and($c->fetchOne('SELECT vars FROM email_template'))->toBeNull()
        ->and(Config::getProperty('maintenance_mode.enabled'))->toBeFalse()
        ->and(json_decode(file_get_contents($this->updateFinalizationStatePath), true)['status'])->toBe('complete')
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1);
});

test('manual update entry point delegates to the same locked finalization flow', function (): void {
    $finalization = Mockery::mock(UpdateFinalization::class);
    $finalization->shouldReceive('finalizeAndComplete')->once();
    $updater = new FOSSBilling\Update();
    $updater->setDi(new Pimple\Container(['update_finalization' => $finalization]));
    $updater->performManualUpdate();
});

test('finalization preserves the configured PHP execution limit', function (int $limit): void {
    $original = ini_get('max_execution_time');

    try {
        ini_set('max_execution_time', (string) $limit);
        finalizationWithStubPatcher(stubUpdatePatcherWithPendingPatches())->finalizeUpdate();
        expect((int) ini_get('max_execution_time'))->toBe($limit);
    } finally {
        ini_set('max_execution_time', $original);
    }
})->with([0, 30, 600]);
