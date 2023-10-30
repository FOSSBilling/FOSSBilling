<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
require_once __DIR__ . '/load.php';
$di = include __DIR__ . '/di.php';

$url = $_GET['_url'] ?? $_SERVER['PATH_INFO'] ?? '';
$http_err_code = $_GET['_errcode'] ?? null;

if (strncasecmp($url, ADMIN_PREFIX, strlen(ADMIN_PREFIX)) === 0) {
    $appUrl = str_replace(ADMIN_PREFIX, '', preg_replace('/\?.+/', '', $url));
    $app = new Box_AppAdmin();
} else {
    $appUrl = $url;
    $app = new Box_AppClient();
}

if ($url === '/run-patcher') {
    $patcher = new FOSSBilling\UpdatePatcher();
    $patcher->setDi($di);

    if (!$patcher->isOutdated()) {
        exit('There are no patches to apply');
    }

    try {
        $patcher->applyConfigPatches();
        $patcher->applyCorePatches();
        exit('Patches have been applied');
    } catch (\Exception $e) {
        exit('An error occurred while attempting to apply patches: <br>' . $e->getMessage());
    }
}

$app->setUrl($appUrl);
$di['translate']();
$app->setDi($di);

// If HTTP error code has been passed, handle it.
if (!is_null($http_err_code)) {
    switch ($http_err_code) {
        case '404':
            $e = new FOSSBilling\Exception('Page :url not found', [':url' => $url], 404);
            echo $app->show404($e);

            break;
        default:
            $http_err_code = intval($http_err_code);
            http_response_code($http_err_code);
            $e = new FOSSBilling\Exception('HTTP Error :err_code occurred while attempting to load :url', [':err_code' => $http_err_code, ':url' => $url], $http_err_code);
            echo $app->render('error', ['exception' => $e]);
    }
    exit;
}

// If no HTTP error passed, run the app.
echo $app->run();
exit;
