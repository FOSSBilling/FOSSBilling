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
const PATH_DATA = PATH_ROOT . DIRECTORY_SEPARATOR . 'data';
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
    $filesystem = new Filesystem();

    // Check if /install directory still exists after installation has been completed.
    if ($filesystem->exists(PATH_CONFIG) && $filesystem->exists('install/install.php') && Environment::isProduction()) {
        // Throw exception only if debug mode is NOT enabled.
        $config = require PATH_CONFIG;
        if (!$config['debug_and_monitoring']['debug']) {
            throw new Exception('For security reasons, you have to delete the install directory before you can use FOSSBilling.', 2);
        }
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
    if ($number === E_RECOVERABLE_ERROR) {
        exceptionHandler(new ErrorException($message, $number, 0, $file, $line));
    } else {
        error_log($number . ' ' . $message . ' ' . $file . ' ' . $line);
    }

    return false;
}

/*
 * Exception handler.
 */
function exceptionHandler($e)
{
    if (!class_exists('\FOSSBilling\ErrorPage')) {
        require_once PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'ErrorPage.php';
    }

    // If the trans function isn't setup, define a "polyfill" for it.
    \FOSSBilling\ErrorPage::setupTrans();

    // Let Sentry capture the exception and then send it
    \Sentry\captureException($e);

    $message = htmlspecialchars($e->getMessage());
    if (getenv('APP_ENV') === 'test') {
        echo $message . PHP_EOL;

        return;
    }
    error_log($message);

    if (defined('BB_MODE_API')) {
        $code = $e->getCode() ?: 9998;
        $result = ['result' => null, 'error' => ['message' => $message, 'code' => $code]];
        echo json_encode($result);

        return false;
    }

    if (defined('BB_DEBUG') && BB_DEBUG && file_exists(PATH_VENDOR)) {
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

function registerSentry(array $config): void
{
    $sentryDSN = '--replace--this--during--release--process--';

    // Registers Sentry for error reporting if enabled.
    $options = [
        'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
            if ($hint) {
                $errorInfo = \FOSSBilling\ErrorPage::getCodeInfo($hint->exception->getCode());

                if (!$errorInfo['report']) {
                    return null;
                }

                if ($hint->exception instanceof \FOSSBilling\InformationException) {
                    return null;
                }
            }

            return $event;
        },

        'environment' => Environment::getCurrentEnvironment(),
        'release' => FOSSBilling\Version::VERSION,
    ];

    /**
     * Here we validate that the DSN is correctly set and that error reporting is enabled before passing it off to the Sentry SDK.
     * It may look a bit odd, but the DSN placeholder value here is split into two strings and concatenated so we can easily perform a `sed` replacement of the placeholder without it effecting this check
     */
    /* @phpstan-ignore-next-line (The empty check is added to catch a possible edge-case where CI may the placeholder with an empty string) */
    if ($config['debug_and_monitoring']['report_errors'] && $sentryDSN !== '--replace--this--' . 'during--release--process--' && !empty($sentryDSN)) {
        // Per Sentry documentation, not setting this results in the SDK simply not sending any information.
        $options['dsn'] = $sentryDSN;
    };

    // If the system URL is correctly set, we can get the UUID for this instance. Otherwise, let Sentry try to come up with one
    if (!empty(BB_URL)) {
        $options['server_name'] = FOSSBilling\Instance::getInstanceID();
    }

    \Sentry\init($options);
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
define('BB_DEBUG', $config['debug_and_monitoring']['debug']);
define('BB_URL', $config['url']);
define('PATH_CACHE', $config['path_data'] . DIRECTORY_SEPARATOR . 'cache');
define('PATH_LOG', $config['path_data'] . DIRECTORY_SEPARATOR . 'log');
define('BB_SSL', str_starts_with($config['url'], 'https'));
define('ADMIN_PREFIX', $config['admin_area_prefix']);
define('BB_URL_API', $config['url'] . 'api/');

// Initial setup and checks passed, now we setup our custom autoloader.
include PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'Autoloader.php';
$loader = new FOSSBilling\AutoLoader();
$loader->register();

// Now that the config file is loaded, we can enable Sentry
registerSentry($config);

// Verify the installer was removed.
checkInstaller();

// Check if SSL required, and enforce if so.
checkSSL();

// Set error and exception handlers, and default logging settings.
ini_set('log_errors', '1');
ini_set('html_errors', false);
ini_set('error_log', PATH_LOG . DIRECTORY_SEPARATOR . 'php_error.log');
if ($config['debug_and_monitoring']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_RECOVERABLE_ERROR);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
