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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';

$di['translate']();

$di['console'] = function () use ($di) {
    $container = [
        'console.name' => 'FOSSBilling Console Commands',
        'console.version' => $di['mod_service']('system')->getVersion(),
        'console.allow_namespace' => true,
    ];
    $console = new Application($container['console.name'], $container['console.version']);

    if ($container['console.allow_namespace']) {
        $console->getDefinition()->addOption(
            new InputOption(
                'namespace',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify namespace for the console'
            )
        );
        $console->setDefaultCommand('list');
        $namespace = array_reduce($_SERVER['argv'], function ($carry, $arg) {
            return str_starts_with($arg, '--namespace=') ? substr($arg, strlen('--namespace=')) : $carry;
        }, '');

        if (!empty($namespace)) {
            if (!class_exists($namespace)) {
                return $console;
            }
            $class = new $namespace();
            $class->setDi($di);
            $console->add($class);

            return $console;
        }
    }

    try {
        $modules = $di['mod_service']('extension')->getCoreAndActiveModules();
    } catch (Exception $e) {
        $modules = $di['mod']('extension')->getCoreModules();
    }

    // Dynamically load the commands from the modules
    foreach ($modules as $module) {
        $cap = ucfirst($module);
        $commands = glob(__DIR__ . '/modules/' . $cap . '/Console/*.php');
        foreach ($commands as $command) {
            $commandClass = basename($command, '.php');
            $class = "\\Box\\Mod\\$cap\\Console\\$commandClass";
            if (class_exists($class)) {
                $instance = new $class();
                $instance->setDi($di);
                $console->add($instance);
            }
        }
    }

    return $console;
};

$console = $di['console'];

$console->run();
