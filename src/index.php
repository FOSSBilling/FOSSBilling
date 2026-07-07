<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

require __DIR__ . DIRECTORY_SEPARATOR . 'load.php';
global $di;

use DebugBar\DataCollector\TimeDataCollector;
use FOSSBilling\Http\RequestFactory;

$config = FOSSBilling\Config::getConfig();
$debugBar = null;
$timeCollector = null;
/* @var Symfony\Component\HttpFoundation\Request $request */
global $request;

if ((bool) ($config['debug_and_monitoring']['debug'] ?? false)) {
    // Setting up the debug bar
    $debugBar = new DebugBar\StandardDebugBar();
    $timeCollector = $debugBar->getCollector('time');

    if (!$timeCollector instanceof TimeDataCollector) {
        throw new RuntimeException('Time collector not found in debug bar.');
    }

    // PDO collector
    $pdoCollector = new DebugBar\DataCollector\PDO\PDOCollector();

    // RedBean
    $pdoCollector->addConnection($di['pdo'], 'RedBeanPHP');

    // Doctrine
    $connection = $di['em']->getConnection();
    $native = $connection->getNativeConnection();

    if ($native instanceof PDO) {
        $pdoCollector->addConnection(new DebugBar\DataCollector\PDO\TraceablePDO($native), 'Doctrine');
    }

    $debugBar->addCollector($pdoCollector);

    $config['info']['salt'] = '********';
    $config['db'] = array_fill_keys(array_keys($config['db']), '********');

    $configCollector = new DebugBar\DataCollector\ConfigCollector($config);

    $debugBar->addCollector($configCollector);
}

$url = RequestFactory::normalizeRoutePath($request);
$http_err_code = $request->query->get('_errcode');

$timeCollector?->startMeasure('session_start', 'Starting / restoring the session');

/*
 * Workaround: Session IDs get reset when using PGs like PayPal because of the `samesite=strict` cookie attribute, resulting in the client getting logged out.
 * The return and cancel URLs include a signed restore_token that contains the session ID. We validate and extract it here.
 */
if ($request->query->has('restore_token')) {
    $restoreToken = $request->query->get('restore_token');
    $restoredSessionId = is_string($restoreToken) ? FOSSBilling\Tools::validateSessionRestoreToken($restoreToken) : null;
    if ($restoredSessionId !== null) {
        session_id($restoredSessionId);
    }
}

$di['session']->getId();
$timeCollector?->stopMeasure('session_start');

if (strncasecmp($url, ADMIN_PREFIX, strlen(ADMIN_PREFIX)) === 0) {
    define('ADMIN_AREA', true);
    $urlWithoutQueryString = parse_url($url, PHP_URL_PATH) ?? $url;
    $adminRelativeUrl = str_replace(ADMIN_PREFIX, '', (string) $urlWithoutQueryString);
    $appUrl = $adminRelativeUrl;
    $app = new Box_AppAdmin([], $debugBar);
} else {
    define('ADMIN_AREA', false);
    $appUrl = $url;
    $app = new Box_AppClient([], $debugBar);
}

$app->setUrl($appUrl);
$app->setDi($di);

$timeCollector?->startMeasure('translate', 'Setting up translations');
$di['translate']();
$timeCollector?->stopMeasure('translate');

// If HTTP error code has been passed, handle it.
if (!is_null($http_err_code)) {
    $http_err_code = intval($http_err_code);
    switch ($http_err_code) {
        case 404:
            $e = new FOSSBilling\Exception('Page :url not found', [':url' => $url], 404);
            $response = $app->show404($e);

            break;
        default:
            $e = new FOSSBilling\Exception('HTTP Error :err_code occurred while attempting to load :url', [':err_code' => $http_err_code, ':url' => $url], $http_err_code);
            $response = $app->errorResponse($e);
    }
} else {
    // If no HTTP error passed, run the app.
    $response = $app->run();
}

$di['cookie_queue']->applyToResponse($response);

emitResponse($response);
