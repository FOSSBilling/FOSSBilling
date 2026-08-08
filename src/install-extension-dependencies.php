#!/usr/bin/env php
<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/*
 * Installs the Composer dependencies of every bundled extension.
 *
 * Extensions carry their own dependencies so that core does not download a
 * payment gateway's SDK for installs that never enable it. This runs from
 * composer install, and can be run by hand after adding an extension.
 */

use Symfony\Component\Filesystem\Path;

if (PHP_SAPI !== 'cli') {
    exit('This script can only be run from the command line.');
}

$vendor = __DIR__ . DIRECTORY_SEPARATOR . 'vendor';
if (!is_file($vendor . DIRECTORY_SEPARATOR . 'autoload.php')) {
    fwrite(STDERR, "Install core's dependencies first.\n");
    exit(1);
}

require $vendor . DIRECTORY_SEPARATOR . 'autoload.php';

// The extension classes are not needed here, only the paths they resolve against.
define('PATH_ROOT', __DIR__);
define('PATH_VENDOR', $vendor);
define('PATH_EXTENSIONS', Path::join(PATH_ROOT, 'extensions'));
define('PATH_CACHE', Path::join(PATH_ROOT, 'data', 'cache'));

$failures = FOSSBilling\Extension\DependencyBootstrap::create()
    ->installMissing(static fn (string $line) => print $line . PHP_EOL);

if ($failures !== []) {
    fwrite(STDERR, PHP_EOL . count($failures) . ' extension(s) could not be prepared.' . PHP_EOL);
    exit(1);
}
