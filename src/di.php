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
 * with this source code in the file LICENSE
 */

declare(strict_types=1);

use RedBeanPHP\Facade;

$di = new Box_Di();

/**
 * Returns the current FOSSBilling config from config.php
 *
 * @param void
 *
 * @return Box_Config
 */
$di['config'] = function () {
    $array = include PATH_ROOT . '/config.php';

    return new Box_Config($array);
};

/**
 * Create a new logger instance and configures it based on the settings in the configuration file.
 *
 * @param void
 *
 * @return Box_Log A new logger instance
 */
$di['logger'] = function () use ($di) {
    $log = new Box_Log();
    $log->setDi($di);

    $log_to_db = isset($di['config']['log_to_db']) && $di['config']['log_to_db'];

    if ($log_to_db) {
        $activity_service = $di['mod_service']('activity');
        $writer2 = new Box_LogDb($activity_service);

        if ($di['auth']->isAdminLoggedIn()) {
            $admin = $di['loggedin_admin'];
            $log->setEventItem('admin_id', $admin->id);
        } elseif ($di['auth']->isClientLoggedIn()) {
            $client = $di['loggedin_client'];
            $log->setEventItem('client_id', $client->id);
        }

        $log->addWriter($writer2);
    } else {
        $logFile = $di['config']['path_logs'];
        $writer = new Box_LogStream($logFile);
        $log->addWriter($writer);
    }

    return $log;
};

/**
 *
 * @param void
 *
 * @return \Box_Crypt
 */
$di['crypt'] = function () use ($di) {
    $crypt = new Box_Crypt();
    $crypt->setDi($di);

    return $crypt;
};

/**
 * Creates a new PDO object for database connections
 *
 * @param void
 *
 * @return PDO The PDO object used for database connections
 */
$di['pdo'] = function () use ($di) {
    $c = $di['config']['db'];

    $pdo = new PDO($c['type'] . ':host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['name'],
        $c['user'],
        $c['password'],
        [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    if (isset($c['debug']) && $c['debug']) {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['Box_DbLoggedPDOStatement']);
    }

    if ('mysql' === $c['type']) {
        $pdo->exec('SET NAMES "utf8"');
        $pdo->exec('SET CHARACTER SET utf8');
        $pdo->exec('SET CHARACTER_SET_CONNECTION = utf8');
        $pdo->exec('SET character_set_results = utf8');
        $pdo->exec('SET character_set_server = utf8');
        $pdo->exec('SET SESSION interactive_timeout = 28800');
        $pdo->exec('SET SESSION wait_timeout = 28800');
    }

    return $pdo;
};

/**
 *
 * @param void
 *
 * @return \Box_Database The new Box_Database object that was just created.
 */
$di['db'] = function () use ($di) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'rb.php';
    R::setup($di['pdo']);
    \RedBeanPHP\Util\DispenseHelper::setEnforceNamingPolicy(false);

    $helper = new Box_BeanHelper();
    $helper->setDi($di);

    $mapper = new Facade();
    $mapper->getRedBean()->setBeanHelper($helper);
    $freeze = isset($di['config']['db']['freeze']) ? (bool) $di['config']['db']['freeze'] : true;
    $mapper->freeze($freeze);

    $db = new Box_Database();
    $db->setDi($di);
    $db->setDataMapper($mapper);

    return $db;
};

/**
 *
 * @param void
 *
 * @return Box_Pagination
 */
$di['pager'] = function () use ($di) {
    $service = new Box_Pagination();
    $service->setDi($di);

    return $service;
};

/**
 *
 * @param void
 *
 * @return Box_Url
 */
$di['url'] = function () use ($di) {
    $url = new Box_Url();
    $url->setDi($di);
    $url->setBaseUri(BB_URL);

    return $url;
};

/**
 * Returns a new Box_Mod object, created with the provided module name.
 *
 * @param string $name The name of the module to create the object with.
 *
 * @return \Box_Mod The new Box_Mod object that was just created.
 */
$di['mod'] = $di->protect(function ($name) use ($di) {
    $mod = new Box_Mod($name);
    $mod->setDi($di);

    return $mod;
});

/**
 *
 * @param string $mod the name of the module to get
 *
 * @return mixed the service of the asociated module
 */
$di['mod_service'] = $di->protect(function ($mod, $sub = '') use ($di) {
    return $di['mod']($mod)->getService($sub);
});

/**
 *
 * @param string $name the name of the module to get the configuration of
 *
 * @return mixed the configuration of the asociated module
 */
$di['mod_config'] = $di->protect(function ($name) use ($di) {
    return $di['mod']($name)->getConfig();
});

/**
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

/**
 * Creates a new session, applying specified security rules depending on the config.php settings.
 *
 * @param void
 *
 * @return \Box_Session
 */
$di['session'] = function () use ($di) {
    $handler = new PdoSessionHandler($di['pdo']);
    $mode = (isset($di['config']['security']['mode'])) ? $di['config']['security']['mode'] : 'strict';
    $lifespan =(isset($di['config']['security']['cookie_lifespan'])) ? $di['config']['security']['cookie_lifespan'] : 7200;
    $secure = (isset($di['config']['security']['force_https'])) ? $di['config']['security']['force_https'] : true;

    return new Box_Session($handler, $mode, $lifespan, $secure);
};

/**
 *
 * @param void
 *
 * @return \Box_Cookie
 */
$di['cookie'] = function () use ($di) {
    $service = new Box_Cookie();
    $service->setDi($di);

    return $service;
};

/**
 *
 * @param void
 *
 * @return \Box_Request
 */
$di['request'] = function () use ($di) {
    $service = new Box_Request();
    $service->setDi($di);

    return $service;
};

/**
 *
 * @param void
 *
 * @return \FileCache
 */
$di['cache'] = function () { return new FileCache(); };

/**
 *
 * @param void
 *
 * @return \Box_Authorization
 */
$di['auth'] = function () use ($di) { return new Box_Authorization($di); };

/**
 * Creates a new Twig environment that's configured for FOSSBilling.
 *
 * @param void
 *
 * @return \Twig\Environment The new Twig environment that was just created.
 *
 * @throws \Twig\Error\LoaderError If the Twig environment could not be created.
 * @throws \Twig\Error\RuntimeError If an error occurs while rendering a template.
 * @throws \Twig\Error\SyntaxError If a template is malformed.
 */
$di['twig'] = $di->factory(function () use ($di) {
    $config = $di['config'];
    $options = $config['twig'];

    $loader = new Twig\Loader\ArrayLoader();
    $twig = new Twig\Environment($loader, $options);

    $box_extensions = new Box_TwigExtensions();
    $box_extensions->setDi($di);

    // $twig->addExtension(new Twig\Extension\OptimizerExtension());
    $twig->addExtension(new \Twig\Extension\StringLoaderExtension());
    $twig->addExtension(new Twig\Extension\DebugExtension());
    $twig->addExtension(new Symfony\Bridge\Twig\Extension\TranslationExtension());
    $twig->addExtension($box_extensions);
    $twig->getExtension(Twig\Extension\CoreExtension::class)
        ->setDateFormat($config['locale_date_format']);
    $twig->getExtension(Twig\Extension\CoreExtension::class)
        ->setTimezone($config['timezone']);

    // add globals
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH']) {
        $_GET['ajax'] = true;
    }

    $twig->addGlobal('request', $_GET);
    $twig->addGlobal('guest', $di['api_guest']);

    return $twig;
});

/**
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
    if (!$di['auth']->isClientLoggedIn()) {
        $api_str = '/api/';
        $url = $di['request']->getQuery('_url');

        if (0 === strncasecmp($url, $api_str, strlen($api_str))) {
            // Throw Exception if api request
            throw new Exception('Client is not logged in');
        } else {
            // Redirect to login page if browser request
            $login_url = $di['url']->link('login');
            header("Location: $login_url");
        }
    }

    return true;
};

/**
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
    if (!$di['auth']->isAdminLoggedIn()) {
        $api_str = '/api/';
        $url = $di['request']->getQuery('_url',null,'');
        if (0 === strncasecmp($url, $api_str, strlen($api_str))) {
            // Throw Exception if api request
            throw new Exception('Admin is not logged in');
        } else {
            // Redirect to login page if browser request
            $login_url = $di['url']->adminLink('staff/login');
            header("Location: $login_url");
        }
    }

    return true;
};

/**
 * Returns an existing logged-in client model object.
 *
 * @param void
 *
 * @return \Model_Client The existing logged-in client model object.
 */
$di['loggedin_client'] = function () use ($di) {
    $di['is_client_logged'];
    $client_id = $di['session']->get('client_id');

    return $di['db']->getExistingModelById('Client', $client_id);
};

/**
 * Returns an existing logged-in admin model object.
 *
 * @param void
 *
 * @return \Model_Admin|null The existing logged-in admin model object, or null if no admin is logged in.
 *
 * @throws \Box_Exception If the script is running in CLI or CGI mode and there is no cron admin available.
 */
$di['loggedin_admin'] = function () use ($di) {
    if ('cli' === php_sapi_name() || !http_response_code()) {
        return $di['mod_service']('staff')->getCronAdmin();
    }

    $di['is_admin_logged'];
    $admin = $di['session']->get('admin');

    return $di['db']->getExistingModelById('Admin', $admin['id']);
};

/**
 * Creates a new API object based on the specified role and returns it.
 *
 * @param string $role The role to create the API object for. Can be 'guest', 'client', 'admin', or 'system'.
 *
 * @return \Api_Handler The new API object that was just created.
 *
 * @throws \Exception If the specified role is not recognized.
 */
$di['api'] = $di->protect(function ($role) use ($di) {
    $identity = match ($role) {
        'guest' => new \Model_Guest(),
        'client' => $di['loggedin_client'],
        'admin' => $di['loggedin_admin'],
        'system' => $di['mod_service']('staff')->getCronAdmin(),
        default => throw new Exception('Unrecognized Handler type: ' . $role),
    };

    $api = new Api_Handler($identity);
    $api->setDi($di);

    return $api;
});

/**
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_guest'] = function () use ($di) { return $di['api']('guest'); };

/**
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_client'] = function () use ($di) { return $di['api']('client'); };

/**
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_admin'] = function () use ($di) { return $di['api']('admin'); };

/**
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_system'] = function () use ($di) { return $di['api']('system'); };

/**
 *
 * @param void
 *
 * @return \Box_Tools
 */
$di['tools'] = function () use ($di) {
    $service = new Box_Tools();
    $service->setDi($di);

    return $service;
};

/**
 *
 * @param void
 *
 * @return \Box_Validate
 */
$di['validator'] = function () use ($di) {
    $validator = new Box_Validate();
    $validator->setDi($di);

    return $validator;
};

/**
 * Creates a new Guzzle HTTP client and returns it.
 *
 * @param void
 *
 * @return \GuzzleHttp\Client The new Guzzle HTTP client that was just created.
 */
$di['guzzle_client'] = function () use ($di) {
    return new GuzzleHttp\Client([
        'headers' => [
            'User-Agent' => $di['config']['guzzle']['user_agent'],
            'Upgrade-Insecure-Requests' => $di['config']['guzzle']['upgrade_insecure_requests'],
        ],
        'timeout' => $di['config']['guzzle']['timeout'],
    ]);
};

/**
 *
 * @param void
 *
 * @return \Box_Mail
 */
$di['mail'] = function () {
    return new Box_Mail();
};

/**
 *
 * @param void
 *
 * @return \Box_Extension
 */
$di['extension'] = function () use ($di) {
    $extension = new \Box_Extension();
    $extension->setDi($di);

    return $extension;
};

/**
 *
 * @param void
 *
 * @return \Box_Update
 */
$di['updater'] = function () use ($di) {
    $updater = new \Box_Update();
    $updater->setDi($di);

    return $updater;
};

/**
 * Creates a new Curl object and returns it.
 *
 * @param string $url The URL to send the request to.
 *
 * @return \Box_Curl The new Curl object that was just created.
 */
$di['curl'] = function ($url) use ($di) {
    $curl = new \Box_Curl($url);
    $curl->setDi($di);

    return $curl;
};

/**
 * @param void
 *
 * @return Server_Package
 */
$di['server_package'] = function () {return new Server_Package(); };

/**
 * @param void
 *
 * @return Server_Client
 */
$di['server_client'] = function () {return new Server_Client(); };

/**
 * @param void
 *
 * @return Server_Account
 */
$di['server_account'] = function () {return new Server_Account(); };

/**
 * Creates a new server manager object and returns it.
 *
 * @param string $manager The name of the server manager to create.
 * @param array $config The configuration options for the server manager.
 *
 * @return \Server_Manager The new server manager object that was just created.
 */
$di['server_manager'] = $di->protect(function ($manager, $config) {
    $class = sprintf('Server_Manager_%s', ucfirst($manager));

    return new $class($config);
});

/**
 * @param void
 *
 * @return Box_Requirements
 */
$di['requirements'] = function () use ($di) {
    $r = new Box_Requirements();
    $r->setDi($di);

    return $r;
};

/**
 * Creates a new Box_Period object using the provided period code and returns it.
 *
 * @param string $code The two characture period code to create the period object with.
 *
 * @return \Box_Period The new period object that was just created.
 */
$di['period'] = $di->protect(function ($code) { return new \Box_Period($code); });

/**
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

/**
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

/**
 * Creates a new table object and returns it.
 *
 * @param string $name The name of the table to create.
 *
 * @return \Box_Table The new table object that was just created.
 */
$di['table'] = $di->protect(function ($name) use ($di) {
    $tools = new Box_Tools();
    $tools->setDi($di);
    $table = $tools->getTable($name);
    $table->setDi($di);

    return $table;
});

/**
 * @param void
 *
 * @return \Box\Mod\Servicelicense\Server
 */
$di['license_server'] = function () use ($di) {
    $server = new \Box\Mod\Servicelicense\Server($di['logger']);
    $server->setDi($di);

    return $server;
};

/**
 * @param mixed $params The parameters for the new FTP object.
 *
 * @return \Box_Ftp The new FTP object that was just created.
 */
$di['ftp'] = $di->protect(function ($params) { return new \Box_Ftp($params); });

/**
 * @param void
 *
 * @return \GeoIp2\Database\Reader
 */
$di['geoip'] = function () { return new \GeoIp2\Database\Reader(PATH_LIBRARY . '/GeoLite2-Country.mmdb'); };

/**
 * @param void
 *
 * @return \Box_Password
 */
$di['password'] = function () { return new Box_Password(); };

/**
 * Creates a new Box_Translate object and sets the specified text domain, locale, and other options.
 *
 * @param string $textDomain The text domain to create the translation object with.
 *
 * @return \Box_Translate The new translation object that was just created.
 */
$di['translate'] = $di->protect(function ($textDomain = '') use ($di) {
    $tr = new Box_Translate();

    if (!empty($textDomain)) {
        $tr->setDomain($textDomain);
    }

    $cookieBBlang = $di['cookie']->get('BBLANG');
    $locale = !empty($cookieBBlang) ? $cookieBBlang : $di['config']['locale'];

    $tr->setDi($di);
    $tr->setLocale($locale);
    $tr->setup();

    return $tr;
});

/**
 * Gets the value of a key from an array, or returns a default value if the key does not exist.
 *
 * @param array $array An array of mixed types to search for the key in.
 * @param string $key The key to look for.
 * @param string $default The default value if the key does not exist.
 *
 * @return mixed|null The value of the key if it exists in the array, or the default value if it does not. (null unless otherwise specified)
 */
$di['array_get'] = $di->protect(function (array $array, $key, $default = null) {
    $result = array_key_exists($key, $array) ? $array[$key] : $default;

    return ('' === $result) ? null : $result;
});

return $di;
