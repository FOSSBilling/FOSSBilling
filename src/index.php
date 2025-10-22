<?php

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

// Setting up the debug bar
$debugBar = new DebugBar\StandardDebugBar();
$debugBar['request']->useHtmlVarDumper();
$debugBar['messages']->useHtmlVarDumper();

// PDO collector
$pdoCollector = new DebugBar\DataCollector\PDO\PDOCollector();

// RedBean
$pdoCollector->addConnection($di['pdo'], 'RedBeanPHP');

// Doctrine
$connection = $di['em']->getConnection();
$native = $connection->getNativeConnection();

if ($native instanceof \PDO) {
    $pdoCollector->addConnection($native, 'Doctrine');
}

$debugBar->addCollector($pdoCollector);

$config = FOSSBilling\Config::getConfig();
$config['info']['salt'] = '********';
$config['db'] = array_fill_keys(array_keys($config['db']), '********');

$configCollector = new DebugBar\DataCollector\ConfigCollector($config);
$configCollector->useHtmlVarDumper();

$debugBar->addCollector($configCollector);

// Get the request URL
$url = $_GET['_url'] ?? parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Rewrite for custom pages
if (str_starts_with((string) $url, '/page/')) {
    $url = substr_replace($url, '/custompages/', 0, 6);
}

// Set the final URL
$_GET['_url'] = $url;
$http_err_code = $_GET['_errcode'] ?? null;

$debugBar['time']->startMeasure('session_start', 'Starting / restoring the session');

/*
 * Workaround: Session IDs get reset when using PGs like PayPal because of the `samesite=strict` cookie attribute, resulting in the client getting logged out.
 * Internally the return and cancel URLs get a restore_session GET parameter attached to them with the proper session ID to restore, so we do so here.
 */
if (!empty($_GET['restore_session'])) {
    session_id($_GET['restore_session']);
}

$di['session'];
$debugBar['time']->stopMeasure('session_start');

if (strncasecmp((string) $url, ADMIN_PREFIX, strlen(ADMIN_PREFIX)) === 0) {
    define('ADMIN_AREA', true);
    $appUrl = str_replace(ADMIN_PREFIX, '', preg_replace('/\?.+/', '', (string) $url));
    $app = new Box_AppAdmin([], $debugBar);
} else {
    define('ADMIN_AREA', false);
    $appUrl = $url;
    $app = new Box_AppClient([], $debugBar);
}

$app->setUrl($appUrl);
$app->setDi($di);

$debugBar['time']->startMeasure('translate', 'Setting up translations');
$di['translate']();
$debugBar['time']->stopMeasure('translate');

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
