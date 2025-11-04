<?php

putenv('APP_ENV=test');
define('PATH_TESTS', __DIR__);

require_once __DIR__ . '/../src/load.php';
require_once __DIR__ . '/../src/vendor/autoload.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'APIHelper.php';
