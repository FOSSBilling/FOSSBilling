<?php
define('APPLICATION_ENV', 'testing');
define('BB_PATH_TESTS', dirname(__FILE__));
require_once dirname(__FILE__) . '/../src/bb-load.php';
$config = include dirname(__FILE__) . '/../src/bb-config.php';

require_once dirname(__FILE__) . '/../src/bb-vendor/autoload.php';
require_once dirname(__FILE__) . '/../src/rb.php';

define('BB_DB_NAME', $config['db']['name']);
define('BB_DB_USER', $config['db']['user']);
define('BB_DB_PASSWORD', $config['db']['password']);
define('BB_DB_HOST', $config['db']['host']);
define('BB_DB_TYPE', $config['db']['type']);


// Add test libraries
set_include_path(implode(PATH_SEPARATOR, array(
    get_include_path(),
    BB_PATH_TESTS.'/bb-library',
    BB_PATH_TESTS.'/includes',
    BB_PATH_TESTS.'/includes/Vps',
)));


require_once 'SolusvmMock.php';
require_once 'BoxSessionMock.php';
require_once 'BBTestCase.php';
require_once 'BBDatabaseTestCase.php'; 
require_once 'BBDbApiTestCase.php';
require_once 'ApiTestCase.php';
require_once 'BBModTestCase.php';
require_once BB_PATH_TESTS.'/includes/Payment/Adapter/Dummy.php';
require_once 'FakeTemplateWrapper.php';
/**/
$di = include BB_PATH_ROOT . '/bb-di.php';
$di['translate']();