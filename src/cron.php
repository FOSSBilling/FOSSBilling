<?php
/**
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
require_once dirname(__FILE__).'/load.php';
$di = include dirname(__FILE__).'/di.php';

$di['translate']();

try {
    if ('cli' == php_sapi_name()) {
        echo "\e[33m- Welcome to BoxBilling.\n";
    }
    $interval = $argv[1] ?? null;
    $service = $di['mod_service']('cron');
    if ('cli' == php_sapi_name()) {
        echo "\e[34mLast executed: ".$service->getLastExecutionTime().".\e[0m";
    }
    $service->runCrons($interval);
} catch (Exception $exception) {
    throw new Exception($exception);
} finally {
    if ('cli' == php_sapi_name()) {
        echo "\e[32mSuccessfully ran the cron jobs.\e[0m";
    } else {
        $login_url = $di['url']->link('bb-admin');
        unset($service, $interval, $di);
        header("Location: $login_url");
    }
}
