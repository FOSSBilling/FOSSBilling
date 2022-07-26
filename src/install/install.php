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

/**
 * @return bool
 *
 * @see http://stackoverflow.com/a/2886224/2728507
 */
function isSSL()
{
    return
        (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS'])
        || 443 == $_SERVER['SERVER_PORT'];
}

date_default_timezone_set('UTC');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
ini_set('log_errors', '1');
ini_set('error_log', dirname(__FILE__) . '/logs/php_error.log');

$protocol = isSSL() ? 'https' : 'http';
$url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$current_url = pathinfo($url, PATHINFO_DIRNAME);
$root_url = str_replace('/install', '', $current_url) . '/';

define('BB_URL', $root_url);
define('BB_URL_INSTALL', BB_URL . 'install/');
define('BB_URL_ADMIN', BB_URL . 'index.php?_url=/bb-admin');

define('BB_PATH_ROOT', realpath(dirname(__FILE__) . '/..'));
define('BB_PATH_LIBRARY', BB_PATH_ROOT . '/bb-library');
define('BB_PATH_VENDOR', BB_PATH_ROOT . '/vendor');
define('BB_PATH_INSTALL_THEMES', BB_PATH_ROOT . '/install');
define('BB_PATH_THEMES', BB_PATH_ROOT . '/bb-themes');
define('BB_PATH_LICENSE', BB_PATH_ROOT . '/LICENSE');
define('BB_PATH_SQL', BB_PATH_ROOT . '/install/sql/structure.sql');
define('BB_PATH_SQL_DATA', BB_PATH_ROOT . '/install/sql/content.sql');
define('BB_PATH_INSTALL', BB_PATH_ROOT . '/install');
define('BB_PATH_CONFIG', BB_PATH_ROOT . '/bb-config.php');
define('BB_PATH_CRON', BB_PATH_ROOT . '/bb-cron.php');
define('BB_PATH_LANGS', BB_PATH_ROOT . '/bb-locale');

/*
  Config paths & templates
*/
define('BB_PATH_HTACCESS', BB_PATH_ROOT . '/.htaccess');
define('BB_PATH_HTACCESS_TEMPLATE', BB_PATH_ROOT . '/htaccess.txt');

define('BB_BS_CONFIG', BB_PATH_THEMES . '/bootstrap/config/settings_data.json');
define('BB_BS_CONFIG_TEMPLATE', BB_PATH_THEMES . '/bootstrap/config/settings_data.json.txt');

define('BB_HURAGA_CONFIG', BB_PATH_THEMES . '/huraga/config/settings_data.json');
define('BB_HURAGA_CONFIG_TEMPLATE', BB_PATH_THEMES . '/huraga/config/settings_data.json.txt');

define('BB_BBTHEME_CONFIG', BB_PATH_THEMES . '/boxbilling/config/settings_data.json');
define('BB_BBTHEM_CONFIG_TEMPLATE', BB_PATH_THEMES . '/boxbilling/config/settings_data.json.txt');

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, [
    BB_PATH_LIBRARY,
    get_include_path(),
]));

require BB_PATH_VENDOR . '/autoload.php';

final class Box_Installer
{
    private $session;

    public function __construct()
    {
        include 'session.php';
        $this->session = new Session();
    }

    public function run($action)
    {
        switch ($action) {
            case 'check-db':
                $user = $_POST['db_user'];
                $host = $_POST['db_host'];
                $pass = $_POST['db_pass'];
                $name = $_POST['db_name'];
                if (!$this->canConnectToDatabase($host, $name, $user, $pass)) {
                    echo 'Could not connect to database. Please check database details. You might need to create database first.';
                } else {
                    $this->session->set('db_host', $host);
                    $this->session->set('db_name', $name);
                    $this->session->set('db_user', $user);
                    $this->session->set('db_pass', $pass);
                    echo 'ok';
                }

                break;

            case 'install':
                try {
                    // Initializing database connection
                    $user = $_POST['db_user'];
                    $host = $_POST['db_host'];
                    $pass = $_POST['db_pass'];
                    $name = $_POST['db_name'];
                    if (!$this->canConnectToDatabase($host, $name, $user, $pass)) {
                        throw new Exception('Could not connect to the database, or the database does not exist');
                    } else {
                        $this->session->set('db_host', $host);
                        $this->session->set('db_name', $name);
                        $this->session->set('db_user', $user);
                        $this->session->set('db_pass', $pass);
                    }

                    // Configuring administrator's account
                    $admin_email = $_POST['admin_email'];
                    $admin_pass = $_POST['admin_pass'];
                    $admin_name = $_POST['admin_name'];
                    if (!$this->isValidAdmin($admin_email, $admin_pass, $admin_name)) {
                        throw new Exception('Administrator\'s account is not valid');
                    } else {
                        $this->session->set('admin_email', $admin_email);
                        $this->session->set('admin_pass', $admin_pass);
                        $this->session->set('admin_name', $admin_name);
                    }

                    $this->session->set('license', 'FOSSBilling CE');
                    $this->makeInstall($this->session);
                    $this->generateEmailTemplates();
                    session_destroy();
                    // Try to remove install folder
                    function rmAllDir($dir)
                    {
                        if (is_dir($dir)) {
                            $contents = scandir($dir);
                            foreach ($contents as $content) {
                                if ('.' != $content && '..' != $content) {
                                    if ('dir' == filetype($dir . '/' . $content)) {
                                        rmAllDir($dir . '/' . $content);
                                    } else {
                                        unlink($dir . '/' . $content);
                                    }
                                }
                            }
                            reset($contents);
                            rmdir($dir);
                        }
                    }
                    try {
                        rmAllDir('../install');
                    } catch (Exception $e) {
                        // do nothing
                    }
                    echo 'ok';
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
                break;

            case 'index':
            default:
                $this->session->set('agree', true);

                $se = new Box_Requirements();
                $options = $se->getOptions();
                $vars = [
                    'tos' => $this->getLicense(),

                    'folders' => $se->folders(),
                    'files' => $se->files(),
                    'os' => PHP_OS,
                    'os_ok' => true,
                    'box_ver' => Box_Version::VERSION,
                    'box_ver_ok' => $se->isBoxVersionOk(),
                    'php_ver' => $options['php']['version'],
                    'php_ver_req' => $options['php']['min_version'],
                    'php_safe_mode' => $options['php']['safe_mode'],
                    'php_ver_ok' => $se->isPhpVersionOk(),
                    'extensions' => $se->extensions(),
                    'all_ok' => $se->canInstall(),

                    'db_host' => $this->session->get('db_host'),
                    'db_name' => $this->session->get('db_name'),
                    'db_user' => $this->session->get('db_user'),
                    'db_pass' => $this->session->get('db_pass'),

                    'admin_email' => $this->session->get('admin_email'),
                    'admin_pass' => $this->session->get('admin_pass'),
                    'admin_name' => $this->session->get('admin_name'),

                    'license' => $this->session->get('license'),
                    'agree' => $this->session->get('agree'),

                    'install_module_path' => BB_PATH_INSTALL,
                    'cron_path' => BB_PATH_CRON,
                    'config_file_path' => BB_PATH_CONFIG,
                    'live_site' => BB_URL,
                    'admin_site' => BB_URL_ADMIN,

                    'domain' => pathinfo(BB_URL, PATHINFO_BASENAME),
                ];
                echo $this->render('./assets/install.html.twig', $vars);
                break;
        }
    }

    private function render($name, $vars = [])
    {
        $options = [
            'paths' => [BB_PATH_INSTALL_THEMES],
            'debug' => true,
            'charset' => 'utf-8',
            'optimizations' => 1,
            'autoescape' => 'html',
            'auto_reload' => true,
            'cache' => false,
        ];
        $loader = new \Twig\Loader\FilesystemLoader($options['paths']);
        $twig = new Twig\Environment($loader, $options);
        // $twig->addExtension(new Twig_Extension_Optimizer());
        $twig->addGlobal('request', $_REQUEST);
        $twig->addGlobal('version', Box_Version::VERSION);

        return $twig->render($name, $vars);
    }

    private function getLicense()
    {
        $path = BB_PATH_LICENSE;
        if (!file_exists($path)) {
            return 'FOSSBilling is licensed under the Apache License, Version 2.0.' . PHP_EOL . 'Please visit https://github.com/FOSSBilling/FOSSBilling/blob/master/LICENSE for full license text.';
        }

        return file_get_contents($path);
    }

    private function getPdo($host, $db, $user, $pass)
    {
        $pdo = new \PDO('mysql:host=' . $host,
            $user,
            $pass,
            [
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $pdo->exec('SET NAMES "utf8"');
        $pdo->exec('SET CHARACTER SET utf8');
        $pdo->exec('SET CHARACTER_SET_CONNECTION = utf8');
        $pdo->exec('SET character_set_results = utf8');
        $pdo->exec('SET character_set_server = utf8');
        $pdo->exec('SET SESSION interactive_timeout = 28800');
        $pdo->exec('SET SESSION wait_timeout = 28800');

        // try create database if permissions allows
        try {
            $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8 COLLATE utf8_general_ci;");
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }

        $pdo->query("USE `$db`;");

        return $pdo;
    }

    private function canConnectToDatabase($host, $db, $user, $pass)
    {
        try {
            $this->getPdo($host, $db, $user, $pass);
        } catch (\Exception $e) {
            error_log($e->getMessage());

            return false;
        }

        return true;
    }

    private function isValidAdmin($email, $pass, $name)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (empty($pass)) {
            return false;
        }

        if (empty($name)) {
            return false;
        }

        return true;
    }

    private function makeInstall($ns)
    {
        $this->_isValidInstallData($ns);
        $this->_createConfigurationFile($ns);

        $pdo = $this->getPdo($ns->get('db_host'), $ns->get('db_name'), $ns->get('db_user'), $ns->get('db_pass'));

        $sql = file_get_contents(BB_PATH_SQL);
        $sql_content = file_get_contents(BB_PATH_SQL_DATA);

        if (!$sql || !$sql_content) {
            throw new Exception('Could not read structure.sql file');
        }

        $sql .= $sql_content;

        $sql = preg_split('/\;[\r]*\n/ism', $sql);
        $sql = array_map('trim', $sql);
        $err = '';
        foreach ($sql as $query) {
            if (!trim($query)) {
                continue;
            }

            $res = $pdo->query($query);
        }

        $passwordObject = new \Box_Password();
        $stmt = $pdo->prepare("INSERT INTO admin (role, name, email, pass, protected, created_at, updated_at) VALUES('admin', :admin_name, :admin_email, :admin_pass, 1, NOW(), NOW());");
        $stmt->execute([
            'admin_name' => $ns->get('admin_name'),
            'admin_email' => $ns->get('admin_email'),
            'admin_pass' => $passwordObject->hashIt($ns->get('admin_pass')),
        ]);

        try {
            $this->_sendMail($ns);
        } catch (Exception $e) {
            // E-mail was not sent, but that is not a problem
            error_log($e->getMessage());
        }

        /*
          Copy config templates when applicable
        */
        if (!file_exists(BB_PATH_HTACCESS) && file_exists(BB_PATH_HTACCESS_TEMPLATE)) {
            rename(BB_PATH_HTACCESS_TEMPLATE, BB_PATH_HTACCESS);
        }

        if (!file_exists(BB_BS_CONFIG) && file_exists(BB_BS_CONFIG_TEMPLATE)) {
            rename(BB_BS_CONFIG_TEMPLATE, BB_BS_CONFIG);
        }

        if (!file_exists(BB_HURAGA_CONFIG) && file_exists(BB_HURAGA_CONFIG_TEMPLATE)) {
            rename(BB_HURAGA_CONFIG_TEMPLATE, BB_HURAGA_CONFIG);
        }

        if (!file_exists(BB_BBTHEME_CONFIG) && file_exists(BB_BBTHEM_CONFIG_TEMPLATE)) {
            rename(BB_BBTHEM_CONFIG_TEMPLATE, BB_BBTHEME_CONFIG);
        }

        return true;
    }

    private function _sendMail($ns)
    {
        $admin_name = $ns->get('admin_name');
        $admin_email = $ns->get('admin_email');
        $admin_pass = $ns->get('admin_pass');

        $content = "Hi $admin_name, " . PHP_EOL;
        $content .= 'You have successfully setup FOSSBilling at ' . BB_URL . PHP_EOL;
        $content .= 'Access client area at: ' . BB_URL . PHP_EOL;
        $content .= 'Access admin area at: ' . BB_URL_ADMIN . ' with login details:' . PHP_EOL;
        $content .= 'E-mail: ' . $admin_email . PHP_EOL;
        $content .= 'Password: The password you choose during installation' . PHP_EOL . PHP_EOL;

        $content .= "Read FOSSBilling documentation to get started https://docs.fossbilling.org/" . PHP_EOL;
        $content .= "Thank You for using FOSSBilling." . PHP_EOL;

        $subject = sprintf('FOSSBilling is ready at "%s"', BB_URL);

        @mail($admin_email, $subject, $content);
    }

    private function _createConfigurationFile($data)
    {
        $output = $this->_getConfigOutput($data);
        if (!@file_put_contents(BB_PATH_CONFIG, $output)) {
            throw new Exception('Configuration file is not writable or does not exist. Please create the file at ' . BB_PATH_CONFIG . ' and make it writable', 101);
        }
    }

    private function _getConfigOutput($ns)
    {
        // TODO: Why not just take the defaults from the bb.config.example.php file and modify accordingly? Also this method doesn't preserve the comments in the example config.
        $data = [
            'debug' => false,

            'maintenance_mode' => [
                'enabled' => false,
                'allowed_urls' => [],
                'allowed_ips' => [],
            ],

            'salt' => md5(random_bytes(13)),
            'url' => BB_URL,
            'admin_area_prefix' => '/bb-admin',
            'sef_urls' => true,
            'timezone' => 'UTC',
            'locale' => 'en_US',
            'locale_date_format' => '%A, %d %B %G',
            'locale_time_format' => ' %T',
            'path_data' => BB_PATH_ROOT . '/bb-data',
            'path_logs' => BB_PATH_ROOT . '/bb-data/log/application.log',

            'log_to_db' => true,

            'db' => [
                'type' => 'mysql',
                'host' => $ns->get('db_host'),
                'name' => $ns->get('db_name'),
                'user' => $ns->get('db_user'),
                'password' => $ns->get('db_pass'),
            ],

            'twig' => [
                'debug' => true,
                'auto_reload' => true,
                'cache' => BB_PATH_ROOT . '/bb-data/cache',
            ],

            'api' => [
                'require_referrer_header' => false,
                'allowed_ips' => [],
                'rate_span' => 60 * 60,
                'rate_limit' => 1000,
                'throttle_delay' => 2,
                'rate_span_login' => 60,
                'rate_limit_login' => 20,
            ],
            'guzzle' => [
                'user_agent' => 'Mozilla/5.0 (RedHatEnterpriseLinux; Linux x86_64; FOSSBilling; +http://fossbilling.org) Gecko/20100101 Firefox/93.0',
                'timeout' => 0,
                'upgrade_insecure_requests' => 0,
            ],
        ];
        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($data, true) . ';';

        return $output;
    }

    private function _isValidInstallData($ns)
    {
        if (!$this->canConnectToDatabase($ns->get('db_host'), $ns->get('db_name'), $ns->get('db_user'), $ns->get('db_pass'))) {
            throw new Exception('Can not connect to database');
        }

        if (!$this->isValidAdmin($ns->get('admin_email'), $ns->get('admin_pass'), $ns->get('admin_name'))) {
            throw new Exception('Administrators account is not valid');
        }
    }

    private function generateEmailTemplates()
    {
        define('BB_PATH_MODS', BB_PATH_ROOT . '/bb-modules');

        $emailService = new \Box\Mod\Email\Service();
        $di = $di = include BB_PATH_ROOT . '/bb-di.php';
        $di['translate']();
        $emailService->setDi($di);

        return $emailService->templateBatchGenerate();
    }
}

$action = $_GET['a'] ?? 'index';
$installer = new Box_Installer();
$installer->run($action);
