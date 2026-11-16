<?php

declare(strict_types=1);

use FOSSBilling\Core\Update\Finalization;
use FOSSBilling\Core\Update\RuntimeLock;

require dirname(__DIR__, 2) . '/src/vendor/autoload.php';
define('PATH_ROOT', $argv[1]);
define('PATH_DATA', PATH_ROOT . '/data');
$mode = $argv[2];
$name = $argv[3];

function awaitFile(string $path): void
{
    $deadline = microtime(true) + 8;
    while (!is_file($path)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for test barrier ' . $path);
        }
        usleep(1000);
    }
}

file_put_contents(PATH_ROOT . '/' . $name . '.entering', 'entering');
$service = new Finalization();
if ($mode !== 'legacy-cron') {
    RuntimeLock::acquire(PATH_ROOT);
}
$service->acquireRuntimeLock();
if ($mode === 'updater') {
    $service->withUpdateLock(static function () use ($name): void {
        file_put_contents(PATH_ROOT . '/' . $name . '.ready', 'exclusive');
        awaitFile(PATH_ROOT . '/' . $name . '.release');
    });
} else {
    file_put_contents(PATH_ROOT . '/' . $name . '.ready', 'reader');
    awaitFile(PATH_ROOT . '/' . $name . '.release');
}
$service->releaseRuntimeLock();
RuntimeLock::release(PATH_ROOT);
echo 'complete';
