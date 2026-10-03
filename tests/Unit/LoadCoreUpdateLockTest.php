<?php

declare(strict_types=1);

use FOSSBilling\Core\Update\Updater;
use Symfony\Component\Filesystem\Filesystem;

/**
 * isCoreUpdateLockActive() reads a real, fixed path (PATH_ROOT/.update-lock) rather than
 * anything injectable: it has to run in load.php before the Composer autoloader (and any
 * config/DI) is available. These tests write/remove that one file directly and always clean
 * up, mirroring how LoadCheckInstallerTest exercises load.php's other bootstrap guards.
 */
function lockFilePath(): string
{
    return PATH_ROOT . DIRECTORY_SEPARATOR . '.update-lock';
}

function withCoreUpdateLock(?int $mtime, Closure $callback): void
{
    $filesystem = new Filesystem();
    $path = lockFilePath();
    $existed = $filesystem->exists($path);
    $originalMtime = $existed ? filemtime($path) : null;

    try {
        if ($mtime === null) {
            $filesystem->remove($path);
        } else {
            $filesystem->touch($path, $mtime);
        }

        $callback();
    } finally {
        if ($existed) {
            $filesystem->touch($path, (int) $originalMtime);
        } else {
            $filesystem->remove($path);
        }
    }
}

test('the lock filename load.php checks for matches Updater::LOCK_FILENAME', function (): void {
    expect(basename(lockFilePath()))->toBe(Updater::LOCK_FILENAME);
});

test('isCoreUpdateLockActive is false when no lock file exists', function (): void {
    withCoreUpdateLock(null, function (): void {
        expect(isCoreUpdateLockActive())->toBeFalse();
    });
});

test('isCoreUpdateLockActive is true for a freshly written lock file', function (): void {
    withCoreUpdateLock(time(), function (): void {
        expect(isCoreUpdateLockActive())->toBeTrue();
    });
});

test('isCoreUpdateLockActive is true just under the staleness window', function (): void {
    withCoreUpdateLock(time() - 599, function (): void {
        expect(isCoreUpdateLockActive())->toBeTrue();
    });
});

test('isCoreUpdateLockActive is true exactly at the staleness boundary', function (): void {
    // The boundary is exact (<=600), so this asserts a fixed reference time
    // against a fixed mtime instead of two separate time() calls - otherwise a
    // clock tick between writing the lock and checking it could flip a
    // legitimately-600-second-old lock to 601 and fail this on production code
    // that's still correct.
    $now = time();

    withCoreUpdateLock($now - 600, function () use ($now): void {
        expect(isCoreUpdateLockActive($now))->toBeTrue();
    });
});

test('isCoreUpdateLockActive treats a lock past the staleness window as abandoned', function (): void {
    withCoreUpdateLock(time() - 601, function (): void {
        expect(isCoreUpdateLockActive())->toBeFalse();
    });
});
