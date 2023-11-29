<?php

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Environment;
use Symfony\Component\Filesystem\Filesystem;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

const PATH_ROOT = __DIR__;
const PATH_VENDOR = PATH_ROOT . DIRECTORY_SEPARATOR . 'vendor';
const PATH_LIBRARY = PATH_ROOT . DIRECTORY_SEPARATOR . 'library';
const PATH_THEMES = PATH_ROOT . DIRECTORY_SEPARATOR . 'themes';
const PATH_MODS = PATH_ROOT . DIRECTORY_SEPARATOR . 'modules';
const PATH_LANGS = PATH_ROOT . DIRECTORY_SEPARATOR . 'locale';
const PATH_UPLOADS = PATH_ROOT . DIRECTORY_SEPARATOR . 'uploads';
const PATH_CONFIG = PATH_ROOT . DIRECTORY_SEPARATOR . 'config.php';

/*
 * Check configuration exists, and is valid.
 */
function checkConfig()
{
    $filesystem = new Filesystem();
    // Check if configuration is available, and redirect to installer if not.
    if (!$filesystem->exists(PATH_CONFIG)) {
        if ($filesystem->exists('install/index.php')) {
            // Build the base URL for the installation, including the protocol and hostname.
            $base_url = 'http' . ((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

            // Append the directory name to the base URL.
            $base_url .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

            // Use the base URL to redirect to the installer, sending HTTP 307 to indicate a temporary redirect.
            header('Location: ' . $base_url . '/install/index.php', true, 307);
        } else {
            throw new Exception('The FOSSBilling configuration file is empty or invalid.', 3);
        }
    }
}

/*
 * Check if the installer is present.
 */
function checkInstaller()
{
    if (!Environment::isProduction()) {
        return;
    }

    $filesystem = new Filesystem();

    // Check if /install directory still exists after installation has been completed.
    if ($filesystem->exists(PATH_CONFIG) && $filesystem->exists('install/install.php')) {
        // Throw exception only if debug mode is NOT enabled.
        $config = require PATH_CONFIG;
        if (!$config['debug_and_monitoring']['debug']) {
            throw new Exception('For security reasons, you have to delete the install directory before you can use FOSSBilling.', 2);
        }
    }

    // If the config file exists and not install.php, but the install folder does, perform some cleanup.
    if ($filesystem->exists(PATH_CONFIG) && $filesystem->exists('install') && !DEBUG) {
        $filesystem->remove('install');
    }
}

/*
 * Check if any legacy BoxBilling/FOSSBilling files are present.
 */
function checkLegacyFiles()
{
    $filesystem = new Filesystem();

    // Detect old files and folders from legacy BoxBilling or FOSSBilling installations.
    $toCheck = ['bb-data', 'bb-library', 'bb-locale', 'bb-modules', 'bb-themes', 'bb-uploads', 'bb-cron.php', 'bb-di.php', 'bb-load.php', 'bb-config.php'];
    $legacyFound = null;
    foreach ($toCheck as $path) {
        if ($filesystem->exists($path)) {
            $legacyFound = true;

            break;
        }
    }

    // Show an error if any legacy files/folders found.
    if ($legacyFound) {
        throw new Exception('Migration from BoxBilling is required.', 4);
    }
}

/*
 * Check hard requirements such as PHP version, Composer packages, etc.
 */
function checkRequirements()
{
    // Check for Composer packages / vendor folder.
    if (!file_exists(PATH_VENDOR)) {
        throw new Exception('The composer packages are missing.', 1);
    }
}

/*
 * Check if SSL required, and enforce if so.
 */
function checkSSL()
{
    $config = include PATH_CONFIG;
    if (isset($config['security']['force_https']) && $config['security']['force_https'] && !FOSSBilling\Environment::isCLI()) {
        if (!FOSSBilling\Tools::isHTTPS()) {
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $url);
            exit;
        }
    }
}

/*
 * Check the web server config.
 */
function checkWebServer()
{
    $filesystem = new Filesystem();

    // Check for missing required .htaccess on Apache and Apache-compatible web servers.
    $isApache = function_exists('apache_get_version') ? true : false;
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
    if ($isApache or (stripos($serverSoftware, 'apache') !== false) or stripos($serverSoftware, 'litespeed') !== false) {
        if (!$filesystem->exists('.htaccess')) {
            throw new Exception('Missing .htaccess file', 5);
        }
    }
}

/*
 * Error handler.
 */
function errorHandler(int $number, string $message, string $file, int $line)
{
    // Just some housekeeping to ensure a few things we rely on are loaded.
    if (!class_exists('\\' . \FOSSBilling\ErrorPage::class)) {
        require_once PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'ErrorPage.php';
    }

    if (!class_exists('\\' . \FOSSBilling\SentryHelper::class)) {
        require_once PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'SentryHelper.php';
    }

    // If it's an exception, handle it. Otherwise we don't need to do anything as PHP will log it for us.
    if ($number === E_RECOVERABLE_ERROR) {
        exceptionHandler(new ErrorException($message, $number, 0, $file, $line));
    }

    return false;
}

/*
 * Exception handler.
 */
function exceptionHandler(Exception|Error $e)
{
    if (getenv('APP_ENV') === 'test') {
        echo $e->getMessage() . PHP_EOL;

        return;
    } else {
        error_log($e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    }

    $message = htmlspecialchars($e->getMessage());

    if (defined('API_MODE')) {
        $code = $e->getCode() ?: 9998;
        $result = ['result' => null, 'error' => ['message' => $message, 'code' => $code]];
        echo json_encode($result);

        return false;
    }

    if (defined('DEBUG') && DEBUG && file_exists(PATH_VENDOR)) {
        /**
         * If advanced debugging is enabled, print Whoops instead of our error page.
         * flip/whoops documentation: https://github.com/filp/whoops/blob/master/docs/API%20Documentation.md.
         */
        $whoops = new Run();
        $prettyPage = new PrettyPageHandler();
        $prettyPage->setPageTitle('An error ocurred');
        $prettyPage->addDataTable('FOSSBilling environment', [
            'PHP Version' => PHP_VERSION,
            'Error code' => $e->getCode(),
            'Instance ID' => INSTANCE_ID ?? 'Unknown',
        ]);
        $whoops->pushHandler($prettyPage);
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);

        echo $whoops->handleException($e);
    } else {
        $errorPage = new \FOSSBilling\ErrorPage();
        $errorPage->generatePage($e->getCode(), $message);
    }
}

/*
 *
 * Initialize App.
 *
 */

// Define custom error handlers.
set_exception_handler('exceptionHandler');
set_error_handler('errorHandler');

// Enabled during setup, is then overridden once we have loaded the config.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Check hard requirements.
checkRequirements();

// Requirements met - load required packages/files.
require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';

// Check web server and web server settings.
checkWebServer();

// Check for legacy BoxBilling/FOSSBilling files.
checkLegacyFiles();

// Check config exists.
checkConfig();

// All seems good, so load the config file.
$config = require PATH_CONFIG;

// Config loaded - set globals and relevant settings.
date_default_timezone_set($config['i18n']['timezone'] ?? 'UTC');
define('ADMIN_PREFIX', $config['admin_area_prefix']);
define('DEBUG', (bool) $config['debug_and_monitoring']['debug']);
define('PATH_DATA', $config['path_data']);
define('PATH_CACHE', PATH_DATA . DIRECTORY_SEPARATOR . 'cache');
define('PATH_LOG', PATH_DATA . DIRECTORY_SEPARATOR . 'log');
define('SYSTEM_URL', $config['url']);
if (!empty($config['info']['instance_id'])) {
    define('INSTANCE_ID', $config['info']['instance_id']);
} else {
    define('INSTANCE_ID', 'Unknown');
}

// Initial setup and checks passed, now we setup our custom autoloader.
include PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'Autoloader.php';
$loader = new FOSSBilling\AutoLoader();
$loader->register();

// Now that the config file is loaded, we can enable Sentry
\FOSSBilling\SentryHelper::registerSentry($config);

// Verify the installer was removed.
checkInstaller();

// Check if SSL required, and enforce if so.
checkSSL();

// Set error and exception handlers, and default logging settings.
ini_set('log_errors', '1');
ini_set('html_errors', false);
ini_set('error_log', PATH_LOG . DIRECTORY_SEPARATOR . 'php_error.log');
error_reporting(E_ALL);

if (DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
