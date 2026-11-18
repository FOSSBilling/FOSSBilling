<?php

declare(strict_types=1);

use FOSSBilling\Core\Update\RuntimeLock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function bridgeUpgradeSource(string $root): void
{
    $filesystem = new Filesystem();
    $fixtures = PATH_TESTS . '/Fixtures/upgrades/0.8.9';
    $filesystem->mkdir([$root . '/data/uploads', $root . '/library/FOSSBilling']);
    $filesystem->copy($fixtures . '/load.php.txt', $root . '/load.php');
    $filesystem->copy($fixtures . '/Updater.php.txt', $root . '/library/FOSSBilling/Update.php');
    (new Process(['git', 'apply', '--unsafe-paths', '-p2', dirname(PATH_TESTS) . '/tools/upgrade-bridge/0.8.9-coordination.patch'], $root))->mustRun();
}

test('the intermediate release hands finalization to new code and protects runtime state', function (string $scenario): void {
    $root = sys_get_temp_dir() . '/fb-upgrade-bridge-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();

    try {
        bridgeUpgradeSource($root);
        // The lock protocol stays shared; the historical ZIP reader remains
        // frozen and is exercised by the upgrade scenarios below.
        expect($filesystem->readFile($root . '/core/Update/RuntimeLock.php'))
            ->toBe($filesystem->readFile(PATH_ROOT . '/core/Update/RuntimeLock.php'));
        $process = new Process([PHP_BINARY, PATH_TESTS . '/Fixtures/update-cache-clear.php', $root, $scenario, 'bridge']);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        if ($scenario === 'protected_runtime') {
            expect($result['error'])->toContain('unsafe file path')
                ->and($result['extracted'])->toBeFalse();
        } else {
            expect($result['error'])->toBeNull()
                ->and($result['pending'])->toBeTrue()
                ->and($result['extracted'])->toBeTrue();
        }
        expect($result['locked'])->toBeFalse()->and($result['archives'])->toBe([]);
    } finally {
        $filesystem->remove($root);
    }
})->with(['normal', 'protected_runtime', 'stale_lock', 'before_download', 'during_download']);

test('the released bootstrap gains a compatible request and cron gate', function (): void {
    $root = sys_get_temp_dir() . '/fb-bridge-bootstrap-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $process = null;

    try {
        bridgeUpgradeSource($root);
        $helperSource = $filesystem->readFile($root . '/core/Update/RuntimeLock.php');
        $filesystem->dumpFile($root . '/core/Update/RuntimeLock.php', $helperSource . PHP_EOL . 'file_put_contents(__DIR__ . "/../../helper-loaded", "old");');
        $filesystem->mkdir($root . '/vendor');
        $filesystem->dumpFile($root . '/vendor/autoload.php', '<?php file_put_contents(__DIR__ . "/../autoloaded", "ready"); exit(0);');
        $process = new Process([PHP_BINARY, '-r', 'file_put_contents($argv[1] . "/entering", "ready"); require $argv[1] . "/load.php";', $root], env: ['APP_ENV' => 'prod']);
        $process->setTimeout(5);
        RuntimeLock::exclusively($root, function () use ($process, $root, $helperSource, $filesystem): void {
            $process->start();
            $deadline = microtime(true) + 5;
            while (!is_file($root . '/entering') && $process->isRunning() && microtime(true) < $deadline) {
                usleep(1000);
            }
            $process->wait();
            expect(is_file($root . '/entering'))->toBeTrue()
                ->and($process->getExitCode())->toBe(1)
                ->and($process->getErrorOutput())->toContain('currently applying a core update')
                ->and(is_file($root . '/autoloaded'))->toBeFalse()
                ->and(is_file($root . '/helper-loaded'))->toBeFalse();
            $filesystem->dumpFile($root . '/core/Update/RuntimeLock.php', $helperSource . PHP_EOL . 'file_put_contents(__DIR__ . "/../../helper-loaded", "new");');
        });
        $process->mustRun();
        expect($process->isSuccessful())->toBeTrue()->and(is_file($root . '/autoloaded'))->toBeTrue()
            ->and($filesystem->readFile($root . '/helper-loaded'))->toBe('new');
    } finally {
        $process?->stop();
        $filesystem->remove($root);
    }
});
