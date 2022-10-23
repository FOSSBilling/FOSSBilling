<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

require_once __DIR__ .'/bb-load.php';
$di = include __DIR__ .'/bb-di.php';

$di['translate']();

try {
    if ('cli' === PHP_SAPI) {
        echo "\e[33m- Welcome to FOSSBilling.\n";
    }
    $interval = $argv[1] ?? null;
    $service = $di['mod_service']('cron');
    if ('cli' === PHP_SAPI) {
        echo "\e[34mLast executed: ".$service->getLastExecutionTime().".\e[0m";
    }
    $service->runCrons($interval);
} catch (Exception $exception) {
    throw new Exception($exception);
} finally {
    if ('cli' === PHP_SAPI) {
        echo "\e[32mSuccessfully ran the cron jobs.\e[0m";
    } else {
        $admin_prefix = $di['config']['admin_area_prefix'];
        $login_url = $di['url']->link($admin_prefix);
        unset($service, $interval, $di);
        header("Location: $login_url");
    }
}
