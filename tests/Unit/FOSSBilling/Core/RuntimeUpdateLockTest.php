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
    $filesystem->copy(PATH_ROOT . '/core/Update/RuntimeLock.php', $root . '/core/Update/RuntimeLock.php');
    $filesystem->dumpFile($root . '/vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../autoloaded", "ready"); exit(0);');
    $process = new Process([PHP_BINARY, '-r', 'file_put_contents($argv[1] . "/entering", "ready"); require $argv[1] . "/load.php";', $root], env: ['APP_ENV' => 'prod']);

    try {
        RuntimeLock::exclusively($root, function () use ($process, $root): void {
            $process->start();
            awaitRuntimeBarrier($root . '/entering', $process);
            usleep(100000);
            expect($process->isRunning())->toBeTrue()->and(is_file($root . '/autoloaded'))->toBeFalse();
        });
        $process->wait();
        expect($process->isSuccessful())->toBeTrue()->and(is_file($root . '/autoloaded'))->toBeTrue();
    } finally {
        $process->stop();
        $filesystem->remove($root);
    }
});
