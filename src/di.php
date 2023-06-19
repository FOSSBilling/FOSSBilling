<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Lcharette\WebpackEncoreTwig\EntrypointsTwigExtension;
use Lcharette\WebpackEncoreTwig\JsonManifest;
use Lcharette\WebpackEncoreTwig\TagRenderer;
use Lcharette\WebpackEncoreTwig\VersionedAssetsTwigExtension;
use RedBeanPHP\Facade;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Twig\Extension\CoreExtension;
use Twig\Extension\DebugExtension;
use Twig\Extension\StringLoaderExtension;
use Twig\Extra\Intl\IntlExtension;

$di = new \Pimple\Container();

/*
 * Returns the current FOSSBilling config from config.php
 *
 * @param void
 *
 * @return array
 */
$di['config'] = function () {
    $array = include PATH_ROOT . '/config.php';
    return $array;
};

/*
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
        $monolog = new \FOSSBilling\Monolog($di);
        $log->addWriter($monolog);
    }

    return $log;
};

/*
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

/*
 * Creates a new PDO object for database connections
 *
 * @param void
 *
 * @return PDO The PDO object used for database connections
 */
$di['pdo'] = function () use ($di) {
    $c = $di['config']['db'];

    $pdo = new PDO(
        $c['type'] . ':host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['name'],
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

/*
 *
 * @param void
 *
 * @return \Box_Database The new Box_Database object that was just created.
 */
$di['db'] = function () use ($di) {
    \RedBeanPHP\R::setup($di['pdo']);
    \RedBeanPHP\Util\DispenseHelper::setEnforceNamingPolicy(false);

    $helper = new \Box_BeanHelper();
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

/*
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

/*
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

/*
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

/*
 *
 * @param string $mod the name of the module to get
 *
 * @return mixed the service of the associated module
 */
$di['mod_service'] = $di->protect(function ($mod, $sub = '') use ($di) {
    return $di['mod']($mod)->getService($sub);
});

/*
 *
 * @param string $name the name of the module to get the configuration of
 *
 * @return mixed the configuration of the associated module
 */
$di['mod_config'] = $di->protect(function ($name) use ($di) {
    return $di['mod']($name)->getConfig();
});

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
 * @return \FOSSBilling\Session
 */
$di['session'] = function () use ($di) {
    $handler = new PdoSessionHandler($di['pdo']);

    $mode = $di['config']['security']['mode'] ?? 'strict';
    $lifespan = $di['config']['security']['cookie_lifespan'] ?? 7200;
    $secure = $di['config']['security']['force_https'] ?? true;

    $session = new \FOSSBilling\Session($handler, $mode, $lifespan, $secure);
    $session->setDi($di);
    $session->setupSession();

    return $session;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Request
 */
$di['request'] = function () use ($di) {
    $service = new \FOSSBilling\Request();
    $service->setDi($di);

    return $service;
};

/*
 * @param void
 *
 * @return \Symfony\Component\Cache\Adapter\FilesystemAdapter
 */
$di['cache'] = function () {
    // Reference: https://symfony.com/doc/current/components/cache/adapters/filesystem_adapter.html
    return new FilesystemAdapter('sf_cache', 24 * 60 * 60, PATH_CACHE);
};

/*
 *
 * @param void
 *
 * @return \Box_Authorization
 */
$di['auth'] = function () use ($di) {
    return new Box_Authorization($di);
};

/*
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

    // Get internationalisation settings from config, or use sensible defaults for
    // missing required settings.
    $locale = \FOSSBilling\i18n::getActiveLocale();
    $timezone = $config['i18n']['timezone'] ?? 'UTC';
    $date_format = !empty($config['i18n']['date_format']) ? strtoupper($config['i18n']['date_format']) : 'MEDIUM';
    $time_format = !empty($config['i18n']['time_format']) ? strtoupper($config['i18n']['time_format']) : 'SHORT';
    $datetime_pattern = $config['i18n']['datetime_pattern'] ?? null;

    $loader = new Twig\Loader\ArrayLoader();
    $twig = new Twig\Environment($loader, $options);

    $box_extensions = new Box_TwigExtensions();
    $box_extensions->setDi($di);

    if ($di['encore_info']['is_encore_theme']) {
        $entryPoints = new EntrypointLookup($di['encore_info']['entrypoints']);
        $tagRenderer = new TagRenderer($entryPoints);
        $encoreExtensions = new EntrypointsTwigExtension($entryPoints, $tagRenderer);
        $twig->addExtension($encoreExtensions);
        $twig->addExtension(new VersionedAssetsTwigExtension(new JsonManifest($di['encore_info']['manifest'])));
    }

    // $twig->addExtension(new OptimizerExtension());
    $twig->addExtension(new StringLoaderExtension());
    $twig->addExtension(new DebugExtension());
    $twig->addExtension(new TranslationExtension());
    $twig->addExtension($box_extensions);
    $twig->getExtension(CoreExtension::class)->setTimezone($timezone);

    try {
        $dateFormatter = new \IntlDateFormatter($locale, constant("\IntlDateFormatter::$date_format"), constant("\IntlDateFormatter::$time_format"), $timezone, null, $datetime_pattern);
    } catch (\Symfony\Polyfill\Intl\Icu\Exception\MethodArgumentValueNotImplementedException) {
        if (($config['i18n']['locale'] ?? 'en_US') == 'en_US') {
            $dateFormatter = new \IntlDateFormatter('en', constant("\IntlDateFormatter::$date_format"), constant("\IntlDateFormatter::$time_format"), $timezone, null, $datetime_pattern);
        } else {
            throw new \Box_Exception("It appears you are trying to use FOSSBilling without the php intl extension enabled. FOSSBilling includes a polyfill for the intl extension, however it does not support :locale. Please enable the intl extension.", [':locale' => $config['i18n']['locale']]);
        }
    }

    $twig->addExtension(new IntlExtension($dateFormatter));

    // add globals
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH']) {
        $_GET['ajax'] = true;
    }

    // CSRF token
    if (PHP_SESSION_ACTIVE !== session_status()) {
        $token = hash('md5', $_COOKIE['PHPSESSID'] ?? '');
    } else {
        $token = hash('md5', session_id());
    }

    $twig->addGlobal('CSRFToken', $token);
    $twig->addGlobal('request', $_GET);
    $twig->addGlobal('guest', $di['api_guest']);
    return $twig;
});

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
    if (!$di['auth']->isClientLoggedIn()) {
        $api_str = '/api/';
        $url = $_GET['_url'] ?? ($_SERVER['PATH_INFO'] ?? '');

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
    if (!$di['auth']->isAdminLoggedIn()) {
        $api_str = '/api/';
        $url = $_GET['_url'] ?? ($_SERVER['PATH_INFO'] ?? '');

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

/*
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

/*
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

/*
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

/*
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_guest'] = function () use ($di) {
    return $di['api']('guest');
};

/*
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_client'] = function () use ($di) {
    return $di['api']('client');
};

/*
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_admin'] = function () use ($di) {
    return $di['api']('admin');
};

/*
 *
 * @param void
 *
 * @return \Api_Handler
 */
$di['api_system'] = function () use ($di) {
    return $di['api']('system');
};

$di['tools'] = function () use ($di) {
    $service = new \FOSSBilling\Tools();
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
    $validator = new \FOSSBilling\Validate();
    $validator->setDi($di);

    return $validator;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\CentralAlerts
 */
$di['central_alerts'] = function () use ($di) {
    $centralalerts = new \FOSSBilling\CentralAlerts();
    $centralalerts->setDi($di);

    return $centralalerts;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\CentralAlerts
 */
$di['central_alerts'] = function () use ($di) {
    $centralalerts = new \FOSSBilling\CentralAlerts();
    $centralalerts->setDi($di);

    return $centralalerts;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\ExtensionManager
 */
$di['extension_manager'] = function () use ($di) {
    $extension = new \FOSSBilling\ExtensionManager();
    $extension->setDi($di);

    return $extension;
};

/*
 *
 * @param void
 *
 * @return \FOSSBilling\Update
 */
$di['updater'] = function () use ($di) {
    $updater = new \FOSSBilling\Update();
    $updater->setDi($di);

    return $updater;
};

/*
 * @param void
 *
 * @return Server_Package
 */
$di['server_package'] = function () {
    return new Server_Package();
};

/*
 * @param void
 *
 * @return Server_Client
 */
$di['server_client'] = function () {
    return new Server_Client();
};

/*
 * @param void
 *
 * @return Server_Account
 */
$di['server_account'] = function () {
    return new Server_Account();
};

/*
 * Creates a new server manager object and returns it.
 *
 * @param string $manager The name of the server manager to create.
 * @param array $config The configuration options for the server manager.
 *
 * @return \Server_Manager The new server manager object that was just created.
 */
$di['server_manager'] = $di->protect(function($manager, $config) use ($di) {
    $class = sprintf('Server_Manager_%s', ucfirst($manager));

    $s = new $class($config);
    $s->setLog($di['logger']);

    return $s;
});

/*
 * @param void
 *
 * @return \FOSSBilling\Requirements
 */
$di['requirements'] = function () use ($di) {
    $r = new \FOSSBilling\Requirements();
    $r->setDi($di);

    return $r;
};

/*
 * Creates a new Box_Period object using the provided period code and returns it.
 *
 * @param string $code The two character period code to create the period object with.
 *
 * @return \Box_Period The new period object that was just created.
 */
$di['period'] = $di->protect(function ($code) {
    return new \Box_Period($code);
});

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
 * Gets the information of Webpack Encore for the current route theme.
 * @return string
 */
$di['encore_info'] = function () use ($di) {
    $service = $di['mod_service']('theme');

    return $service->getEncoreInfo();
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
 * Creates a new table object and returns it.
 *
 * @param string $name The name of the table to create.
 *
 * @return \Box_Table The new table object that was just created.
 */
$di['table'] = $di->protect(function ($name) use ($di) {
    $tools = new \FOSSBilling\Tools();
    $tools->setDi($di);
    $table = $tools->getTable($name);
    $table->setDi($di);

    return $table;
});

/*
 * @param void
 *
 * @return \Box\Mod\Servicelicense\Server
 */
$di['license_server'] = function () use ($di) {
    $server = new \Box\Mod\Servicelicense\Server($di['logger']);
    $server->setDi($di);

    return $server;
};

/*
 * @param void
 *
 * @return \GeoIp2\Database\Reader
 */
$di['geoip'] = function () {
    return new \GeoIp2\Database\Reader(PATH_LIBRARY . '/GeoLite2-Country.mmdb');
};

/*
 * @param void
 *
 * @return \Box_Password
 */
$di['password'] = function () {
    return new Box_Password();
};

/*
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

    $locale = \FOSSBilling\i18n::getActiveLocale();

    $tr->setDi($di);
    $tr->setLocale($locale);
    $tr->setup();

    return $tr;
});

/*
 * Creates a CSV export of data from a specified table and sends it to the browser.
 *
 * @param string $table Name of the table to export data from
 * @param string $outputName Name of the exported CSV file
 * @param array $headers Optional array of column headers for the CSV file
 * @param int $limit Optional limit of the number of rows to export from the table
 * @return void
 */
$di['table_export_csv'] = $di->protect(function (string $table, string $outputName = 'export.csv', array $headers = [], int $limit = 0) use ($di) {
    if ($limit > 0) {
        $beans = $di['db']->findAll($table, "LIMIT :limit", array(':limit' => $limit));
    } else {
        $beans = $di['db']->findAll($table);
    }

    $rows = array_map(function ($bean) {
        return $bean->export();
    }, $beans);

    // If we've been provided a list of headers, use that. Otherwise, pull the keys from the rows and use that for the CSV header
    if ($headers) {
        $rows = array_map(function ($row) use ($headers) {
            return array_intersect_key($row, array_flip($headers));
        }, $rows);
    } else {
        $headers = array_keys(reset($rows));
    }

    $csv = League\Csv\Writer::createFromFileObject(new SplTempFileObject());

    $csv->insertOne($headers);
    $csv->insertAll($rows);

    $csv->output($outputName);

    // Prevent further output from being added to the end of the CSV
    die();
});

return $di;
