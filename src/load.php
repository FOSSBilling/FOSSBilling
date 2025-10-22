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

use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\SentryHelper;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/*
 * Check if the installer is present.
 */
function checkInstaller(): void
{
    global $filesystem;

    // Ignore this check if not in production environment.
    if (!Environment::isProduction()) {
        return;
    }

    // Check if /install directory still exists after installation has been completed.
    if ($filesystem->exists(PATH_CONFIG) && $filesystem->exists(Path::join('install', 'install.php'))) {
        // Throw exception only if debug mode is NOT enabled.
        if (!empty(Config::getProperty('debug_and_monitoring.debug'))) {
            throw new Exception('For security reasons, you have to delete the install directory before you can use FOSSBilling.', 2);
        }
    }

    // If the config file exists and not install.php, but the install folder does, perform some cleanup.
    if ($filesystem->exists(PATH_CONFIG) && $filesystem->exists(Path::normalize('install')) && !DEBUG) {
        $filesystem->remove('install');
    }
}

/*
 * Check if any legacy BoxBilling/FOSSBilling files are present.
 */
function checkLegacyFiles(): void
{
    global $filesystem;

    // Detect old files and folders from legacy BoxBilling or FOSSBilling installations.
    $toCheck = ['bb-data', 'bb-library', 'bb-locale', 'bb-modules', 'bb-themes', 'bb-uploads', 'bb-cron.php', 'bb-di.php', 'bb-load.php', 'bb-config.php'];
    $legacyFound = null;
    foreach ($toCheck as $path) {
        if ($filesystem->exists(Path::normalize($path))) {
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
 * Check if SSL required, and enforce if so.
 */
function checkSSL(): void
{
    global $request;

    if (!empty(Config::getProperty('security.force_https')) && Config::getProperty('security.force_https') && !Environment::isCLI()) {
        if (!Tools::isHTTPS()) {
            header('Location: https://' . $request->getHost() . $request->getRequestUri());
            exit;
        }
    }
}

/*
 * Check if the update patcher needs to be run.
 */
function checkUpdatePatcher(): void
{
    global $di, $filesystem, $request;

    $version = FOSSBilling\Version::VERSION;
    if ($di['cache']->getItem('updatePatcher')->isHit() && $version === $di['cache']->getItem('updatePatcher')->get()) {
        exit('The update patcher has already been run for this version.');
    }

    if ($request->getPathInfo() == '/run-patcher' || $request->query->get('_url') === '/run-patcher') {
        $patcher = new FOSSBilling\UpdatePatcher();
        $patcher->setDi($di);

        try {
            $patcher->applyConfigPatches();
            $patcher->applyCorePatches();

            // Clear the file cache after applying patches.
            $filesystem->remove(PATH_CACHE);
            $filesystem->mkdir(PATH_CACHE);

            $di['cache']->getItem('updatePatcher')->set(FOSSBilling\Version::VERSION);

            exit('Any missing config migrations or database patches have been applied and the cache has been cleared.');
        } catch (Exception $e) {
            exit("An error occurred while attempting to apply patches: <br>{$e->getMessage()}.");
        }
    }
}

/*
 * Check the web server config.
 */
function checkWebServer(): void
{
    global $filesystem;

    // Check for missing required .htaccess on Apache and Apache-compatible web servers.
    $webServer = SentryHelper::estimateWebServer();
    if ($webServer === 'Apache' || $webServer === 'Litespeed') {
        if (!$filesystem->exists('.htaccess')) {
            throw new Exception('Missing .htaccess file', 5);
        }
    }
}

/*
 * Error handler.
 */
function errorHandler(int $number, string $message, string $file, int $line): bool
{
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
    global $filesystem;

    if (Environment::isTesting()) {
        echo $e->getMessage() . PHP_EOL;

        return;
    } else {
        error_log("{$e->getMessage()} at {$e->getFile()} : {$e->getLine()}");
    }

    $message = htmlspecialchars($e->getMessage());

    if (defined('API_MODE')) {
        $code = $e->getCode() ?: 9998;
        $result = ['result' => null, 'error' => ['message' => $message, 'code' => $code]];
        echo json_encode($result);

        return false;
    }

    if (defined('DEBUG') && DEBUG && $filesystem->exists(PATH_VENDOR)) {
        /**
         * If advanced debugging is enabled, print Whoops instead of our error page.
         * filp/whoops documentation: https://github.com/filp/whoops/blob/master/docs/API%20Documentation.md.
         */
        $whoops = new Run();
        $prettyPage = new PrettyPageHandler();
        $prettyPage->setPageTitle('An error occurred');
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
        $errorPage = new FOSSBilling\ErrorPage();
        $errorPage->generatePage($e->getCode(), $message);
    }
}

/*
 * Pre-initialization.
 */
function preInit(): void
{
    // Define root path.
    define('PATH_ROOT', __DIR__);

    // Check vendor folder exists and load Composer autoloader.
    define('PATH_VENDOR', PATH_ROOT . DIRECTORY_SEPARATOR . 'vendor');
    if (!file_exists(PATH_VENDOR)) {
        throw new Exception('The composer packages are missing.', 1);
    }
    require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';

    // Define global paths.
    define('PATH_LIBRARY', Path::join(PATH_ROOT, 'library'));
    define('PATH_THEMES', Path::join(PATH_ROOT, 'themes'));
    define('PATH_MODS', Path::join(PATH_ROOT, 'modules'));
    define('PATH_LANGS', Path::join(PATH_ROOT, 'locale'));
    define('PATH_UPLOADS', Path::join(PATH_ROOT, 'uploads'));
    define('PATH_CONFIG', Path::join(PATH_ROOT, 'config.php'));

    // Load required FOSSBilling libraries.
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'ErrorPage.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'SentryHelper.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Environment.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Config.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Tools.php');
}

/*
 * Initialize the application.
 */
function init(): void
{
    // Define custom error handlers.
    set_exception_handler(exceptionHandler(...));
    set_error_handler(errorHandler(...));

    // Initialize required Symfony components.
    global $filesystem, $request;
    $filesystem = new Filesystem();
    $request = Request::createFromGlobals();

    // Check config exists, redirecting to installer or throwing an exception if not.
    if (!$filesystem->exists(PATH_CONFIG) && $filesystem->exists(Path::join('install', 'install.php'))) {
        $response = new RedirectResponse($request->getSchemeAndHttpHost() . $request->getBasePath() . '/install/install.php', 307);
        $response->send();
        exit;
    } elseif (!$filesystem->exists(PATH_CONFIG) && !$filesystem->exists(Path::join('install', 'install.php'))) {
        throw new Exception('The FOSSBilling configuration file is empty or invalid.', 3);
    }

    // Set globals and relevant settings based on the config.
    date_default_timezone_set(Config::getProperty('i18n.timezone', 'UTC'));
    define('ADMIN_PREFIX', Config::getProperty('admin_area_prefix'));
    define('DEBUG', (bool) Config::getProperty('debug_and_monitoring.debug', false));
    define('PATH_DATA', Path::normalize(Config::getProperty('path_data')));
    define('PATH_CACHE', Path::join(PATH_DATA, 'cache'));
    define('PATH_LOG', Path::join(PATH_DATA, 'log'));
    define('INSTANCE_ID', Config::getProperty('info.instance_id', 'Unknown'));

    // Set the system URL.
    $scheme = Config::getProperty('security.force_https', true) || Tools::isHTTPS() ? 'https://' : 'http://';

    // Keep the app working correctly if the URL didn't get correctly updated
    $url = str_replace(['https://', 'http://'], '', Config::getProperty('url'));
    define('SYSTEM_URL', $scheme . $url);

    // Set the default interface.
    define('BIND_TO', Tools::getDefaultInterface());

    // Initial setup and checks passed, now we setup our custom autoloader.
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Autoloader.php');
    $loader = new FOSSBilling\AutoLoader();
    $loader->register();

    // Load the DI container.
    global $di;
    $di = require Path::join(PATH_ROOT, 'di.php');

    // Now that the config file is loaded, we can enable Sentry.
    SentryHelper::registerSentry();
}

/*
 * Post-initialization.
 */
function postInit(): void
{
    // Set error and exception handlers, and default logging settings.
    ini_set('log_errors', '1');
    ini_set('html_errors', false);
    ini_set('error_log', Path::join(PATH_LOG, 'php_error.log'));
    error_reporting(E_ALL);

    if (DEBUG) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
    } else {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
    }
}

// Perform pre-initialization (loading required dependencies, etc.).
preInit();

// Initialize the application.
init();

// Check for legacy BoxBilling/FOSSBilling files.
checkLegacyFiles();

// Verify the installer was removed.
checkInstaller();

// Check if SSL required, and enforce if so.
checkSSL();

// Check web server and web server settings.
checkWebServer();

// Check whether the patcher needs to be run.
checkUpdatePatcher();

// Perform post-initialization (setting error handlers, etc).
postInit();
