<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));
define('BB_PATH_ROOT', dirname(__FILE__));
define('BB_PATH_VENDOR', BB_PATH_ROOT.'/vendor');
define('BB_PATH_LIBRARY', BB_PATH_ROOT.'/bb-library');
define('BB_PATH_THEMES', BB_PATH_ROOT.'/bb-themes');
define('BB_PATH_MODS', BB_PATH_ROOT.'/bb-modules');
define('BB_PATH_LANGS', BB_PATH_ROOT.'/bb-locale');
define('BB_PATH_UPLOADS', BB_PATH_ROOT.'/bb-uploads');
define('BB_PATH_DATA', BB_PATH_ROOT.'/bb-data');
define('isCLI', 'cli' == php_sapi_name());

function handler_error(int $number, string $message, string $file, int $line)
{
    if (E_RECOVERABLE_ERROR === $number) {
        if (isCLI) {
            echo "Error #[$number] occurred in [$file] at line [$line]: [$message]";
        } else {
            handler_exception(new ErrorException($message, $number, 0, $file, $line));
        }
    } else {
        error_log($number.' '.$message.' '.$file.' '.$line);
    }

    return false;
}

// Removed Exception type. Some errors are thrown as exceptions causing fatal errors.
function handler_exception($e)
{
    if (isCLI) {
        echo 'Error #['.$e->getCode().'] occurred in ['.$e->getFile().'] at line ['.$e->getLine().']: ['.trim(strip_tags($e->getMessage())).']';
    } else {
        if (APPLICATION_ENV == 'testing') {
            echo $e->getMessage().PHP_EOL;

            return;
        }
        error_log($e->getMessage());

        if (defined('BB_MODE_API')) {
            $code = $e->getCode() ? $e->getCode() : 9998;
            $result = ['result' => null, 'error' => ['message' => $e->getMessage(), 'code' => $code]];
            echo json_encode($result);

            return false;
        }

        if (defined('BB_DEBUG') && BB_DEBUG && file_exists(BB_PATH_VENDOR)) {
            /**
             * If advanced debugging is enabled, print Whoops instead of our error page.
             * flip/whoops documentation: https://github.com/filp/whoops/blob/master/docs/API%20Documentation.md.
             */
            $whoops = new \Whoops\Run();
            $prettyPage = new \Whoops\Handler\PrettyPageHandler();
            $prettyPage->setPageTitle('An error ocurred');
            $prettyPage->addDataTable('FOSSBilling environment', [
        'PHP Version' => phpversion(),
        'Error code' => $e->getCode(),
      ]);
            $whoops->pushHandler($prettyPage);
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);

            echo $whoops->handleException($e);
        } else {
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
              width: 150px;
              height: 150px;
              background-image: url('/bb-themes/boxbilling/assets/images/fb_wallet.svg');
              background-repeat: no-repeat;
              background-size: 124px 144px;
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

            $page = str_replace(PHP_EOL, '', $page);
            echo $page;
            if ($e->getCode()) {
                echo sprintf('<h2>Error code: <em>%s</em></h1>', $e->getCode());
            }
            echo sprintf('<p>%s</p>', $e->getMessage());

            echo sprintf('<center><p><a href="https://docs.fossbilling.org/en/latest/search.html?q=%s&check_keywords=yes&area=default" target="_blank">Look for detailed error explanation</a></p></center>', urlencode($e->getMessage()));
            echo '<center><hr><p>Powered by <a href="https://fossbilling.org">FOSSBilling</a></p></center>
      </body>
      
      </html>';
        }
    }
}

set_exception_handler('handler_exception');
set_error_handler('handler_error');

// Check for Composer packages
if (!file_exists(BB_PATH_VENDOR)) {
    throw new Exception("It seems like Composer packages are missing. You have to run \"<code>composer install</code>\" in order to install them. For detailed instruction, you can see <a href=\"https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies\">Composer's getting started guide</a>.<br /><br />If you have downloaded FOSSBilling from <a href=\"https://github.com/FOSSBilling/FOSSBilling/releases\">GitHub releases</a>, this shouldn't happen.", 110);
}

// Multisite support. Load new configuration depending on the current hostname
// If being run from CLI, first parameter must be the hostname
$configPath = BB_PATH_ROOT.'/bb-config.php';
if ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) || ('cli' == php_sapi_name() && isset($argv[1]))) {
    if ('cli' == php_sapi_name()) {
        $host = $argv[1];
    } else {
        $host = $_SERVER['HTTP_HOST'];
    }

    $predictConfigPath = BB_PATH_ROOT.'/bb-config-'.$host.'.php';
    if (file_exists($predictConfigPath)) {
        $configPath = $predictConfigPath;
    }
}

// Try to check if configuration is available
if (!file_exists($configPath) || 0 == filesize($configPath)) {
    // Try to create an empty configuration file
    @file_put_contents($configPath, '');

    $base_url = 'http'.(isset($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO'] ? 's' : '').'://'.$_SERVER['HTTP_HOST'];
    $base_url .= preg_replace('@/+$@', '', dirname($_SERVER['SCRIPT_NAME']));
    $url = $base_url.'/install/index.php';

    if (file_exists(BB_PATH_ROOT.'/install/index.php')) {
        header("Location: $url");
    }

    $configFile = pathinfo($configPath, PATHINFO_BASENAME);
    $msg = sprintf("Your <b><em>$configFile</em></b> file seems to be invalid. It's possible that your preexisting configuration file may not contain the required configuration parameters or have become corrupted. FOSSBilling needs to have a valid configuration file present in order to function properly.</p> <p>Please use the example config as reference <a target='_blank' href='https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/master/src/bb-config-sample.php'>here</a>. You may need to manually restore a old config file or fix your existing one.</p>");
    throw new Exception($msg, 101);
}

// Try to check if /install directory still exists, even after the installation was completed
if (file_exists($configPath) && 0 !== filesize($configPath) && file_exists(BB_PATH_ROOT.'/install/index.php')) {
    throw new Exception('For safety reasons, you have to delete the <b><em>/install</em></b> directory to start using FOSSBilling.</p><p>Please delete the <b><em>/install</em></b> directory from your web server.', 102);
}

$config = require_once $configPath;
require BB_PATH_VENDOR.'/autoload.php';

date_default_timezone_set($config['timezone']);

define('BB_DEBUG', $config['debug']);
define('BB_URL', $config['url']);
define('BB_SEF_URLS', $config['sef_urls']);
define('BB_PATH_CACHE', $config['path_data'].'/cache');
define('BB_PATH_LOG', $config['path_data'].'/log');
define('BB_SSL', ('https' === substr($config['url'], 0, 5)));

if ($config['sef_urls']) {
    define('BB_URL_API', $config['url'].'api/');
} else {
    define('BB_URL_API', $config['url'].'index.php?_url=/api/');
}

if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_RECOVERABLE_ERROR);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

ini_set('log_errors', '1');
ini_set('html_errors', false);
ini_set('error_log', BB_PATH_LOG.'/php_error.log');
