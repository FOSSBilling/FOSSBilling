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

require_once __DIR__.'/load.php';
$di = include __DIR__.'/di.php';

$di['translate']();

$di->register(new \CristianG\PimpleConsole\ServiceProvider(), [
    /**
     * Set the console application name. Defaults to 'Console'
     * @param  string
     */
    'console.name'            => 'FOSSBilling Console Commands',
    /**
     * Set the console application version. Defaults to '2.0'
     * @param  string
     */
    'console.version'         => $di['mod_service']('system')->getVersion(),
    /**
     * Set console application list
     * @param  array
     */
    'console.classes'         => function ($di) {
        try {
            $modules = $di['mod_service']('extension')->getCoreAndActiveModules();
        } catch (Exception $e) {
            $modules = $di['mod']('extension')->getCoreModules();
        }
        $fullyQualifiedClassName = [];
        // Dynamically load the commands from the modules
        foreach ($modules as $module) {
            $cap = ucfirst($module);
            $commands = glob(__DIR__.'/modules/'.$cap.'/Console/*.php');
            foreach ($commands as $command) {
                $commandClass = basename($command, '.php');
                // Construct the fully qualified class name
                $fullyQualifiedClassName[] = "\\Box\\Mod\\$cap\\Console\\$commandClass";
            }
        }
        return $fullyQualifiedClassName;
    },
    /**
     * Set namespace command --namespace="\Namespace\Run" to be provided on command
     * @param  bool
     */
    'console.allow_namespace' => FOSSBilling\Config::getProperty('console.allow_namespace', true),
    /**
     * Set your DI new Pimple\Container() for your app to be loaded before execute
     */
    'console.di'              => $di
]);

$console = $di['console'];

$console->run();
