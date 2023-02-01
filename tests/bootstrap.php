<?php
define('APPLICATION_ENV', 'testing');
define('PATH_TESTS', dirname(__FILE__));
require_once dirname(__FILE__) . '/../src/load.php';
$config = include dirname(__FILE__) . '/../src/config.php';

require_once dirname(__FILE__) . '/../src/vendor/autoload.php';

define('BB_DB_NAME', $config['db']['name']);
define('BB_DB_USER', $config['db']['user']);
define('BB_DB_PASSWORD', $config['db']['password']);
define('BB_DB_HOST', $config['db']['host']);
define('BB_DB_TYPE', $config['db']['type']);


// Add test libraries
set_include_path(implode(PATH_SEPARATOR, array(
    get_include_path(),
    PATH_TESTS . '/library',
    PATH_TESTS . '/includes',
    PATH_TESTS . '/includes/Vps',
)));


require_once 'BoxSessionMock.php';
require_once 'BBTestCase.php';
require_once 'BBDatabaseTestCase.php';
require_once 'BBDbApiTestCase.php';
require_once 'ApiTestCase.php';
require_once 'BBModTestCase.php';
require_once PATH_TESTS . '/includes/Payment/Adapter/Dummy.php';
require_once 'FakeTemplateWrapper.php';
require_once 'DummyBean.php';
/**/
$di = include PATH_ROOT . '/di.php';
$di['translate']();
