<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use FOSSBilling\Cache\CacheFactory;
use FOSSBilling\Doctrine\DriverManagerFactory;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Http\RequestFactory;
use FOSSBilling\Security\AuthenticationRequiredException;
use FOSSBilling\Security\EmailValidationRequiredException;
use FOSSBilling\System\Config;
use FOSSBilling\System\Environment;
use FOSSBilling\System\Version;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

$di = new Pimple\Container();

global $request;

if (!$request instanceof Request) {
    throw new LogicException('The request must be initialized before loading the DI container.');
}

/*
 * Create a new logger instance and configures it based on the settings in the configuration file.
 *
 * @param void
 *
 * @return FOSSBilling\Logging\Logger A new logger instance
 */
$di['logger'] = function () use ($di) {
    $log = new FOSSBilling\Logging\Logger();
    $log->setDi($di);

    $activity_service = $di['mod_service']('activity');
    $dbWriter = new FOSSBilling\Logging\DatabaseWriter($activity_service);
    $log->addWriter($dbWriter);

    $context = [];
    if ($di['auth']->isAdminLoggedIn()) {
        $admin = $di['loggedin_admin'];
        $context['admin_id'] = $admin->getId();
    } elseif ($di['auth']->isClientLoggedIn()) {
        $client = $di['loggedin_client'];
        $context['client_id'] = $client->getId();
    }

    $monolog = new FOSSBilling\Logging\Factory();
    $log->addWriter($monolog);

    if ($context !== []) {
        $log = $log->withContext($context);
    }

    return $log;
};

/*
 *
 * @param void
 *
 * @return FOSSBilling\Security\Crypt
 */
$di['crypt'] = function () use ($di) {
    $crypt = new FOSSBilling\Security\Crypt();
    $crypt->setDi($di);

    return $crypt;
};

/*
 * Creates and returns a Doctrine ORM EntityManager instance.
 *
 * This is the anchor for the shared database connection: the PDO and DBAL
 * services below reuse its connection so that all three participate in the
 * same transaction scope.
 *
 * @param void
 *
 * @return EntityManager The Doctrine ORM EntityManager instance.
 */
$di['em'] = (fn (): EntityManager => EntityManagerFactory::create());

/*
 *
 * @param void
 *
 * @return Connection The shared Doctrine DBAL connection instance.
 */
$di['dbal'] = (fn (): Connection => DriverManagerFactory::getSharedConnection());

/*
 * Creates a new PDO object for database connections
 *
 * @param void
 *
 * @return PDO The PDO object used for database connections
 */
$di['pdo'] = function () {
    $debug = (bool) Config::getProperty('debug_and_monitoring.debug', false);

    $pdo = DriverManagerFactory::getSharedConnection()->getNativeConnection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('PDO service must resolve to a PDO instance');
    }

    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $debug ? new DebugBar\DataCollector\PDO\TraceablePDO($pdo) : $pdo;
};

/*
 *
 * @param void
 *
 * @return FOSSBilling\Pagination\Service
 */
$di['pager'] = function () use ($di) {
    $service = new FOSSBilling\Pagination\Service();
    $service->setDi($di);

    return $service;
};

/*
 *
 * @param void
 *
 * @return FOSSBilling\Url
 */
$di['url'] = function () use ($di) {
    $url = new FOSSBilling\Url();
    $url->setDi($di);
    $url->setBaseUri(SYSTEM_URL);

    return $url;
};

/*
 * Returns a new Module object, created with the provided module name.
 *
 * @param string $name The name of the module to create the object with.
 *
 * @return \Module The new Module object that was just created.
 */
$di['mod'] = $di->protect(function ($name) use ($di) {
    $mod = new FOSSBilling\Module($name);
    $mod->setDi($di);

    return $mod;
});

/*
 *
 * @param string $mod the name of the module to get
 *
 * @return mixed the service of the associated module
 */
$di['mod_service'] = $di->protect(fn ($mod, $sub = '') => $di['mod']($mod)->getService($sub));

/*
 *
 * @param string $name the name of the module to get the configuration of
 *
 * @return mixed the configuration of the associated module
 */
$di['mod_config'] = $di->protect(fn ($name) => $di['mod']($name)->getConfig());

$di['cookie_queue'] = fn (): FOSSBilling\Http\CookieQueue => new FOSSBilling\Http\CookieQueue();

/*
 *
 * @param void
 *
 * @return \Box_EventManager
 */
$di['events_manager'] = function () use ($di) {
    $service = new Box_EventManager();
    $service->setDi($di);

    return $service;
};

/*
 * Creates a new session, applying specified security rules depending on the config.php settings.
 *
 * @param void
 *
 * @return \FOSSBilling\Security\Session
 *
 * @var \FOSSBilling\Security\Session $di['session']
 */
$di['session'] = function () use ($di) {
    $pdo = $di->offsetGet('pdo');
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('PDO service must resolve to a PDO instance');
    }

    $sessionLifetime = max(1, (int) Config::getProperty('security.session_lifespan', 7200));
    $handler = new PdoSessionHandler($pdo, [
        'db_table' => 'session',
        'db_id_col' => 'id',
        'db_data_col' => 'content',
        'db_lifetime_col' => 'lifetime',
        'db_time_col' => 'modified_at',
        // The application uses the same connection for its business queries;
        // advisory locking avoids holding a transaction open for the request.
        'lock_mode' => PdoSessionHandler::LOCK_ADVISORY,
    ]);
    $currentCookieParams = session_get_cookie_params();
    $cookieParams = [
        'path' => $currentCookieParams['path'],
        'domain' => $currentCookieParams['domain'],
        'secure' => Config::getProperty('security.force_https', true) || $di['request']->isSecure(),
        'httponly' => true,
        'samesite' => Config::getProperty('security.mode', 'strict') === 'strict'
            ? 'Strict'
            : $currentCookieParams['samesite'],
    ];
    $storage = new NativeSessionStorage([
        'cache_limiter' => '',
        'cookie_lifetime' => 0,
        'gc_maxlifetime' => $sessionLifetime,
        'cookie_path' => $cookieParams['path'],
        'cookie_domain' => $cookieParams['domain'],
        'cookie_secure' => $cookieParams['secure'],
        'cookie_httponly' => $cookieParams['httponly'],
        'cookie_samesite' => $cookieParams['samesite'],
        'serialize_handler' => 'php',
    ], $handler);
    $session = new FOSSBilling\Security\Session(new SymfonySession($storage), $cookieParams);
    $session->setDi($di);
    $session->setupSession();

    return $session;
};

/*
 * Creates a new request object based on the current request.
 *
 * @param void
 *
 * @link https://symfony.com/doc/current/components/http_foundation.html
 *
 * @return Symfony\Component\HttpFoundation\Request
 */
$di['request'] = $request;

/*
 * The general-purpose application cache. Backed by the filesystem by default; can be configured
 * to use Redis or Memcached instead via the `cache` block in the FOSSBilling configuration file.
 *
 * @param void
 *
 * @link https://symfony.com/doc/current/components/cache.html
 *
 * @return CacheItemPoolInterface
 */
$di['cache'] = fn (): CacheItemPoolInterface => CacheFactory::create(CacheFactory::NAMESPACE_APP, 24 * 60 * 60);

$di['rate_limit_cache'] = fn (): CacheItemPoolInterface => CacheFactory::create(CacheFactory::NAMESPACE_RATE_LIMIT, 24 * 60 * 60);

$di['http_client'] = fn (): HttpClientInterface => HttpClient::create([
    'bindto' => BIND_TO,
    'headers' => [
        'User-Agent' => 'FOSSBilling/' . Version::VERSION,
    ],
]);

$di['filesystem'] = fn (): Filesystem => new Filesystem();

$di['rate_limiter'] = function () use ($di) {
    $rateLimiter = new FOSSBilling\Security\RateLimiter();
    $rateLimiter->setDi($di);

    return $rateLimiter;
};

/*
 *
 * @param void
 *
 * @return Box_Authorization
 *
 * @var Box_Authorization $di['auth']
 */
$di['auth'] = fn (): Box_Authorization => new Box_Authorization($di);

/*
 * Checks whether a client is logged in and throws an exception or redirects to the login page if not.
 *
 * @param void
 *
 * @return bool True if a client is logged in.
 *
 * @throws \Exception If a client is not logged in and the request is an API request.
 *
 * @throws \HttpException If a client is not logged in and the request is a browser request.
 */
$di['is_client_logged'] = function () use ($di) {
    /** @var Box_Authorization $auth */
    $auth = $di['auth'];
    if (!$auth->isClientLoggedIn()) {
        throw new AuthenticationRequiredException('client');
    }

    return true;
};

/*
 * @param mixed $model The client DB model to check
 * @return bool Returns true if the client's email address is valid or if email confirmation is disabled.
 */
$di['is_client_email_validated'] = $di->protect(function ($model) use ($di) {
    $config = $di['mod_config']('client');
    if (isset($config['require_email_confirmation']) && (bool) $config['require_email_confirmation']) {
        return (bool) $model->getEmailApproved();
    }

    return true;
});

/*
 * Checks whether an admin is logged in and throws an exception or redirects to the login page if not.
 *
 * @param void
 *
 * @return bool True if an admin is logged in.
 *
 * @throws \Exception If an admin is not logged in and the request is an API request.
 *
 */
$di['is_admin_logged'] = function () use ($di) {
    /** @var Box_Authorization $auth */
    $auth = $di['auth'];
    if (!$auth->isAdminLoggedIn()) {
        throw new AuthenticationRequiredException('admin');
    }

    return true;
};

/*
 * Returns an existing logged-in client model object.
 *
 * @param void
 *
 * @return \Box\Mod\Client\Entity\Client The existing logged-in client entity.
 */
$di['loggedin_client'] = function () use ($di) {
    $di['is_client_logged'];
    /** @var FOSSBilling\Security\Session $session */
    $session = $di['session'];
    $client_id = $session->get('client_id');

    try {
        $client = $di['em']->getRepository(Box\Mod\Client\Entity\Client::class)->find($client_id);
        if (!$client instanceof Box\Mod\Client\Entity\Client || $client->getStatus() !== Box\Mod\Client\Entity\Client::ACTIVE) {
            throw new Exception('Client account is not active');
        }

        return $client;
    } catch (Exception) {
        // Either the account was deleted or the session is invalid. Either way, remove the ID from the session so the system doesn't consider someone logged in
        $session->delete('client_id');

        throw new AuthenticationRequiredException('client');
    }
};

/*
 * Signals whether the current request is executing cron tasks (CLI or HTTP-triggered).
 * Set to true by Cron\Service::runCrons(). Used to scope cron-admin identity fallbacks
 * so they cannot fire for unauthenticated requests outside of a genuine cron run.
 */
$di['is_cron'] = false;

/*
 * Returns an existing logged-in admin entity.
 *
 * @param void
 *
 * @return \Box\Mod\Staff\Entity\Admin|null The existing logged-in admin entity, or null if no admin is logged in.
 *
 * @throws \FOSSBilling\Exception\BaseException If the script is running in CLI or CGI mode and there is no cron admin available.
 */
$di['loggedin_admin'] = function () use ($di) {
    if (Environment::isCLI()) {
        return $di['mod_service']('staff')->getCronAdmin();
    }

    $di['is_admin_logged'];
    /** @var FOSSBilling\Security\Session $session */
    $session = $di['session'];
    $admin = $session->get('admin');

    try {
        $model = $di['em']->getRepository(Box\Mod\Staff\Entity\Admin::class)->find($admin['id']);
        if (!$model instanceof Box\Mod\Staff\Entity\Admin || $model->getStatus() !== Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE) {
            throw new Exception('Admin account is not active');
        }

        return $model;
    } catch (Exception) {
        // Either the account was deleted or the session is invalid. Either way, remove the ID from the session so the system doesn't consider someone logged in
        $session->delete('admin');

        throw new AuthenticationRequiredException('admin');
    }
};

$di['set_return_uri'] = function () use ($di): void {
    $request = $di['request'];
    $url = RequestFactory::getRoutePath($request);
    $query = $request->query->all();
    unset($query['_url']);

    if (str_starts_with($url, ADMIN_PREFIX)) {
        $url = substr($url, strlen(ADMIN_PREFIX));
    }

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    /** @var FOSSBilling\Security\Session $session */
    $session = $di['session'];
    $session->set('redirect_uri', $url);
};

/*
 * Creates a new API object based on the specified role and returns it.
 *
 * @param string $role The role to create the API object for. Can be 'guest', 'client', or 'admin'.
 *
 * @return \FOSSBilling\Api\Identity The new API identity wrapper that was just created.
 *
 * @throws \Exception If the specified role is not recognized or if a client is trying to use the API while their email is not valid.
 */
$di['api_identity'] = $di->protect(function ($role) use ($di) {
    $identity = match ($role) {
        'guest' => new FOSSBilling\Identity\Guest(),
        'client' => $di['loggedin_client'],
        'admin' => $di['loggedin_admin'],
        default => throw new Exception('Unrecognized Handler type: ' . $role),
    };

    // Checks to enforce email validation for clients
    if ($role === 'client' && !$di['is_client_email_validated']($identity)) {
        $routePath = RequestFactory::getRoutePath($di['request']);
        $isApiRequest = str_starts_with($routePath, '/api/');
        $isAllowedClientApi = str_starts_with($routePath, '/api/client/client/')
            || str_starts_with($routePath, '/api/client/profile/');
        $isAllowedClientPage = str_starts_with($routePath, '/client/profile')
            || str_starts_with($routePath, '/client/logout');

        if (($isApiRequest && !$isAllowedClientApi) || (!$isApiRequest && !$isAllowedClientPage)) {
            throw new EmailValidationRequiredException();
        }
    }

    return new FOSSBilling\Api\Identity($identity);
});

$di['api_dispatcher'] = function () use ($di): FOSSBilling\Api\Dispatcher {
    $dispatcher = new FOSSBilling\Api\Dispatcher();
    $dispatcher->setDi($di);

    return $dispatcher;
};

$di['api_proxy'] = $di->protect(function (string $role) use ($di): FOSSBilling\Api\Proxy {
    $identity = $di['api_identity']($role);
    $api = new FOSSBilling\Api\Proxy($identity->getIdentity());
    $api->setDi($di);

    return $api;
});

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Api\Proxy
 */
$di['api_guest'] = fn () => $di['api_proxy']('guest');

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Api\Proxy
 */
$di['api_client'] = fn () => $di['api_proxy']('client');

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Api\Proxy
 */
$di['api_admin'] = fn () => $di['api_proxy']('admin');

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Api\Proxy Internal-only system API proxy used for cron/background processing.
 */
$di['api_system'] = function () use ($di) {
    $identity = $di['mod_service']('staff')->getCronAdmin();
    $api = new FOSSBilling\Api\Proxy($identity);
    $api->setDi($di);

    return $api;
};

$di['tools'] = function () use ($di) {
    $service = new FOSSBilling\Tools();
    $service->setDi($di);

    return $service;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Validate
 */
$di['validator'] = function () use ($di) {
    $validator = new FOSSBilling\Validate();
    $validator->setDi($di);

    return $validator;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Remote\CentralAlerts
 */
$di['central_alerts'] = function () use ($di) {
    $centralalerts = new FOSSBilling\Remote\CentralAlerts();
    $centralalerts->setDi($di);

    return $centralalerts;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Remote\ExtensionManager
 */
$di['extension_manager'] = function () use ($di) {
    $extension = new FOSSBilling\Remote\ExtensionManager();
    $extension->setDi($di);

    return $extension;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Update\Updater
 */
$di['updater'] = function () use ($di) {
    $updater = new FOSSBilling\Update\Updater();
    $updater->setDi($di);

    return $updater;
};

$di['update_finalization'] = function () use ($di) {
    $finalization = new FOSSBilling\Update\Finalization();
    $finalization->setDi($di);

    return $finalization;
};

$di['update_readiness'] = new FOSSBilling\Update\ReadinessCheck(
    \PATH_ROOT,
    \PATH_DATA,
    \PATH_CONFIG,
);

/*
 * Creates a new server manager object and returns it.
 *
 * @param string $manager The name of the server manager to create.
 * @param array $config The configuration options for the server manager.
 *
 * @return \Server_Manager The new server manager object that was just created.
 */
$di['server_manager'] = $di->protect(function ($manager, $config) use ($di) {
    $managerName = ucfirst((string) $manager);
    $class = sprintf('Server_Manager_%s', $managerName);

    if (!class_exists($class)) {
        $file = Path::join(PATH_LIBRARY, 'Server', 'Manager', $managerName . '.php');
        if ($di['filesystem']->exists($file)) {
            require_once $file;
        }
    }

    $s = new $class($config);
    $s->setLog($di['logger']);

    return $s;
});

/*
 * Creates a new FOSSBilling\Period object using the provided period code and returns it.
 *
 * @param string $code The two character period code to create the period object with.
 *
 * @return FOSSBilling\Period The new period object that was just created.
 */
$di['period'] = $di->protect(fn (string $code): FOSSBilling\Period => new FOSSBilling\Period($code));

/*
 * Gets the current client area theme.
 *
 * @param void
 *
 * @return \Box_Theme The current client area theme.
 */
$di['theme'] = function () use ($di) {
    $service = $di['mod_service']('theme');

    return $service->getCurrentClientAreaTheme();
};

/*
 * Loads an existing cart session or creates a new one if there is no session.
 *
 * @param void
 *
 * @return mixed The either existing or new cart.
 */
$di['cart'] = function () use ($di) {
    $service = $di['mod_service']('cart');

    return $service->getSessionCart();
};

/*
 * @param void
 *
 * @return \Box\Mod\Servicelicense\Server
 */
$di['license_server'] = function () use ($di) {
    $server = new Box\Mod\Servicelicense\Server();
    $server->setDi($di);

    return $server;
};

/*
 * @param void
 *
 * @return \FOSSBilling\GeoIP\Reader
 */
$di['geoip'] = function () use ($di) {
    $reader = new FOSSBilling\GeoIP\Reader();
    $reader->setDi($di);

    return $reader;
};

/*
 * @param void
 *
 * @return \FOSSBilling\Security\PasswordManager
 */
$di['password'] = fn (): FOSSBilling\Security\PasswordManager => new FOSSBilling\Security\PasswordManager();

/*
 * Creates a new FOSSBilling\I18n\Translator object and sets the specified text domain, locale, and other options.
 *
 * @param string $textDomain The text domain to create the translation object with.
 *
 * @return FOSSBilling\I18n\Translator The new translation object that was just created.
 */
$di['translate'] = $di->protect(function ($textDomain = '') use ($di) {
    $tr = new FOSSBilling\I18n\Translator();

    if (!empty($textDomain)) {
        $tr->setDomain($textDomain);
    }

    $locale = FOSSBilling\I18n\I18n::getActiveLocale($di['request'], true, $di['cookie_queue']);

    $tr->setLocale($locale);
    $tr->setup();

    return $tr;
});

$di['csv_response_factory'] = fn (): FOSSBilling\Http\CsvResponseFactory => new FOSSBilling\Http\CsvResponseFactory($di['em']->getConnection());

$di['twig_factory'] = fn (): FOSSBilling\Twig\TwigFactory => new FOSSBilling\Twig\TwigFactory($di);

return $di;
