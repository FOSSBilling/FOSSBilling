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
if (str_starts_with($mode, 'handoff-')) {
    $service = new class extends Finalization {
        public bool $pauseReader = false;

        public function acquireRuntimeLock(): void
        {
            if ($this->pauseReader) {
                $this->pauseReader = false;
                file_put_contents(PATH_ROOT . '/handoff.ready', 'restoring reader');
                awaitFile(PATH_ROOT . '/handoff.release');
            }
            parent::acquireRuntimeLock();
        }
    };
    RuntimeLock::acquire(PATH_ROOT);
    $service->acquireRuntimeLock();
    $service->pauseReader = true;

    try {
        $service->withUpdateLock(static function () use ($mode): void {
            if ($mode === 'handoff-failure') {
                throw new RuntimeException('Extraction failed');
            }
        });
    } catch (RuntimeException $e) {
        if ($mode !== 'handoff-failure' || $e->getMessage() !== 'Extraction failed') {
            throw $e;
        }
    }
    RuntimeLock::release(PATH_ROOT);
    file_put_contents(PATH_ROOT . '/tail.ready', 'old updater tail');
    awaitFile(PATH_ROOT . '/tail.release');
    $service->releaseRuntimeLock();
    echo 'complete';
    exit;
}
$service = new Finalization();
if ($mode === 'legacy-timeout') {
    define('DEBUG', false);
    define('PATH_CONFIG', PATH_ROOT . '/config.php');
    (new Symfony\Component\Filesystem\Filesystem())->dumpFile(PATH_CONFIG, '<?php return [];');
    $reader = new Finalization();
    $reader->acquireRuntimeLock();

    try {
        $service->withUpdateLock(static function (): void {
            throw new LogicException('Extraction must not start while the legacy reader is active.');
        }, 0.02);

        throw new LogicException('Expected the legacy-reader drain to time out.');
    } catch (FOSSBilling\Core\Exception\InformationException $e) {
        if (!str_contains($e->getMessage(), 'still finishing')) {
            throw $e;
        }
        echo 'timed out without extraction';
    } finally {
        $reader->releaseRuntimeLock();
    }
    exit;
}
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
