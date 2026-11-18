<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/vendor/autoload.php';
require dirname(__DIR__, 2) . '/src/core/Filesystem/ZipExtractor.php';

if (extension_loaded('zip')) {
    throw new RuntimeException('The fallback memory test must run without ext-zip.');
}

(new FOSSBilling\Core\Filesystem\ZipExtractor())->extract($argv[1] . '/update.zip', $argv[1] . '/destination', static function (): void {});
