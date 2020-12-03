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
define('isCLI', php_sapi_name() == 'cli');

function handler_error(int $number, string $message, string $file, int $line)
{
    if (E_RECOVERABLE_ERROR===$number) {
      if (isCLI) {
        echo "Error #[$number] occurred in [$file] at line [$line]: [$message]";
      } else {
        handler_exception(new ErrorException($message, $number, 0, $file, $line));
      }
    } else {
        error_log($number." ".$message." ".$file." ".$line);
    }
    return false;
}

// Removed Exception type. Some errors are thrown as exceptions causing fatal errors.
function handler_exception($e)
{
  if (isCLI) {
    echo "Error #[". $e->getCode() ."] occurred in [". $e->getFile() ."] at line [". $e->getLine() ."]: [". trim(strip_tags($e->getMessage())) ."]";
  } else {
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
    <html lang=\"en\">
    
    <head>
        <meta charset=\"utf-8\">
        <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
        <title>An error ocurred</title>
    
        <!-- Google font -->
        <link href=\"https://fonts.googleapis.com/css?family=Nunito:400,700\" rel=\"stylesheet\">
    
        <!-- Custom stlylesheet -->
        <link type=\"text/css\" rel=\"stylesheet\" href=\"css/style.css\" />
    
        <!--[if lt IE 9]>
            <script src=\"https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js\"></script>
            <script src=\"https://oss.maxcdn.com/respond/1.4.2/respond.min.js\"></script>
        <![endif]-->
        <style>
        * {
            -webkit-box-sizing: border-box;
                    box-sizing: border-box;
          }
          
          body {
            padding: 0;
            margin: 0;
          }
          
          #error {
            position: relative;
            height: 100vh;
          }
          
          #error .error {
            position: absolute;
            left: 50%;
            top: 50%;
            -webkit-transform: translate(-50%, -50%);
                -ms-transform: translate(-50%, -50%);
                    transform: translate(-50%, -50%);
          }
          
          .error {
            max-width: 560px;
            width: 100%;
            padding-left: 160px;
            line-height: 1.1;
          }
          
          .error .error-container {
            position: absolute;
            left: 0;
            top: 0;
            display: inline-block;
            width: 140px;
            height: 140px;
            background-image: url('/bb-themes/boxbilling/assets/images/box.png');
            background-size: cover;
          }
          
          .error .error-container:before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-transform: scale(2.4);
                -ms-transform: scale(2.4);
                    transform: scale(2.4);
            border-radius: 50%;
            background-color: #f2f5f8;
            z-index: -1;
          }
          
          .error h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 65px;
            font-weight: 700;
            margin-top: 0px;
            margin-bottom: 10px;
            color: #151723;
            text-transform: uppercase;
          }
          
          .error h2 {
            font-family: 'Nunito', sans-serif;
            font-size: 21px;
            font-weight: 400;
            margin: 0;
            text-transform: uppercase;
            color: #151723;
          }
          
          .error p {
            font-family: 'Nunito', sans-serif;
            color: #999fa5;
            font-weight: 400;
          }
          
          .error a {
            font-family: 'Nunito', sans-serif;
            display: inline-block;
            font-weight: 700;
            border-radius: 40px;
            text-decoration: none;
            color: #388dbc;
          }
          
          @media only screen and (max-width: 767px) {
            .error .error-container {
              width: 110px;
              height: 110px;
            }
            .error {
              padding-left: 15px;
              padding-right: 15px;
              padding-top: 110px;
            }
          }

          code {
            font-family: Consolas,'courier new';
            color: crimson;
            background-color: #f1f1f1;
            padding: 2px;
            font-size: 90%;
          }
          
        </style>
    </head>
    <body>

    <div id=\"error\">
        <div class=\"error\">
            <div class=\"error-container\"></div>
            <h1>Error</h1>";
    $page = str_replace(PHP_EOL, "", $page);
    print $page;
    if($e->getCode()) {
        print sprintf('<h2>Error code: <em>%s</em></h1>', $e->getCode());
    }
    print sprintf('<p>%s</p>', $e->getMessage());
    
    if(defined('BB_DEBUG') && BB_DEBUG) {
        print sprintf('<hr><center><small><p><b>%s</b></p></small></center>', 'Set BB_DEBUG to FALSE, to hide the message below');
        print sprintf('<p>Class: "%s"</p>', get_class($e));
        print sprintf('<p>File: "%s"</p>', $e->getFile());
        print sprintf('<p>Line: "%s"</p>', $e->getLine());
        print sprintf('Trace: <code>%s</code>', $e->getTraceAsString());
    }
    print sprintf('<center><p><a href="http://docs.boxbilling.com/en/latest/search.html?q=%s&check_keywords=yes&area=default" target="_blank">Look for detailed error explanation</a></p></center>', urlencode($e->getMessage()));
    print("<center><hr><p>Powered by <a href=//www.boxbilling.com/>BoxBilling</a></p></center>
    </body>
    
    </html>");
  }
}

set_exception_handler("handler_exception");
set_error_handler('handler_error');

// Check for Composer packages
$vendorPath = BB_PATH_ROOT.'/bb-vendor';
if(!file_exists($vendorPath)) {
  throw new Exception("It seems like Composer packages are missing. You have to run \"<code>composer install</code>\" in order to install them. For detailed instruction, you can see <a href=\"https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies\">Composer's getting started guide</a>.<br /><br />If you have downloaded BoxBilling from <a href=\"https://github.com/boxbilling/boxbilling/releases\">GitHub releases</a>, this shouldn't happen.", 103);
}

// Multisite support. Load new configuration depending on the current hostname
// If being run from CLI, first parameter must be the hostname
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

// Try to check if configuration is available
if(!file_exists($configPath) || 0 == filesize($configPath)) {
    
    // Try to create an empty configuration file
    @file_put_contents($configPath, '');
    
    $base_url = "http".(isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ? 's' : '')."://".$_SERVER['HTTP_HOST'];
    $base_url .= preg_replace('@/+$@','',dirname($_SERVER['SCRIPT_NAME']));
    $url = $base_url . '/install/index.php';
    $configFile = pathinfo($configPath, PATHINFO_BASENAME);
    $msg = sprintf("The <em>$configFile</em> path seems to be invalid. You may have not generated the configuration file yet, or the configuration file may not contain the required configuration parameters. BoxBilling needs to have a valid configuration file present in order to function properly.</p> <p>Need some help with the installation? <a target='_blank' href='http://docs.boxbilling.com/en/latest/reference/installation.html'>We got it</a>. You can create the configuration file using the interactive installer, or manually create the configuration file.</p> <p>If it's your first time setting up BoxBilling, you can <a href='%s' class='button'>continue with the web-based interactive BoxBilling installation</a>.", $url);
    throw new Exception($msg, 101);
}

// Try to check if /install directory still exists, even after the installation was completed
if(file_exists($configPath) && 0 !== filesize($configPath) && file_exists(BB_PATH_ROOT.'/install/index.php')) {
    throw new Exception("For safety reasons, you have to delete the <b><em>/install</em></b> directory to start using BoxBilling.</p><p>Please delete the <b><em>/install</em></b> directory from your web server.", 102);
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
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_RECOVERABLE_ERROR);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

ini_set('log_errors', '1');
ini_set('html_errors', FALSE);
ini_set('error_log', BB_PATH_LOG . '/php_error.log');
