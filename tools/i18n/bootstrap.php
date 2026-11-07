<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Install translation tooling dependencies: composer install --working-dir=tools/i18n\n");
    exit(1);
}
require_once $autoload;
