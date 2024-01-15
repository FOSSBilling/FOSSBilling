<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "APIHelper.php";
$path = realpath(getenv("APP_PATH"));
define("APP_PATH", $path);
