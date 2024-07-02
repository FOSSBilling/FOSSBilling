<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';

$di['translate']();

// Instantiate ConsoleFactory and create the console application
$factory = new FOSSBilling\ConsoleFactory();
$factory->setDi($di);
$console = $factory->createConsoleApplication();

// Run the console application
$console->run();
