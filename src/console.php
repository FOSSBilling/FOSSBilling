#!/usr/bin/env php
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
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';

use Symfony\Component\Console\Application;

$di['translate']();

$application = new Application();

// Setting the application constraints
$application->setName('FOSSBilling');
$application->setVersion($di['mod_service']('system')->getVersion());

$modules = $di['mod']('extension')->getCoreModules();

// Check if the config file exists. If it does, the database is likely already initialized and this will work.
if (file_exists(PATH_CONFIG)) {
    // Try to load the modules from the database. If this fails, the database might not initialized yet. We will use the list of the core modules instead.
    try {
        $modules = $di['mod_service']('extension')->getCoreAndActiveModules();
    } catch (Exception $e) {
        // Do nothing
    }
}

// Dynamically load the commands from the modules
foreach ($modules as $module) {
    // Our manifests declare the names in lowercase, but the module directories start with an uppercase letter.
    $cap = ucfirst($module);

    $commands = glob(__DIR__ . '/modules/' . $cap . '/Commands/*.php');

    foreach ($commands as $command) {
        $command = basename($command, '.php');
        $class = 'Box\\Mod\\' . $cap . '\\Commands\\' . $command;

        $command = new $class();
        $command->setDi($di);
        $application->add($command);
    }
}

$application->run();
