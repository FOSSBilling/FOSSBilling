<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


$di = new Box_Di();
$di['config'] = function() {
    $array = include BB_PATH_ROOT . '/bb-config.php';
    return new Box_Config($array);
};
$di['logger'] = function () use ($di) {
    $log     = new Box_Log();
    $log->setDi($di);

    $log_to_db = isset($di['config']['log_to_db']) && $di['config']['log_to_db'];
    if ($log_to_db) {
        $activity_service = $di['mod_service']('activity');
        $writer2          = new Box_LogDb($activity_service);
        if ($di['auth']->isAdminLoggedIn()){
                $admin = $di['loggedin_admin'];
                $log->setEventItem('admin_id', $admin->id);
        }
        elseif ($di['auth']->isClientLoggedIn()) {
            $client = $di['loggedin_client'];
            $log->setEventItem('client_id', $client->id);
        }
        $log->addWriter($writer2);
    } else {
        $logFile = $di['config']['path_logs'];
        $writer  = new Box_LogStream($logFile);
        $log->addWriter($writer);
    }

    return $log;
};
$di['crypt'] = function() use ($di) {
    $crypt =  new Box_Crypt();
    $crypt->setDi($di);
    return $crypt;
};
$di['pdo'] = function() use ($di) {
    $c = $di['config']['db'];

    $pdo = new PDO($c['type'].':host='.$c['host'].';dbname='.$c['name'],
        $c['user'],
        $c['password'],
        array(
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY         => true,
            PDO::ATTR_ERRMODE                          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE               => PDO::FETCH_ASSOC,
        )
    );

    if(isset($c['debug']) && $c['debug']) {
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, array ('Box_DbLoggedPDOStatement'));
    }

    if($c['type'] == 'mysql') {
        $pdo->exec( 'SET NAMES "utf8"' );
        $pdo->exec( 'SET CHARACTER SET utf8' );
        $pdo->exec( 'SET CHARACTER_SET_CONNECTION = utf8' );
        $pdo->exec( 'SET CHARACTER_SET_DATABASE = utf8' );
        $pdo->exec( 'SET character_set_results = utf8' );
        $pdo->exec( 'SET character_set_server = utf8' );
        $pdo->exec( 'SET SESSION interactive_timeout = 28800' );
        $pdo->exec( 'SET SESSION wait_timeout = 28800' );
    }

    return $pdo;
};
$di['db'] = function() use ($di) {

    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'rb.php';
    R::setup($di['pdo']);

    $helper = new Box_BeanHelper();
    $helper->setDi($di);

    $mapper = new \RedBeanPHP\Facade();
    $mapper->getRedBean()->setBeanHelper($helper);
    $freeze = isset($di['config']['db']['freeze']) ? (bool)$di['config']['db']['freeze'] : true;
    $mapper->freeze($freeze);

    $db = new Box_Database();
    $db->setDi($di);
    $db->setDataMapper($mapper);
    return $db;
};
$di['pager'] = function() use($di) {
    $service = new Box_Pagination();
    $service->setDi($di);
    return $service;
};
$di['url'] = function() use ($di) {
    $url  = new Box_Url();
    $url->setDi($di);
    $url->setBaseUri(BB_URL);
    return $url;
};
$di['mod'] = $di->protect(function ($name) use($di) {
    $mod = new Box_Mod($name);
    $mod->setDi($di);
    return $mod;
});
$di['mod_service'] = $di->protect(function ($mod, $sub = '') use($di) {
    return $di['mod']($mod)->getService($sub);
});
$di['mod_config'] = $di->protect(function ($name) use($di) {
    return $di['mod']($name)->getConfig();
});
$di['events_manager'] = function() use ($di) {
    $service = new Box_EventManager();
    $service->setDi($di);
    return $service;
};
$di['session'] = function () use ($di) {
    $handler = new PdoSessionHandler($di['pdo']);
    return new Box_Session($handler);
};
$di['cookie'] = function () use ($di) {
    $service = new Box_Cookie();
    $service->setDi($di);
    return $service;
};
$di['request'] = function () use ($di) {
    $service = new Box_Request();
    $service->setDi($di);
    return $service;
};
$di['cache'] = function () use ($di) { return new FileCache();};
$di['auth'] = function () use ($di) { return new Box_Authorization($di);};
$di['twig'] = $di->factory(function () use ($di) {
    $config = $di['config'];
    $options = $config['twig'];

    $loader = new Twig\Loader\ArrayLoader();
    $twig = new Twig\Environment($loader, $options);

    $box_extensions = new Box_TwigExtensions();
    $box_extensions->setDi($di);

      //$twig->addExtension(new Twig\Extension\OptimizerExtension());
      $twig->addExtension(new \Twig\Extension\StringLoaderExtension());
      $twig->addExtension(new Twig\Extension\DebugExtension());
      $twig->addExtension(new Twig\Extensions\I18nExtension());
    $twig->addExtension($box_extensions);
      $twig->getExtension(Twig\Extension\CoreExtension::class)->setDateFormat($config['locale_date_format']);
      $twig->getExtension(Twig\Extension\CoreExtension::class)->setTimezone($config['timezone']);
  

    // add globals
    if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        $_GET['ajax'] = TRUE;
    }

    $twig->addGlobal('request', $_GET);
    $twig->addGlobal('guest', $di['api_guest']);

    return $twig;
});

$di['is_client_logged'] = function() use($di) {
    if(!$di['auth']->isClientLoggedIn()) {
        $api_str = '/api/';
        $url = $di['request']->getQuery('_url');
        if (strncasecmp($url, $api_str , strlen($api_str)) === 0) {
            //Throw Exception if api request
            throw new Exception('Client is not logged in');
        }
        else {            
            //Redirect to login page if browser request
            $login_url = $di['url']->link('login');
            header("Location: $login_url");
        }

    }
    return true;
};

$di['is_admin_logged'] = function() use($di) {
    
    if(!$di['auth']->isAdminLoggedIn()) {
    $api_str = '/api/';
    $url = $di['request']->getQuery('_url');
    if (strncasecmp($url, $api_str , strlen($api_str)) === 0) {
        //Throw Exception if api request
        throw new Exception('Admin is not logged in');
    }
    else {
        //Redirect to login page if browser request
        $login_url = $di['url']->adminLink('staff/login');
        header("Location: $login_url");
        }
    }
    return true;
};

$di['loggedin_client'] = function() use ($di) {
    $di['is_client_logged'];
    $client_id = $di['session']->get('client_id');
    return $di['db']->getExistingModelById('Client', $client_id);
};
$di['loggedin_admin'] = function() use ($di) {
    if(php_sapi_name() == 'cli' || substr(php_sapi_name(), 0, 3) == 'cgi') {
        return $di['mod_service']('staff')->getCronAdmin();
    }

    $di['is_admin_logged'];
    $admin = $di['session']->get('admin');
    return $di['db']->getExistingModelById('Admin', $admin['id']);
};

$di['api'] = $di->protect(function($role) use($di) {
    switch ($role) {
        case 'guest':
            $identity = new \Model_Guest();
            break;

        case 'client':
            $identity = $di['loggedin_client'];
            break;

        case 'admin':
            $identity = $di['loggedin_admin'];
            break;

        case 'system':
            $identity = $di['mod_service']('staff')->getCronAdmin();
           break;

        default:
            throw new Exception('Unrecognized Handler type: '.$role);
    }
    $api = new Api_Handler($identity);
    $api->setDi($di);
    return $api;
});
$di['api_guest'] = function() use ($di) { return $di['api']('guest'); };
$di['api_client'] = function() use ($di) { return $di['api']('client');};
$di['api_admin'] = function() use ($di) { return $di['api']('admin'); };
$di['api_system'] = function() use ($di) { return $di['api']('system'); };

$di['tools'] = function () use ($di){
    $service = new Box_Tools();
    $service->setDi($di);
    return $service;
};

$di['validator'] = function () use ($di){
    $validator = new Box_Validate();
    $validator->setDi($di);
    return $validator;
};
$di['guzzle_client'] = function () {
    return new GuzzleHttp\Client();
};
$di['mail'] = function () {
    return new Box_Mail();
};
$di['extension'] = function () use ($di) {
    $extension = new \Box_Extension();
    $extension->setDi($di);
    return $extension;
};
$di['updater'] = function () use ($di) {
    $updater = new \Box_Update();
    $updater->setDi($di);
    return $updater;
};
$di['curl'] = function ($url) use ($di) {
    $curl = new \Box_Curl($url);
    $curl->setDi($di);
    return $curl;

};
$di['zip_archive'] = function () use ($di) {return new ZipArchive();};

$di['server_package'] = function () use ($di) {return new Server_Package();};
$di['server_client'] = function () use ($di) {return new Server_Client();};
$di['server_account'] = function () use ($di) {return new Server_Account();};

$di['server_manager'] = $di->protect(function ($manager, $config) use($di) {
    $class = sprintf('Server_Manager_%s', ucfirst($manager));
    return new $class($config);
});

$di['requirements'] = function () use ($di) {
    $r = new Box_Requirements();
    $r->setDi($di);
    return $r;
};

$di['period'] = $di->protect(function($code) use($di){ return new \Box_Period($code); });

$di['theme'] = function() use($di) {
    $service = $di['mod_service']('theme');
    return $service->getCurrentClientAreaTheme();
};
$di['cart'] = function() use($di) {
    $service = $di['mod_service']('cart');
    return $service->getSessionCart();
};

$di['table'] = $di->protect(function ($name) use($di) {
    $tools = new Box_Tools();
    $tools->setDi($di);
    $table =  $tools->getTable($name);
    $table->setDi($di);
    return $table;
});

$di['license_server'] = function () use ($di) {
    $server = new \Box\Mod\Servicelicense\Server($di['logger']);
    $server->setDi($di);
    return $server;
};

$di['solusvm'] = $di->protect(function ($config) use($di) {
    $solusVM = new \Box\Mod\Servicesolusvm\SolusVM();
    $solusVM->setDi($di);
    $solusVM->setConfig($config);
    return $solusVM;
});

$di['service_boxbilling'] = $di->protect(function ($config) use($di) {
    $service = new \Box\Mod\Serviceboxbillinglicense\ServiceBoxbilling($config);
    return $service;
});

$di['ftp'] = $di->protect(function($params) use($di){ return new \Box_Ftp($params); });

$di['pdf'] = function () use ($di) {
    include BB_PATH_LIBRARY . '/PDF_ImageAlpha.php';
    return new \PDF_ImageAlpha();
};

$di['geoip'] = function () use ($di) { return new \GeoIp2\Database\Reader(BB_PATH_LIBRARY . '/GeoLite2-Country.mmdb'); };

$di['password'] = function() use ($di) { return new Box_Password();};
$di['translate'] = $di->protect(function($textDomain = '') use ($di) {
    $tr = new Box_Translate();
    if (!empty($textDomain)){
        $tr->setDomain($textDomain);
    }
    $cookieBBlang = $di['cookie']->get('BBLANG');
    $locale = !empty($cookieBBlang) ? $cookieBBlang  : $di['config']['locale'];

    $tr->setDi($di);
    $tr->setLocale($locale);
    $tr->setup();
    return $tr;
});
$di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
    return array_key_exists($key, $array)  ? $array[$key] : $default;
});
return $di;