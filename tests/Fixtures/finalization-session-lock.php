<?php

declare(strict_types=1);

use FOSSBilling\Core\Security\Session;
use FOSSBilling\Core\Update\Finalization;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/src/vendor/autoload.php';
define('PATH_DATA', $argv[1]);
$filesystem = new Filesystem();
session_save_path(PATH_DATA);
$session = new SymfonySession(new NativeSessionStorage(['use_cookies' => false, 'use_strict_mode' => false, 'cache_limiter' => '']));
$session->setId('finalization-lock-review');
$finalization = new Finalization();
if (($argv[2] ?? '') === 'peer') {
    $finalization->acquireRuntimeLock();
    $filesystem->dumpFile(PATH_DATA . '/ready', 'ready');
    $session->start();
    $filesystem->dumpFile(PATH_DATA . '/saved', $session->get('value'));
    $session->save();
    $finalization->releaseRuntimeLock();
    exit;
}
$session->start();
$session->set('value', 'saved-value');
$finalization->setDi(new Pimple\Container(['session' => new Session($session)]));
$finalization->acquireRuntimeLock();
$peer = new Process([PHP_BINARY, __FILE__, PATH_DATA, 'peer']);
$peer->start();

try {
    $deadline = microtime(true) + 5;
    while (!$filesystem->exists(PATH_DATA . '/ready')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Peer did not acquire its runtime lock.');
        }
        usleep(1000);
    }
    (new ReflectionMethod($finalization, 'withFinalizationLock'))->invoke($finalization, function () use ($filesystem): void {
        if ($filesystem->readFile(PATH_DATA . '/saved') !== 'saved-value') {
            throw new RuntimeException('Session was not saved before finalization.');
        }
    });
    $peer->wait();
    if (!$peer->isSuccessful()) {
        throw new RuntimeException($peer->getErrorOutput());
    }
    echo 'complete';
} finally {
    $peer->stop();
    $finalization->releaseRuntimeLock();
}
