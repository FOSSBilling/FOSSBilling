<?php

declare(strict_types=1);

use FOSSBilling\Core\Update\RuntimeLock;

require dirname(__DIR__, 2) . '/src/core/Update/RuntimeLock.php';
$root = $argv[1];
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
file_put_contents($root . '/' . $name . '.entering', 'entering');
if ($argv[2] === 'updater') {
    RuntimeLock::exclusively($root, static function () use ($root, $name): void {
        file_put_contents($root . '/' . $name . '.ready', 'exclusive');
        awaitFile($root . '/' . $name . '.release');
    });
} else {
    RuntimeLock::acquire($root);
    file_put_contents($root . '/' . $name . '.ready', 'reader');
    awaitFile($root . '/' . $name . '.release');
    RuntimeLock::release($root);
}
echo 'complete';
