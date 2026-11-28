<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('only interrupted writes retain the durable extraction recovery marker', function (string $scenario, bool $incomplete, bool $extracted): void {
    $root = sys_get_temp_dir() . '/fb-interrupted-extraction-' . bin2hex(random_bytes(6));
    $fs = new Filesystem();

    try {
        $fs->mkdir($root . '/core/Filesystem');
        $fs->copy(PATH_ROOT . '/core/Filesystem/ZipExtractor.php', $root . '/core/Filesystem/ZipExtractor.php');
        $fs->mkdir($root . '/core/Update');
        $fs->copy(PATH_ROOT . '/core/Update/RuntimeLock.php', $root . '/core/Update/RuntimeLock.php');
        if ($fs->exists(PATH_ROOT . '/library/FOSSBilling/Update.php')) {
            $fs->mkdir($root . '/library/FOSSBilling');
            $fs->copy(PATH_ROOT . '/library/FOSSBilling/Update.php', $root . '/library/FOSSBilling/Update.php');
        }
        $process = new Process([PHP_BINARY, PATH_TESTS . '/Fixtures/update-cache-clear.php', $root, $scenario]);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($result['incomplete'])->toBe($incomplete)
            ->and($result['extracted'])->toBe($extracted)
            ->and($result['locked'])->toBeFalse()
            ->and($result['archives'])->toBe([]);
        if ($incomplete) {
            expect($result['error'])->not->toBeNull();
        }
    } finally {
        $fs->remove($root);
    }
})->with([
    'success' => ['normal', false, true],
    'invalid archive before writes' => ['extraction_failure', false, false],
    'protected state before writes' => ['protected_extraction_marker', false, false],
    'previous interrupted update cannot be retried blindly' => ['recovery_pending', true, false],
    'failure after the first file' => ['partial_extraction', true, true],
    'failure handing off to finalization' => ['pending_state_failure', true, true],
]);

test('an interrupted extraction blocks bootstrap before Composer without expiring', function (): void {
    $root = sys_get_temp_dir() . '/fb-extraction-bootstrap-' . bin2hex(random_bytes(6));
    $fs = new Filesystem();

    try {
        $fs->mkdir([$root . '/data/uploads', $root . '/vendor', $root . '/core/Update']);
        $fs->copy(PATH_ROOT . '/load.php', $root . '/load.php');
        $fs->copy(PATH_ROOT . '/core/Update/RuntimeLock.php', $root . '/core/Update/RuntimeLock.php');
        $fs->dumpFile($root . '/vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../autoloaded", "ready"); exit(0);');
        $fs->touch($root . '/.update-extraction-pending', time() - 86400);
        $process = new Process([PHP_BINARY, $root . '/load.php'], env: ['APP_ENV' => 'prod']);
        $process->run();
        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('Update extraction has not completed')
            ->and($fs->exists($root . '/autoloaded'))->toBeFalse();
        $fs->remove($root . '/.update-extraction-pending');
        $process->mustRun();
        expect($fs->exists($root . '/autoloaded'))->toBeTrue();
    } finally {
        $fs->remove($root);
    }
});

test('old updater work retains the finalization reader after the extraction gate is released', function (): void {
    $root = sys_get_temp_dir() . '/fb-finalization-handoff-' . bin2hex(random_bytes(6));
    $fs = new Filesystem();
    $fs->mkdir($root . '/data/uploads');
    $input = new Symfony\Component\Process\InputStream();
    $code = 'require $argv[1]; $root = $argv[2]; FOSSBilling\\Core\\Update\\RuntimeLock::acquire($root); FOSSBilling\\Core\\Update\\RuntimeLock::acquireFinalizationReader($argv[3]); FOSSBilling\\Core\\Update\\RuntimeLock::exclusively($root, static fn () => null); FOSSBilling\\Core\\Update\\RuntimeLock::release($root); echo "ready\n"; fflush(STDOUT); fgets(STDIN);';
    $process = new Process([PHP_BINARY, '-r', $code, PATH_ROOT . '/core/Update/RuntimeLock.php', $root, PATH_DATA]);
    $process->setInput($input);
    $handle = null;

    try {
        $process->start();
        expect($process->waitUntil(static fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue();
        $handle = fopen(PATH_DATA . '/update-finalization.lock', 'c');
        expect(flock($handle, LOCK_EX | LOCK_NB))->toBeFalse();
        if (class_exists(FOSSBilling\Core\Update\Finalization::class)) {
            $finalization = new FOSSBilling\Core\Update\Finalization();
            $lock = new ReflectionMethod($finalization, 'withFinalizationLock');
            expect(fn () => $lock->invoke($finalization, static fn () => null, 0.02))
                ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'still finishing');
        }
        $input->write("finish\n");
        $input->close();
        $process->wait();
        expect($process->getExitCode())->toBe(0)
            ->and(flock($handle, LOCK_EX | LOCK_NB))->toBeTrue();
    } finally {
        $process->stop();
        $input->close();
        if (is_resource($handle)) {
            fclose($handle);
        }
        $fs->remove($root);
    }
});
