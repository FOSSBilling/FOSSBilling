<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');
const PATH_ROOT = __DIR__;
const PATH_VENDOR = PATH_ROOT . DIRECTORY_SEPARATOR. 'vendor';
const PATH_LIBRARY = PATH_ROOT . DIRECTORY_SEPARATOR. 'library';
const PATH_THEMES = PATH_ROOT . DIRECTORY_SEPARATOR. 'themes';
const PATH_MODS = PATH_ROOT . DIRECTORY_SEPARATOR. 'modules';
const PATH_LANGS = PATH_ROOT . DIRECTORY_SEPARATOR. 'locale';
const PATH_UPLOADS = PATH_ROOT . DIRECTORY_SEPARATOR. 'uploads';
const PATH_DATA = PATH_ROOT . DIRECTORY_SEPARATOR. 'data';
const isCLI = 'cli' === PHP_SAPI;

// Deprecated aliases
const BB_PATH_ROOT = PATH_ROOT;
const BB_PATH_VENDOR = PATH_VENDOR;
const BB_PATH_LIBRARY = PATH_LIBRARY;
const BB_PATH_THEMES = PATH_THEMES;
const BB_PATH_MODS = PATH_MODS;
const BB_PATH_LANGS = PATH_LANGS;
const BB_PATH_UPLOADS = PATH_UPLOADS;
const BB_PATH_DATA = PATH_DATA;

function handler_error(int $number, string $message, string $file, int $line)
{
    if (E_RECOVERABLE_ERROR === $number) {
        if (isCLI) {
            echo "Error #[$number] occurred in [$file] at line [$line]: [$message]";
        } else {
            handler_exception(new ErrorException($message, $number, 0, $file, $line));
        }
    } else {
        error_log($number . ' ' . $message . ' ' . $file . ' ' . $line);
    }

    return false;
}

// Removed Exception type. Some errors are thrown as exceptions causing fatal errors.
function handler_exception($e)
{
    if (isCLI) {
        echo 'Error #[' . $e->getCode() . '] occurred in [' . $e->getFile() . '] at line [' . $e->getLine() . ']: [' . trim(strip_tags($e->getMessage())) . ']';
    } else {
        if (APPLICATION_ENV === 'testing') {
            echo $e->getMessage() . PHP_EOL;

            return;
        }
        error_log($e->getMessage());

        if (defined('BB_MODE_API')) {
            $code = $e->getCode() ?: 9998;
            $result = ['result' => null, 'error' => ['message' => $e->getMessage(), 'code' => $code]];
            echo json_encode($result);

            return false;
        }

        if (defined('BB_DEBUG') && BB_DEBUG && file_exists(PATH_VENDOR)) {
            /**
             * If advanced debugging is enabled, print Whoops instead of our error page.
             * flip/whoops documentation: https://github.com/filp/whoops/blob/master/docs/API%20Documentation.md.
             */
            $whoops = new Run();
            $prettyPage = new PrettyPageHandler();
            $prettyPage->setPageTitle('An error ocurred');
            $prettyPage->addDataTable('FOSSBilling environment', [
        'PHP Version' => PHP_VERSION,
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
              font-family: Arial, Helvetica, sans-serif;
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
              background-image: url('data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgNTkuNDMgNjguNyI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiMwMDgxYzU7fS5jbHMtMntmaWxsOiNmZmY7fS5jbHMtM3tmaWxsOiNmYWM5NjU7fS5jbHMtNHtmaWxsOiMwNTA0MDc7fS5jbHMtNCwuY2xzLTV7c3Ryb2tlOiNmZmY7c3Ryb2tlLW1pdGVybGltaXQ6MTA7fS5jbHMtNXtmaWxsOiMxYTExMGY7fTwvc3R5bGU+PC9kZWZzPjxnIGlkPSJMYXllcl8xLTIiPjxnPjxnPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTkuODQsMzcuODZjLTEuMzcsMC0yLjUxLTEuMzUtMi41OC0zLjA4TDUuOTksNi41NmMtLjA4LTEuNzksMS4wMS0zLjMyLDIuNDQtMy40MUw1MS4yMywuNWguMTNjMS4zNywwLDIuNTEsMS4zNSwyLjU4LDMuMDdsMS4yNiwyOC4yM2MuMDgsMS43OS0xLjAxLDMuMzItMi40NCwzLjQxbC00Mi44LDIuNjVoLS4xM1oiLz48cGF0aCBjbGFzcz0iY2xzLTIiIGQ9Ik01MS4zNiwxYzEuMDksMCwyLjAyLDEuMTcsMi4wOCwyLjZsMS4yNiwyOC4yM2MuMDcsMS40OS0uODQsMi44Mi0xLjk3LDIuODlsLTQyLjgsMi42NXMtLjA3LDAtLjEsMGMtMS4wOSwwLTIuMDItMS4xNy0yLjA4LTIuNkw2LjQ5LDYuNTRjLS4wNy0xLjQ5LC44NC0yLjgyLDEuOTctMi44OUw1MS4yNywxcy4wNywwLC4xLDBNNTEuMzYsMGMtLjA1LDAtLjExLDAtLjE2LDBMOC40LDIuNjVjLTEuNywuMS0zLDEuODYtMi45MSwzLjkzbDEuMjYsMjguMjNjLjA5LDIsMS40NSwzLjU1LDMuMDgsMy41NSwuMDUsMCwuMTEsMCwuMTYsMGw0Mi44LTIuNjVjMS43LS4xMSwzLTEuODYsMi45MS0zLjkzbC0xLjI2LTI4LjIzQzU0LjM2LDEuNTUsNTIuOTksMCw1MS4zNiwwaDBaIi8+PC9nPjxwYXRoIGNsYXNzPSJjbHMtMyIgZD0iTTQ2LjU3LDIxLjkxbC02LjU3LC4zNGMtMS40NiwuMDgtMi43MS0xLjAxLTIuNzktMi40MmwtLjE0LTIuNTljLS4wOC0xLjQxLDEuMDUtMi42MiwyLjUxLTIuNjlsNi41Ny0uMzRjMS40Ni0uMDgsMi43MSwxLjAxLDIuNzksMi40MmwuMTQsMi41OWMuMDgsMS40MS0xLjA1LDIuNjItMi41MSwyLjY5WiIvPjxyZWN0IGNsYXNzPSJjbHMtNCIgeD0iMS43MiIgeT0iMjguMTYiIHdpZHRoPSI1NiIgaGVpZ2h0PSIzOCIgcng9IjQiIHJ5PSI0IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg0LjIyIC0yLjQxKSByb3RhdGUoNSkiLz48cGF0aCBjbGFzcz0iY2xzLTUiIGQ9Ik00Ny42Myw0NS4wOGgxMHY4aC0xMGMtMS4xLDAtMi0uOS0yLTJ2LTRjMC0xLjEsLjktMiwyLTJaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg0LjQ3IC00LjMxKSByb3RhdGUoNSkiLz48L2c+PC9nPjwvc3ZnPg==');
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
              font-size: 65px;
              font-weight: 700;
              margin-top: 0px;
              margin-bottom: 10px;
              color: #151723;
              text-transform: uppercase;
            }

            .error h2 {
              font-size: 21px;
              font-weight: 400;
              margin: 0;
              text-transform: uppercase;
              color: #151723;
            }

            .error p {
              color: #999fa5;
              font-weight: 400;
            }

            .error a {
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
              font-family: Monaco, Menlo, Consolas, 'Courier New', monospace;
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

            echo sprintf('<center><p><a href="https://fossbilling.org/docs/en/latest/search.html?q=%s&check_keywords=yes&area=default" target="_blank">Look for detailed error explanation</a></p></center>', urlencode($e->getMessage()));
            echo '<center><hr><p>Powered by <a href="https://fossbilling.org">FOSSBilling</a></p></center>
      </body>

      </html>';
        }
    }
}

set_exception_handler('handler_exception');
set_error_handler('handler_error');

// Check for Composer packages
if (!file_exists(PATH_VENDOR)) {
    throw new Exception("It seems like Composer packages are missing. You have to run \"<code>composer install</code>\" in order to install them. For detailed instruction, you can see <a href=\"https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies\">Composer's getting started guide</a>.<br /><br />If you have downloaded FOSSBilling from <a href=\"https://github.com/FOSSBilling/FOSSBilling/releases\">GitHub releases</a>, this shouldn't happen.", 110);
}

// Multisite support. Load new configuration depending on the current hostname
// If being run from CLI, first parameter must be the hostname
$configPath = PATH_ROOT . '/config.php';
if ((isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) || ('cli' === PHP_SAPI && isset($argv[1]))) {
    if ('cli' === PHP_SAPI) {
        $host = $argv[1];
    } else {
        $host = $_SERVER['HTTP_HOST'];
    }

    $predictConfigPath = PATH_ROOT . '/config-' . $host . '.php';
    if (file_exists($predictConfigPath)) {
        $configPath = $predictConfigPath;
    }
}

// Rename the old "bb-config" file to the new name if it exists
if(!file_exists($configPath) && file_exists(PATH_ROOT . '/bb-config.php')){
    rename(PATH_ROOT . '/bb-config.php', $configPath);
}

// Try to check if configuration is available
if (!file_exists($configPath) || 0 === filesize($configPath)) {
    // Try to create an empty configuration file
    @file_put_contents($configPath, '');

    $base_url = 'http' . ((isset($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS'] || 1 == $_SERVER['HTTPS'])) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    $base_url .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $url = $base_url . '/install/index.php';

    if (file_exists(PATH_ROOT . '/install/index.php')) {
        header("Location: $url");
    }

    $configFile = pathinfo($configPath, PATHINFO_BASENAME);
    $msg = "Your <b><em>$configFile</em></b> file seems to be invalid. It's possible that your preexisting configuration file may not contain the required configuration parameters or have become corrupted. FOSSBilling needs to have a valid configuration file present in order to function properly.</p> <p>Please use the example config as reference <a target='_blank' href='https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/master/src/config-sample.php'>here</a>. You may need to manually restore a old config file or fix your existing one.</p>";
    throw new Exception($msg, 101);
}

// Try to check if /install directory still exists, even after the installation was completed and display a message
if (file_exists($configPath) && 0 !== filesize($configPath) && file_exists(PATH_ROOT . '/install/index.php')) {
    throw new Exception('For safety reasons, you have to delete the <b><em>/install</em></b> directory to start using FOSSBilling.</p><p>Please delete the <b><em>/install</em></b> directory from your web server.', 102);
}

// Detect old for files from an old BoxBilling or FOSSBilling preview installation.
function detectOldFiles(){
    $i = 0;
    $msg = '';
    $foundOld = false;

    $oldFolderNames = ['bb-data','bb-library','bb-locale','bb-modules','bb-themes','bb-uploads','bb-cron.php','bb-di.php','bb-load.php','bb-config.php'];
    $newFolderNames = ['data','library','locale','modules','themes','uploads','cron.php','di.php','load.php','config.php'];

    foreach ($oldFolderNames as $folder){
        $toCheck = PATH_ROOT . DIRECTORY_SEPARATOR . $folder;
        $newName = $newFolderNames[$i];
        if(file_exists($toCheck) or is_dir($toCheck)){
            $msg .= "<b>$folder</b> --> <b>$newName</b> <br>";
            $foundOld = true;
        }
        $i++;
    }
    if($foundOld){
        $finalMsg = "The FOSSBilling file structure has been changed, please migrate any custom files and folders from the old folder to the new folder and then delete the old ones. You will also need to update the paths in your config.php file. <br>";
        $finalMsg .= "Feel free to join our <a href='https://fossbilling.org/discord'>Discord Server</a> for assistance. <br><br>";
        $finalMsg .= "Files and folders renamed: <br>";
        $finalMsg .= $msg;
        throw new Exception($finalMsg);
    }
}
detectOldFiles();

// Try to check if /install directory still exists, even after the installation was completed
if (file_exists($configPath) && 0 !== filesize($configPath) && file_exists(PATH_ROOT . '/install/index.php')) {
    throw new Exception('For safety reasons, you have to delete the <b><em>/install</em></b> directory to start using FOSSBilling.</p><p>Please delete the <b><em>/install</em></b> directory from your web server.', 102);
}

$config = require $configPath;
require PATH_VENDOR . '/autoload.php';

date_default_timezone_set($config['timezone']);

define('BB_DEBUG', $config['debug']);
define('BB_URL', $config['url']);
define('BB_SEF_URLS', $config['sef_urls']);
define('PATH_CACHE', $config['path_data'] . '/cache');
define('PATH_LOG', $config['path_data'] . '/log');
define('BB_SSL', str_starts_with($config['url'], 'https'));

if ($config['sef_urls']) {
    define('BB_URL_API', $config['url'] . 'api/');
} else {
    define('BB_URL_API', $config['url'] . 'index.php?_url=/api/');
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
ini_set('error_log', PATH_LOG . '/php_error.log');

$isApache = (function_exists('apache_get_version')) ? true : false;
$serverSoftware = (isset($_SERVER['SERVER_SOFTWARE'])) ? $_SERVER['SERVER_SOFTWARE'] : '';

if ($isApache or (false !== stripos($serverSoftware, 'apache'))) {
    if (!file_exists(PATH_ROOT . '/.htaccess')) {
        throw new Exception('Error: You appear to be running an Apache server without a valid <b><em>.htaccess</em></b> file. You may need to rename <b><em>htaccess.txt</em></b> to <b><em>.htaccess</em></b>');
    }
}

// If the configured security mode is strict, redirect to HTTPS
if (isset($config['security']['force_https']) && $config['security']['force_https'] && 'cli' !== PHP_SAPI){
    $isHTTPS = false;
    
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $isHTTPS = true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
        $isHTTPS = true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) == 'on') {
        $isHTTPS = true;
    }

    if(!$isHTTPS){
        $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $url);
        exit;
    }
}
