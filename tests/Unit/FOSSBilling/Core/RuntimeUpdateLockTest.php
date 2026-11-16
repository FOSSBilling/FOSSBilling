<?php

declare(strict_types=1);

use FOSSBilling\Core\Update\RuntimeLock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function awaitRuntimeBarrier(string $path, Process $process): void
{
    $deadline = microtime(true) + 5;
    while (!is_file($path)) {
        if (!$process->isRunning() || microtime(true) > $deadline) {
            throw new RuntimeException('Missing barrier ' . $path . ': ' . $process->getErrorOutput());
        }
        usleep(1000);
    }
}

test('extraction drains active readers and prevents new readers entering', function (string $kind): void {
    $root = sys_get_temp_dir() . '/fb-runtime-update-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir($root . '/data/uploads');
    $worker = PATH_TESTS . '/Fixtures/runtime-update-lock.php';
    $reader = new Process([PHP_BINARY, $worker, $root, $kind, 'reader']);
    $updater = new Process([PHP_BINARY, $worker, $root, 'updater', 'updater']);
    $newReader = new Process([PHP_BINARY, $worker, $root, 'reader', 'new']);

    try {
        $reader->start();
        awaitRuntimeBarrier($root . '/reader.ready', $reader);
        $updater->start();
        awaitRuntimeBarrier($root . '/updater.entering', $updater);
        usleep(100000);
        expect($updater->isRunning())->toBeTrue()->and(is_file($root . '/updater.ready'))->toBeFalse();
        $filesystem->touch($root . '/reader.release');
        awaitRuntimeBarrier($root . '/updater.ready', $updater);
        $newReader->start();
        awaitRuntimeBarrier($root . '/new.entering', $newReader);
        usleep(100000);
        expect($newReader->isRunning())->toBeTrue()->and(is_file($root . '/new.ready'))->toBeFalse();
        $filesystem->touch($root . '/updater.release');
        awaitRuntimeBarrier($root . '/new.ready', $newReader);
        $filesystem->touch($root . '/new.release');
        foreach ([$reader, $updater, $newReader] as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue()->and($process->getOutput())->toBe('complete');
        }
    } finally {
        foreach ([$reader, $updater, $newReader] as $process) {
            $process->stop();
        }
        $filesystem->remove($root);
    }
})->with(['reader', 'legacy-cron']);

test('drain timeouts leave the application lock usable', function (): void {
    $root = sys_get_temp_dir() . '/fb-runtime-timeout-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir($root . '/data/uploads');
    $reader = fopen($root . '/' . RuntimeLock::FILENAME, 'c');
    flock($reader, LOCK_SH);

    try {
        expect(fn () => RuntimeLock::exclusively($root, fn () => throw new LogicException('Must not extract'), 0.02))
            ->toThrow(RuntimeException::class, 'still finishing');
        flock($reader, LOCK_UN);
        expect(RuntimeLock::exclusively($root, fn () => 'recovered'))->toBe('recovered');
    } finally {
        RuntimeLock::release($root);
        fclose($reader);
        $filesystem->remove($root);
    }
});

test('bootstrap cannot load application classes while extraction owns the lock', function (): void {
    $root = sys_get_temp_dir() . '/fb-bootstrap-update-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir([$root . '/data/uploads', $root . '/vendor', $root . '/core/Update']);
    $filesystem->copy(PATH_ROOT . '/load.php', $root . '/load.php');
    $helperSource = $filesystem->readFile(PATH_ROOT . '/core/Update/RuntimeLock.php');
    $filesystem->dumpFile($root . '/core/Update/RuntimeLock.php', $helperSource . PHP_EOL . 'file_put_contents(__DIR__ . "/../../helper-loaded", "old");');
    $filesystem->dumpFile($root . '/vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../autoloaded", "ready"); exit(0);');
    $process = new Process([PHP_BINARY, '-r', 'file_put_contents($argv[1] . "/entering", "ready"); require $argv[1] . "/load.php";', $root], env: ['APP_ENV' => 'prod']);

    $process->setTimeout(5);

    try {
        RuntimeLock::exclusively($root, function () use ($process, $root, $helperSource, $filesystem): void {
            $process->start();
            awaitRuntimeBarrier($root . '/entering', $process);
            $process->wait();
            expect($process->getExitCode())->toBe(1)
                ->and($process->getErrorOutput())->toContain('currently applying a core update')
                ->and(is_file($root . '/autoloaded'))->toBeFalse()
                ->and(is_file($root . '/helper-loaded'))->toBeFalse();
            $filesystem->dumpFile($root . '/core/Update/RuntimeLock.php', $helperSource . PHP_EOL . 'file_put_contents(__DIR__ . "/../../helper-loaded", "new");');
        });
        $process->mustRun();
        expect($process->isSuccessful())->toBeTrue()->and(is_file($root . '/autoloaded'))->toBeTrue()
            ->and($filesystem->readFile($root . '/helper-loaded'))->toBe('new');
    } finally {
        $process->stop();
        $filesystem->remove($root);
    }
});

test('stale marker takeover preserves its inode and excludes competing updaters', function (): void {
    $root = sys_get_temp_dir() . '/fb-marker-takeover-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir($root);
    $path = $root . '/.update-lock';
    $filesystem->touch($path, time() - 700);
    $original = stat($path);
    $owner = false;

    try {
        $owner = RuntimeLock::claimMarker($path, 600);
        expect(is_resource($owner))->toBeTrue()
            ->and(fstat($owner)['ino'])->toBe($original['ino']);
        $contender = new Process([PHP_BINARY, '-r', 'require $argv[1]; $handle = FOSSBilling\Core\Update\RuntimeLock::claimMarker($argv[2], 600); echo is_resource($handle) ? "claimed" : "busy"; if (is_resource($handle)) { fclose($handle); }', PATH_ROOT . '/vendor/autoload.php', $path]);
        $contender->mustRun();
        expect($contender->getOutput())->toBe('busy');
        // Age alone must not let a second updater displace the live owner.
        $probe = fopen($path, 'r');

        try {
            expect(flock($probe, LOCK_SH | LOCK_NB))->toBeFalse();
        } finally {
            fclose($probe);
        }
    } finally {
        if (is_resource($owner)) {
            fclose($owner);
        }
        $filesystem->remove($root);
    }
});

test('an unlocked fresh marker retains the legacy recovery window', function (): void {
    $root = sys_get_temp_dir() . '/fb-marker-fresh-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir($root);
    $path = $root . '/.update-lock';
    $owner = RuntimeLock::claimMarker($path, 600);

    try {
        expect(is_resource($owner))->toBeTrue();
        fclose($owner);
        $owner = false;
        expect(RuntimeLock::claimMarker($path, 600))->toBeFalse();
        $filesystem->touch($path, time() - 700);
        $owner = RuntimeLock::claimMarker($path, 600);
        expect(is_resource($owner))->toBeTrue();
    } finally {
        if (is_resource($owner)) {
            fclose($owner);
        }
        $filesystem->remove($root);
    }
});

test('updater restores its finalization reader before reopening bootstrap', function (string $mode): void {
    $root = sys_get_temp_dir() . '/fb-finalization-gate-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir($root . '/data/uploads');
    $process = new Process([PHP_BINARY, PATH_TESTS . '/Fixtures/runtime-update-lock.php', $root, $mode, 'handoff']);
    $bootstrap = fopen($root . '/' . RuntimeLock::FILENAME, 'c');
    $finalization = fopen($root . '/data/update-finalization.lock', 'c');

    try {
        $process->start();
        awaitRuntimeBarrier($root . '/handoff.ready', $process);
        // A new request must not enter while the old updater restores its
        // schema reader: otherwise it can migrate before old session cleanup.
        expect(flock($bootstrap, LOCK_SH | LOCK_NB))->toBeFalse();
        $filesystem->touch($root . '/handoff.release');
        awaitRuntimeBarrier($root . '/tail.ready', $process);
        expect(flock($bootstrap, LOCK_SH | LOCK_NB))->toBeTrue()
            ->and(flock($finalization, LOCK_EX | LOCK_NB))->toBeFalse();
        $filesystem->touch($root . '/tail.release');
        $process->wait();
        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toBe('complete')
            ->and(flock($finalization, LOCK_EX | LOCK_NB))->toBeTrue();
    } finally {
        $process->stop();
        fclose($bootstrap);
        fclose($finalization);
        $filesystem->remove($root);
    }
})->with(['handoff-success', 'handoff-failure']);
