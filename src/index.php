<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

require __DIR__ . DIRECTORY_SEPARATOR . 'load.php';
global $di;

use Symfony\Component\HttpFoundation\Request;

// Setting up the debug bar
$debugBar = new DebugBar\StandardDebugBar();
$debugBar['request']->useHtmlVarDumper();
$debugBar['messages']->useHtmlVarDumper();

$config = FOSSBilling\Config::getConfig();
$config['info']['salt'] = '********';
$config['db'] = array_fill_keys(array_keys($config['db']), '********');

$configCollector = new DebugBar\DataCollector\ConfigCollector($config);
$configCollector->useHtmlVarDumper();

$debugBar->addCollector($configCollector);

// Get request information.
$request = Request::createFromGlobals();
$requestPath = $request->getPathInfo() ?: '/';
$request->query->set('_url', $requestPath); // TODO: Legacy support for _url.
$httpErrorCode = $request->query->get('_errcode'); // TODO: Legacy support for _errcode.
$restoreSession = $request->query->get('restore_session'); // TODO: Legacy support for restore_session.

$debugBar['time']->startMeasure('session_start', 'Starting / restoring the session');

/*
 * Workaround: Session IDs get reset when using PGs like PayPal because of the `samesite=strict` cookie attribute, resulting in the client getting logged out.
 * Internally the return and cancel URLs get a restore_session GET parameter attached to them with the proper session ID to restore, so we do so here.
 */
if (!empty($restoreSession)) {
    session_id($restoreSession);
}

$di['session'];
$debugBar['time']->stopMeasure('session_start');

$app = new \FOSSBilling\App($request, $debugBar);
$app->setDi($di);

$debugBar['time']->startMeasure('translate', 'Setting up translations');
$di['translate']();
$debugBar['time']->stopMeasure('translate');

// TODO: Legacy error handling - if HTTP error code has been passed, handle it.
if (!is_null($httpErrorCode)) {
    switch ($httpErrorCode) {
        case '404':
            $e = new FOSSBilling\Exception('Page :url not found', [':url' => $requestPath], 404);
            echo $app->show404($e);

            break;
        default:
            $httpErrorCode = intval($httpErrorCode);
            http_response_code($httpErrorCode);
            $e = new FOSSBilling\Exception('HTTP Error :err_code occurred while attempting to load :url', [':err_code' => $httpErrorCode, ':url' => $requestPath], $httpErrorCode);
            echo $app->render('error', ['exception' => $e]);
    }
    exit;
}

// If no HTTP error passed, run the app.
echo $app->run();
exit;
