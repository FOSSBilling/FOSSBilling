<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/vendor/autoload.php';
require dirname(__DIR__, 2) . '/src/core/Filesystem/ZipExtractor.php';

if (extension_loaded('zip')) {
    throw new RuntimeException('The fallback memory test must run without ext-zip.');
}
if (ini_get('memory_limit') !== '48M') {
    throw new RuntimeException('The fallback memory test must run with a 48M memory limit.');
}

(new FOSSBilling\Core\Filesystem\ZipExtractor())->extract($argv[1] . '/update.zip', $argv[1] . '/destination', static function (): void {});
