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

require_once dirname(__FILE__).'/bb-load.php';
$di = include dirname(__FILE__).'/bb-di.php';
$url = $di['request']->getQuery('_url');
$admin_prefix = $di['config']['admin_area_prefix'];
if (0 === strncasecmp($url, $admin_prefix, strlen($admin_prefix))) {
    $url = str_replace($admin_prefix, '', preg_replace('/\?.+/', '', $url));
    $app = new Box_AppAdmin();
    $app->setUrl($url);
} else {
    $app = new Box_AppClient();
    $app->setUrl($url);
}
$di['translate']();
$app->setDi($di);
echo $app->run();
exit(); // disable auto_append_file directive
