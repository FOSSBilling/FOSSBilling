<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE.
 */

require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';

$url = $di['request']->getQuery('_url') ?? '';
$http_err_code = $di['request']->getQuery('_errcode') ?? null;

$admin_prefix = $di['config']['admin_area_prefix'];
if (0 === strncasecmp($url, $admin_prefix, strlen($admin_prefix))) {
    $appUrl = str_replace($admin_prefix, '', preg_replace('/\?.+/', '', $url));
    $app = new Box_AppAdmin();
} else {
    $appUrl = $url;
    $app = new Box_AppClient();
}

$app->setUrl($appUrl);
$di['translate']();
$app->setDi($di);

// If HTTP error code has been passed, handle it.
if (!is_null($http_err_code)) {
    switch ($http_err_code) {
        case '404':
            $e = new Box_Exception('Page :url not found', [':url' => $url], 404);
            echo $app->show404($e);
            break;
        default:
            http_response_code($http_err_code);
            $e = new Box_Exception('HTTP Error :err_code occured attempting to load :url', [':err_code' => $http_err_code, ':url' => $url], $http_err_code);
            echo $app->render('error', ['exception' => $e]);
    }
    exit;
}

// If no HTTP error passed, run the app.
echo $app->run();
exit;
