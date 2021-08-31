<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

require_once dirname(__FILE__) . '/bb-load.php';
$di = include dirname(__FILE__) . '/bb-di.php';
$url = $di['request']->getQuery('_url');
$admin_prefix = $di['config']['admin_area_prefix'];
if (strncasecmp($url,$admin_prefix, strlen($admin_prefix)) === 0) {
    $url = str_replace($admin_prefix, '', preg_replace('/\?.+/', '', $url));
    $app = new Box_AppAdmin();
    $app->setUrl($url);
} else {
    $app = new Box_AppClient();
    $app->setUrl($url);
}
$di['translate']();
$app->setDi($di);
print $app->run();
exit(); // disable auto_append_file directive