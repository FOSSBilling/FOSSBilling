<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';

use FOSSBilling\Environment;

$di['translate']();

try {
    $interval = $argv[1] ?? null;
    $service = $di['mod_service']('cron');

    if (Environment::isCLI()) {
        echo "\e[33m- Welcome to FOSSBilling.\n";
        echo "\e[34mLast executed: " . $service->getLastExecutionTime() . ".\e[0m";
    }

    $service->runCrons($interval);
} catch (Exception $exception) {
    throw new Exception($exception);
} finally {
    if (Environment::isCLI()) {
        echo "\e[32mSuccessfully ran the cron jobs.\e[0m";
    }
}
