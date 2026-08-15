<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Config;
use FOSSBilling\Environment;
use FOSSBilling\Http\ExceptionResponseFactory;
use FOSSBilling\Http\RequestFactory;
use FOSSBilling\SentryHelper;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

function emitResponse(Response $response): never
{
    sendResponse($response);
    exit;
}

function sendResponse(Response $response): void
{
    global $request;

    $currentRequest = $request instanceof Request ? $request : Request::createFromGlobals();
    $response->prepare($currentRequest)->send();
}

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
    // This delete is irreversible, so it requires an explicit APP_ENV=prod rather than the ambiguous default above.
    // @phpstan-ignore booleanNot.alwaysTrue (DEBUG is a runtime constant)
    if (Environment::isExplicitlyProduction() && $filesystem->exists(PATH_CONFIG) && $filesystem->exists(Path::normalize('install')) && !DEBUG) {
        // Bootstrap runs before the DI logger and PHP error log are configured.
        error_log('Removing the install directory now that installation is complete.');
        $filesystem->remove('install');
    }
}

/*
 * Check if SSL required, and enforce if so.
 */
function checkSSL(): void
{
    global $request;

    if (Config::getProperty('security.force_https') && !Environment::isCLI()) {
        if (!$request->isSecure()) {
            emitResponse(new RedirectResponse('https://' . $request->getHost() . $request->getRequestUri()));
        }
    }
}

/*
 * Check the web server config.
 */
function checkWebServer(): void
{
    global $filesystem, $request;

    // Check for missing required .htaccess on Apache and Apache-compatible web servers.
    $webServer = SentryHelper::estimateWebServer($request->server->get('SERVER_SOFTWARE', ''));
    if ($webServer === 'Apache' || $webServer === 'Litespeed') {
        if (!$filesystem->exists('.htaccess')) {
            throw new Exception('Missing .htaccess file', 5);
        }
    }
}

/*
 * Check whether the configured database has existing tables.
 */
function hasDatabaseTables(): bool
{
    $dbConfig = Config::getProperty('db', []);
    if (!is_array($dbConfig) || ($dbConfig['driver'] ?? '') !== 'pdo_mysql') {
        return true;
    }

    $host = $dbConfig['host'] ?? '';
    $database = $dbConfig['name'] ?? '';
    $port = Tools::normalizePort($dbConfig['port'] ?? null, 3306);
    if ($host === '' || $database === '') {
        return true;
    }

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $dbConfig['user'] ?? '',
            $dbConfig['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $statement = $pdo->query('SHOW TABLES');

        return $statement !== false && $statement->fetchColumn() !== false;
    } catch (Throwable $e) {
        if ((bool) Config::getProperty('debug', false)) {
            // Database inspection happens before the DI container is available.
            error_log(sprintf(
                'hasDatabaseTables() failed to inspect configured database tables: %s in %s on line %d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));
        }

        return true;
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
function exceptionHandler(Exception|Error $e): void
{
    $exceptionResponseFactory = new ExceptionResponseFactory();

    if (Environment::isTesting()) {
        $msg = $exceptionResponseFactory->formatTestingMessage($e);
        @file_put_contents(Path::join(PATH_LOG, 'exception_handler.log'), date('c') . ' ' . $msg, FILE_APPEND);
        @file_put_contents(Path::join(PATH_ROOT, 'data', 'log', 'exception_handler.log'), date('c') . ' ' . $msg, FILE_APPEND);
    } else {
        // The exception handler must also work when initialization itself fails.
        error_log("{$e->getMessage()} at {$e->getFile()} : {$e->getLine()}");
    }

    emitResponse($exceptionResponseFactory->create($e));
}

/*
 * Refuse to serve this request while FOSSBilling\Update::performUpdate() is
 * actively writing files to PATH_ROOT.
 *
 * This intentionally runs before the Composer autoloader is loaded, and
 * before any FOSSBilling class is referenced: while a core update is
 * extracting a release archive on top of the live codebase, a concurrent
 * request that starts autoloading classes mid-swap can see a torn mix of old
 * and new files and fatal out with an "incompatible declaration" error.
 * See https://github.com/FOSSBilling/FOSSBilling/issues/4159.
 *
 * The lock filename below is duplicated as a literal (see
 * Update::LOCK_FILENAME) because this check has to run before that class -
 * or anything else - can be autoloaded. Keep both copies in sync if it
 * changes. The 600-second staleness window only exists here; performUpdate()
 * always removes the lock itself via a `finally` block, so this is purely a
 * failsafe for the rare case that PHP is killed outright (host timeout, OOM)
 * before that block can run.
 */
function isCoreUpdateLockActive(?int $now = null): bool
{
    $mtime = @filemtime(PATH_ROOT . DIRECTORY_SEPARATOR . '.update-lock');
    $now ??= time();

    // No lock file, or it's old enough that the process which created it must
    // have died without cleaning up (e.g. a host-side request timeout killed
    // it before the update's `finally` block could run). Treat an old lock as
    // abandoned rather than blocking the site forever.
    return $mtime !== false && ($now - $mtime) <= 600;
}

function blockWhileCoreUpdateIsRunning(): void
{
    if (!isCoreUpdateLockActive()) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "FOSSBilling is currently applying a core update. Please try again in a few moments.\n");
        exit(1);
    }

    http_response_code(503);
    header('Retry-After: 5');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Updating&hellip;</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:4rem 1rem;">'
        . '<h1>FOSSBilling is updating</h1>'
        . '<p>This installation is being updated and will be back in a moment. This page will retry automatically.</p>'
        . '<script>setTimeout(function () { location.reload(); }, 5000);</script>'
        . '</body></html>';
    exit;
}

/*
 * Pre-initialization.
 */
function preInit(): void
{
    // Define root path.
    define('PATH_ROOT', __DIR__);

    blockWhileCoreUpdateIsRunning();

    // Check vendor folder exists and load Composer autoloader.
    define('PATH_VENDOR', PATH_ROOT . DIRECTORY_SEPARATOR . 'vendor');
    if (!file_exists(PATH_VENDOR)) {
        throw new Exception('The composer packages are missing.', 1);
    }
    require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';

    // Define global paths.
    define('PATH_LIBRARY', Path::join(PATH_ROOT, 'library'));
    require Path::join(PATH_LIBRARY, 'TranslationFunctions.php');
    define('PATH_THEMES', Path::join(PATH_ROOT, 'themes'));
    define('PATH_MODS', Path::join(PATH_ROOT, 'modules'));
    define('PATH_LANGS', Path::join(PATH_ROOT, 'locale'));
    $pathUploads = Path::join(PATH_ROOT, 'data', 'uploads');
    if (getenv('APP_ENV') === 'test') {
        $pathUploads = Path::join(sys_get_temp_dir(), 'fossbilling_test_data', 'uploads');
        if (!is_dir($pathUploads) && !mkdir($pathUploads, 0o755, true) && !is_dir($pathUploads)) {
            throw new Exception(sprintf('Unable to create uploads directory for tests: "%s".', $pathUploads));
        }
    }
    define('PATH_UPLOADS', $pathUploads);
    define('PATH_CONFIG', Path::join(PATH_ROOT, 'config.php'));

    // Load required FOSSBilling libraries.
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'ErrorPage.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'SentryHelper.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Environment.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'ApiResponseFactory.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'ExceptionResponseFactory.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'RequestFactory.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'ResponseFactory.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'RouteDefinition.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'RouteMatch.php');
    require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Http', 'RouteMatcher.php');
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
    $request = RequestFactory::createFromGlobals(RequestFactory::getPreConfigProxyConfig($_SERVER));
    RequestFactory::normalizeRoutePath($request);

    // Check config exists and is valid, redirecting to installer or throwing an exception if not.
    $configExists = $filesystem->exists(PATH_CONFIG);
    $configIsValid = $configExists && Config::isConfigValid();
    $installerExists = $filesystem->exists(Path::join('install', 'install.php'));

    if (!$configExists && $installerExists) {
        emitResponse(new RedirectResponse($request->getBasePath() . '/install/install.php', 307));
    } elseif (!$configIsValid) {
        throw new Exception('The FOSSBilling configuration file is empty or invalid.', 3);
    }

    if (Environment::isDevelopment() && Config::getProperty('debug_and_monitoring.debug', false) && $installerExists && !hasDatabaseTables()) {
        emitResponse(new RedirectResponse($request->getBasePath() . '/install/install.php', 307));
    }

    RequestFactory::configureFromConfig($request);

    // Set globals and relevant settings based on the config.
    date_default_timezone_set(Config::getProperty('i18n.timezone', 'UTC'));
    define('ADMIN_PREFIX', Config::getProperty('admin_area_prefix'));
    if (!defined('DEBUG')) {
        define('DEBUG', (bool) Config::getProperty('debug_and_monitoring.debug', false));
    }
    $pathData = Path::normalize(Config::getProperty('path_data'));
    if (Environment::isTesting()) {
        $pathData = Path::join(sys_get_temp_dir(), 'fossbilling_test_data');
        @mkdir(Path::join($pathData, 'cache'), 0o755, true);
        @mkdir(Path::join($pathData, 'log'), 0o755, true);
        @mkdir($pathData, 0o755, true);
    }
    define('PATH_DATA', $pathData);
    define('PATH_CACHE', Path::join(PATH_DATA, 'cache'));
    define('PATH_LOG', Path::join(PATH_DATA, 'log'));
    define('INSTANCE_ID', Config::getProperty('info.instance_id', 'Unknown'));

    // Set the system URL.
    $scheme = Config::getProperty('security.force_https', true) || $request->isSecure() ? 'https://' : 'http://';

    // Keep the app working correctly if the URL didn't get correctly updated
    $url = str_replace(['https://', 'http://'], '', Config::getProperty('url'));
    define('SYSTEM_URL', $scheme . $url);

    // Set the default interface.
    define('BIND_TO', Tools::getDefaultInterface());

    // Load the DI container.
    global $di;
    $di = require Path::join(PATH_ROOT, 'di.php');

    if (!Environment::isCLI() && !Environment::isTesting()) {
        $di['update_finalization']->ensureCurrentVersionFinalization();
    }

    // Now that the config file is loaded, we can enable Sentry.
    SentryHelper::registerSentry($request->server->get('SERVER_SOFTWARE', ''));
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

    // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
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

// Verify the installer was removed.
checkInstaller();

// Check if SSL required, and enforce if so.
checkSSL();

// Check web server and web server settings.
checkWebServer();

// Perform post-initialization (setting error handlers, etc).
postInit();
