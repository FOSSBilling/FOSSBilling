<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


require_once dirname(__FILE__) . '/bb-load.php';
$di = include dirname(__FILE__) . '/bb-di.php';

$di['translate']();

try {
    $interval = isset($argv[1]) ? $argv[1] : null;
    $service = $di['mod_service']('cron');
    $service->runCrons($interval);
 } catch (Exception $exception) {
    throw new Expection($exception);
 } finally {
    if (php_sapi_name() == 'cli') {
        print ("\e[32mSuccessfully ran the cron jobs.\e[0m");
    } else {
        $login_url = $di['url']->link('bb-admin');
        unset($service, $interval, $di);
        header("Location: $login_url");
    }
 }
