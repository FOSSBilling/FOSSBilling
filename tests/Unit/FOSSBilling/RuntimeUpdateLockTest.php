<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require_once PATH_ROOT . '/core/Update/RuntimeLock.php';

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
})->with(['request', 'cron']);
