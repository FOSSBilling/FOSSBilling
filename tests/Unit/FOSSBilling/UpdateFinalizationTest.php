<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\UpdateFinalization;
use FOSSBilling\UpdatePatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

beforeEach(function (): void {
    $filesystem = new Filesystem();
    $statePath = Path::join(PATH_DATA, UpdateFinalization::STATE_FILENAME);

    $this->updateFinalizationFilesystem = $filesystem;
    $this->updateFinalizationStatePath = $statePath;
    $this->updateFinalizationOriginalState = $filesystem->exists($statePath) ? $filesystem->readFile($statePath) : null;

    $filesystem->remove($statePath);
});

afterEach(function (): void {
    if ($this->updateFinalizationOriginalState === null) {
        $this->updateFinalizationFilesystem->remove($this->updateFinalizationStatePath);
    } else {
        $this->updateFinalizationFilesystem->dumpFile($this->updateFinalizationStatePath, $this->updateFinalizationOriginalState);
    }
});

function stubUpdatePatcherWithPendingPatches(int $pending = 1): UpdatePatcher
{
    return new class($pending) extends UpdatePatcher {
        public function __construct(private readonly int $pendingPatches)
        {
        }

        public function availablePatches(): int
        {
            return $this->pendingPatches;
        }
    };
}

function finalizationWithStubPatcher(UpdatePatcher $stubPatcher): UpdateFinalization
{
    $finalization = new class extends UpdateFinalization {
        public UpdatePatcher $stubPatcher;

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
            'finalized_at' => date(DATE_ATOM),
        ], JSON_THROW_ON_ERROR)
    );
}

test('flips a finalized state with pending patches back to pending', function (): void {
    seedFinalizedState($this->updateFinalizationFilesystem, $this->updateFinalizationStatePath);

    $finalization = finalizationWithStubPatcher(stubUpdatePatcherWithPendingPatches(3));
    $state = $finalization->ensureCurrentVersionFinalization();

    expect($state['status'])->toBe('pending')
        ->and($state['finalized_at'])->toBeNull()
        ->and($finalization->isRequired(false))->toBeTrue();

    $persisted = json_decode(
        $this->updateFinalizationFilesystem->readFile($this->updateFinalizationStatePath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($persisted['status'])->toBe('pending');
});

test('leaves a finalized state with no pending patches alone', function (): void {
    seedFinalizedState($this->updateFinalizationFilesystem, $this->updateFinalizationStatePath);

    $finalization = finalizationWithStubPatcher(stubUpdatePatcherWithPendingPatches(0));
    $state = $finalization->ensureCurrentVersionFinalization();

    expect($state['status'])->toBe('finalized');
});
