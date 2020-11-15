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

defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));
define('BB_PATH_ROOT',      dirname(__FILE__));
define('BB_PATH_VENDOR',    BB_PATH_ROOT . '/bb-vendor');
define('BB_PATH_LIBRARY',   BB_PATH_ROOT . '/bb-library');
define('BB_PATH_THEMES',    BB_PATH_ROOT . '/bb-themes');
define('BB_PATH_MODS',      BB_PATH_ROOT . '/bb-modules');
define('BB_PATH_LANGS',     BB_PATH_ROOT . '/bb-locale');
define('BB_PATH_UPLOADS',   BB_PATH_ROOT . '/bb-uploads');
define('BB_PATH_DATA',   BB_PATH_ROOT . '/bb-data');

function handler_error($number, $message, $file, $line)
{
    if (E_RECOVERABLE_ERROR===$number) {
        handler_exception(new ErrorException($message, $number, 0, $file, $line));
    } else {
        error_log($number." ".$message." ".$file." ".$line);
    }
    return false;
}

// Removed Exception type. Some errors are thrown as exceptions causing fatal errors.
function handler_exception($e)
{
    if(APPLICATION_ENV == 'testing') {
        print $e->getMessage() . PHP_EOL;
        return ;
    }
    error_log($e->getMessage());
    
    if(defined('BB_MODE_API')) {
        $code = $e->getCode() ? $e->getCode() : 9998;
        $result = array('result'=>NULL, 'error'=>array('message'=>$e->getMessage(), 'code'=>$code));
        print json_encode($result);
        return false;
    }

    $page = "<!DOCTYPE html>
    <html lang=en>
    <meta charset=utf-8>
    <title>Error</title>
    <style>
    *{margin:0;padding:0}html,code{font:15px/22px arial,sans-serif}html{background:#fff;color:#222;padding:15px}body{margin:7% auto 0;min-height:180px;padding:30px 0 15px}* > body{padding-right:205px}p{margin:11px 0 22px;overflow:hidden}ins{color:#777;text-decoration:none}a img{border:0} em{font-weight:bold}@media screen and (max-width:772px){body{background:none;margin-top:0;max-width:none;padding-right:0}}pre{ width: 100%; overflow:auto; }
    </style>
    <a href=//www.boxbilling.com/ target='_blank'><img src='/bb-themes/boxbilling/assets/images/logo.png' alt='BoxBilling' style='height:60px'></a>
    ";
    $page = str_replace(PHP_EOL, "", $page);
    print $page;
    if($e->getCode()) {
        print sprintf('<p>Code: <em>%s</em></p>', $e->getCode());
    }
    print sprintf('<p>%s</p>', $e->getMessage());
    print sprintf('<p><a href="http://docs.boxbilling.com/en/latest/search.html?q=%s&check_keywords=yes&area=default" target="_blank">Look for detailed error explanation</a></p>', urlencode($e->getMessage()));

    if(defined('BB_DEBUG') && BB_DEBUG) {
        print sprintf('<em>%s</em>', 'Set BB_DEBUG to FALSE, to hide the message below');
        print sprintf('<p>Class: "%s"</p>', get_class($e));
        print sprintf('<p>File: "%s"</p>', $e->getFile());
        print sprintf('<p>Line: "%s"</p>', $e->getLine());
        print sprintf('Trace: <pre>%s</pre>', $e->getTraceAsString());
    }
}

set_exception_handler("handler_exception");
set_error_handler('handler_error');

// multisite support. Load new config depending on current host
// if run from cli first param must be hostname
$configPath = BB_PATH_ROOT.'/bb-config.php';
if((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) || (php_sapi_name() == 'cli' && isset($argv[1]) ) ) {
    if(php_sapi_name() == 'cli') {
        $host = $argv[1];
    } else {
        $host = $_SERVER['HTTP_HOST'];
    }
    
    $predictConfigPath = BB_PATH_ROOT.'/bb-config-'.$host.'.php';
    if(file_exists($predictConfigPath)) {
        $configPath = $predictConfigPath;
    }
}

// check if config is available
if(!file_exists($configPath) || 0 == filesize( $configPath )) {
    
    //try create empty config file
    @file_put_contents($configPath, '');
    
    $base_url = "http://".$_SERVER['HTTP_HOST'];
    $base_url .= preg_replace('@/+$@','',dirname($_SERVER['SCRIPT_NAME'])).'/';
    $url = $base_url . 'install/index.php';
    $configFile = pathinfo($configPath, PATHINFO_BASENAME);
    $msg = sprintf("There doesn't seem to be a <em>$configFile</em> file or bb-config.php file does not contain required configuration parameters. I need this before we can get started. Need more help? <a target='_blank' href='http://docs.boxbilling.com/en/latest/reference/installation.html'>We got it</a>. You can create a <em>$configFile</em> file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file.</p><p><a href='%s' class='button'>Continue with BoxBilling installation</a>", $url);
    throw new Exception($msg, 101);
}

$config = require_once $configPath;
require BB_PATH_VENDOR . '/autoload.php';

date_default_timezone_set($config['timezone']);

define('BB_DEBUG',          $config['debug']);
define('BB_URL',            $config['url']);
define('BB_SEF_URLS',       $config['sef_urls']);
define('BB_PATH_CACHE',     $config['path_data'] . '/cache');
define('BB_PATH_LOG',       $config['path_data'] . '/log');
define('BB_SSL',            (substr($config['url'], 0, 5) === 'https'));

if($config['sef_urls']) {
    define('BB_URL_API',    $config['url'] . 'api/');
} else {
    define('BB_URL_API',    $config['url'] . 'index.php?_url=/api/');
}

if($config['debug']) {
    error_reporting( E_ALL );
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting( E_RECOVERABLE_ERROR );
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

ini_set('log_errors', '1');
ini_set('html_errors', FALSE);
ini_set('error_log', BB_PATH_LOG . '/php_error.log');