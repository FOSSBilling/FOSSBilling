<?php

putenv('APP_ENV=test');
define('PATH_TESTS', __DIR__);

require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/vendor/autoload.php';
$config = include __DIR__ . '/../src/config.php';

define('BB_DB_NAME', $config['db']['name']);
define('BB_DB_USER', $config['db']['user']);
define('BB_DB_PASSWORD', $config['db']['password']);
define('BB_DB_HOST', $config['db']['host']);
define('BB_DB_TYPE', $config['db']['type']);

// Add test libraries
set_include_path(implode(PATH_SEPARATOR, [
    get_include_path(),
    PATH_TESTS . '/library',
    PATH_TESTS . '/includes',
    PATH_TESTS . '/includes/Vps',
]));

require_once 'BBTestCase.php';
require_once 'BBDatabaseTestCase.php';
require_once 'BBDbApiTestCase.php';
require_once 'ApiTestCase.php';
require_once 'BBModTestCase.php';
require_once PATH_TESTS . '/includes/Payment/Adapter/Dummy.php';
require_once 'FakeTemplateWrapper.php';
require_once 'DummyBean.php';
$di = include PATH_ROOT . '/di.php';
$di['translate']();

// Setup the autoloader
$testsLoader = new AntCMS\AntLoader([
    'mode' => 'filesystem',
]);
$testsLoader->addNamespace('', DIRECTORY_SEPARATOR . 'library', 'psr0');
$testsLoader->register();
