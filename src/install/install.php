<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Email\Service;
use FOSSBilling\Environment;
use Symfony\Component\HttpClient\HttpClient;
use Twig\Loader\FilesystemLoader;
date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
ini_set('log_errors', '1');
ini_set('error_log', 'php_error.log');
define('PATH_ROOT', dirname(__DIR__));
const PATH_LIBRARY = PATH_ROOT . DIRECTORY_SEPARATOR . 'library';
const PATH_VENDOR = PATH_ROOT . DIRECTORY_SEPARATOR . 'vendor';
const PATH_INSTALL_THEMES = PATH_ROOT . DIRECTORY_SEPARATOR . 'install';
const PATH_THEMES = PATH_ROOT . DIRECTORY_SEPARATOR . 'themes';
const PATH_LICENSE = PATH_ROOT . DIRECTORY_SEPARATOR . 'LICENSE';
const PATH_SQL = PATH_ROOT . DIRECTORY_SEPARATOR . 'install/sql/structure.sql';
const PATH_SQL_DATA = PATH_ROOT . DIRECTORY_SEPARATOR . 'install/sql/content.sql';
const PATH_INSTALL = PATH_ROOT . DIRECTORY_SEPARATOR . 'install';
const PATH_CONFIG = PATH_ROOT . DIRECTORY_SEPARATOR . 'config.php';
const PATH_CONFIG_SAMPLE = PATH_ROOT . DIRECTORY_SEPARATOR . 'config-sample.php';
const PATH_CRON = PATH_ROOT . DIRECTORY_SEPARATOR . 'cron.php';
const PATH_LANGS = PATH_ROOT . DIRECTORY_SEPARATOR . 'locale';
const PATH_MODS = PATH_ROOT . DIRECTORY_SEPARATOR . 'modules';
const PATH_CACHE = PATH_ROOT . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache';
// Config paths and templates
const BB_HURAGA_CONFIG = PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings_data.json';
const BB_HURAGA_CONFIG_TEMPLATE = PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings_data.json.example';
// .htaccess Path
const PATH_HTACCESS = PATH_ROOT . DIRECTORY_SEPARATOR . '.htaccess';

// Set default include path
set_include_path(implode(PATH_SEPARATOR, [
    PATH_LIBRARY,
    get_include_path(),
]));

// Load autoloaders
require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';
include PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'Autoloader.php';

// Build the environment
$loader = new FOSSBilling\AutoLoader();
$loader->register();
$protocol = FOSSBilling\Tools::isHTTPS() ? 'https' : 'http';
$url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$current_url = pathinfo($url, PATHINFO_DIRNAME);
$root_url = str_replace('/install', '', $current_url) . '/';
define('BB_URL', $root_url);
const BB_URL_INSTALL = BB_URL . 'install/';
const BB_URL_ADMIN = BB_URL . 'index.php?_url=/admin';

// Inline installer class.
final class Box_Installer
{
    private Session $session;

    public function __construct()
    {
        include 'session.php';
        $this->session = new Session();
    }

    public function run($action): void
    {
        switch ($action) {
            case 'install':
                try {
                    // Verify database connection
                    if (! $this->canConnectToDatabase($_POST['databaseHostname'] . ';' . $_POST['databasePort'], $_POST['databaseName'], $_POST['databaseUsername'], $_POST['databasePassword'])) {
                        $this->renderResultPage(false, 'Could not connect to the database, or the database does not exist');
                        break;
                    }
                    $this->session->set('databaseHostname', $_POST['databaseHostname']);
                    $this->session->set('databasePort', $_POST['databasePort']);
                    $this->session->set('databaseName', $_POST['databaseName']);
                    $this->session->set('databaseUsername', $_POST['databaseUsername']);
                    $this->session->set('databasePassword', $_POST['databasePassword']);

                    // Validate admin credentials
                    if (! $this->isValidAdmin($_POST['adminEmail'], $_POST['adminPassword'], $_POST['adminName'])) {
                        $this->renderResultPage(false, 'Administrator\'s account is invalid');
                        break;
                    }
                    $this->session->set('adminName', $_POST['adminName']);
                    $this->session->set('adminEmail', $_POST['adminEmail']);
                    $this->session->set('adminPassword', $_POST['adminPassword']);

                    // Setup default currency
                    $this->session->set('currencyCode', $_POST['currencyCode']);
                    $this->session->set('currencyTitle', $_POST['currencyTitle']);
                    $this->session->set('currencyFormat', $_POST['currencyFormat']);

                    // Attempt installation
                    $this->makeInstall($this->session);
                    $this->generateEmailTemplates();
                    session_destroy();
                    // Try to remove install folder
                    try {
                        // Delete install directory only if debug mode is NOT enabled.
                        $config = require PATH_CONFIG;
                        if (!$config['debug']) {
                            $this->rmAllDir('..' . DIRECTORY_SEPARATOR . 'install');
                        }
                    } catch (Exception) {
                        // do nothing
                    }

                    // Installation is successful
                    $this->renderResultPage(true, 'Installation completed successfully!');
                } catch (Exception $e) {
                    // Route to result page with exception information
                    $this->renderResultPage(false, $e->getMessage());
                }
                break;
            case 'index':
            default:
                $se = new \FOSSBilling\Requirements();
                $options = $se->getOptions();
                $vars = [
                    'folders' => $se->folders(),
                    'files' => $se->files(),
                    'os' => PHP_OS,
                    'os_ok' => true,
                    'fossbilling_ver' => \FOSSBilling\Version::VERSION,
                    'fossbilling_ver_ok' => $se->isFOSSBillingVersionOk(),
                    'php_ver' => $options['php']['version'],
                    'php_ver_req' => $options['php']['min_version'],
                    'php_safe_mode' => $options['php']['safe_mode'],
                    'php_ver_ok' => $se->isPhpVersionOk(),
                    'extensions' => $se->extensions(),
                    'canInstall' => $se->canInstall(),
                    'databaseHostname' => $this->session->get('databaseHostname'),
                    'databaseName' => $this->session->get('databaseName'),
                    'databaseUsername' => $this->session->get('databaseUsername'),
                    'databasePassword' => $this->session->get('databasePassword'),
                    'adminName' => $this->session->get('adminName'),
                    'adminEmail' => $this->session->get('adminEmail'),
                    'adminPassword' => $this->session->get('adminPassword'),
                    'currencyCode' => $this->session->get('currencyCode'),
                    'currencyTitle' => $this->session->get('currencyTitle'),
                    'currencyFormat' => $this->session->get('currencyFormat'),
                    'install_module_path' => PATH_INSTALL,
                    'cron_path' => PATH_CRON,
                    'config_file_path' => PATH_CONFIG,
                    'live_site' => BB_URL,
                    'admin_site' => BB_URL_ADMIN,
                    'domain' => pathinfo(BB_URL, PATHINFO_BASENAME),
                ];
                echo $this->render('./assets/install.html.twig', $vars);
                break;
        }
    }

    private function renderResultPage(bool $success, string $message)
    {
        $vars = [
            'success' => $success,
            'message' => $message
        ];
        echo $this->render('./assets/installresult.html.twig', $vars);
    }

    private function render($name, $vars = []): string
    {
        $options = [
            'paths' => [PATH_INSTALL_THEMES],
            'debug' => true,
            'charset' => 'utf-8',
            'optimizations' => 1,
            'autoescape' => 'html',
            'auto_reload' => true,
            'cache' => false,
        ];
        $loader = new FilesystemLoader($options['paths']);
        $twig = new Twig\Environment($loader, $options);
        // $twig->addExtension(new Twig_Extension_Optimizer());
        $twig->addGlobal('request', $_REQUEST);
        $twig->addGlobal('version', \FOSSBilling\Version::VERSION);

        return $twig->render($name, $vars);
    }

    private function getPdo($host, $db, $user, $pass): PDO
    {
        $pdo = new PDO('mysql:host=' . $host,
            $user,
            $pass,
            [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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

    private function canConnectToDatabase($host, $db, $user, $pass): bool
    {
        try {
            $this->getPdo($host, $db, $user, $pass);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return false;
        }

        return true;
    }

    private function isValidAdmin($email, $pass, $name): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (strlen($pass) < 8) {
            throw new Exception('Minimum admin password length is 8 characters.');
        }

        if (!preg_match("#[0-9]+#", $pass)) {
            throw new Exception('Admin password must include at least one number.');
        }

        if (!preg_match("#[a-z]+#", $pass)) {
            throw new Exception('Admin password must include at least one lowercase letter.');
        }

        if (!preg_match("#[A-Z]+#", $pass)) {
            throw new Exception('Admin password must include at least one uppercase letter.');
        }

        if (empty($name)) {
            return false;
        }

        return true;
    }

    private function makeInstall($ns): bool
    {
        $this->_isValidInstallData($ns);
        $this->_createConfigurationFile($ns);

        $pdo = $this->getPdo($ns->get('databaseHostname') . ';' . $ns->get('databasePort'), $ns->get('databaseName'), $ns->get('databaseUsername'), $ns->get('databasePassword'));

        $sql = file_get_contents(PATH_SQL);
        $sql_content = file_get_contents(PATH_SQL_DATA);

        if (!$sql || !$sql_content) {
            throw new Exception('Could not read structure.sql file');
        }

        $sql .= $sql_content;

        $sql = preg_split('/\;[\r]*\n/ism', $sql);
        $sql = array_map('trim', $sql);
        foreach ($sql as $query) {
            if (!trim($query)) {
                continue;
            }

            $pdo->query($query);
        }

        $passwordObject = new Box_Password();
        $stmt = $pdo->prepare("INSERT INTO admin (role, name, email, pass, protected, created_at, updated_at) VALUES('admin', :adminName, :adminEmail, :adminPassword, 1, NOW(), NOW());");
        $stmt->execute([
            'adminName' => $ns->get('adminName'),
            'adminEmail' => $ns->get('adminEmail'),
            'adminPassword' => $passwordObject->hashIt($ns->get('adminPassword')),
        ]);

        $stmt = $pdo->prepare("DELETE FROM currency WHERE code='USD'");
        $stmt->execute();

        $stmt = $pdo->prepare("INSERT INTO currency (id, title, code, is_default, conversion_rate, format, price_format, created_at, updated_at) VALUES(1, :currencyTitle, :currencyCode, 1, 1.000000, :currencyFormat, 1,  NOW(), NOW());");
        $stmt->execute([
            'currencyTitle' => $ns->get('currencyTitle'),
            'currencyCode' => $ns->get('currencyCode'),
            'currencyFormat' => $ns->get('currencyFormat'),
        ]);

        /*
          Copy config templates when applicable
        */
        if (!file_exists(BB_HURAGA_CONFIG) && file_exists(BB_HURAGA_CONFIG_TEMPLATE)) {
            copy(BB_HURAGA_CONFIG_TEMPLATE, BB_HURAGA_CONFIG); // Copy the file instead of renaming it. This allows local dev instances to not need to restore the original file manually.
        }

        /*
          If .htaccess doesn't exist, grab it from Github.
        */
        if (!file_exists(PATH_HTACCESS)) {
            try {
                $client = HttpClient::create();
                $response = $client->request('GET', 'https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/main/src/.htaccess');
                file_put_contents(PATH_HTACCESS, $response->getContent());
            } catch (Exception $e) {
                throw new Exception("Unable to write required .htaccess file to " . PATH_HTACCESS . ". Check file and folder permissions.", $e->getCode());
            }
        }

        return true;
    }

    private function _createConfigurationFile($data): void
    {
        $output = $this->_getConfigOutput($data);
        if (!file_put_contents(PATH_CONFIG, $output)) {
            throw new Exception('Configuration file is not writable or does not exist. Please create the file at ' . PATH_CONFIG . ' and make it writable', 101);
        }
    }

    /**
     * Generate the `config.php` file using the `config-sample.php` as a template.
     *
     * @param object $ns
     * @return string
     */
    private function _getConfigOutput($ns): string
    {
        // Version data
        $version = new \FOSSBilling\Requirements();
        $reg = '^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$^';
        $updateBranch = (preg_match($reg, \FOSSBilling\Version::VERSION, $matches) !== 0) ? "release" : "preview";

        // Load default sample config
        $data = require PATH_CONFIG_SAMPLE;

        // Handle dynamic configs
        $data['security']['force_https'] = FOSSBilling\Tools::isHTTPS() ? true : false;
        $data['update_branch'] = $updateBranch;
        $data['salt'] = md5(random_bytes(13));
        $data['url'] = BB_URL;
        $data['path_data'] = PATH_ROOT . '/data';
        $data['path_logs'] = PATH_ROOT . '/data/log/application.log';
        $data['db'] = [
            'type' => 'mysql',
            'host' => $ns->get('databaseHostname'),
            'port' => $ns->get('databasePort'),
            'name' => $ns->get('databaseName'),
            'user' => $ns->get('databaseUsername'),
            'password' => $ns->get('databasePassword'),
        ];
        $data['twig']['cache'] = PATH_ROOT . '/data/cache';

        // Build and return data
        $output = '<?php ' . PHP_EOL;
        $output .= 'return ' . var_export($data, true) . ';';
        return $output;
    }

    private function _isValidInstallData($ns): void
    {
        if (!$this->canConnectToDatabase($ns->get('databaseHostname'), $ns->get('databaseName'), $ns->get('databaseUsername'), $ns->get('databasePassword'))) {
            throw new Exception('Can not connect to database');
        }

        if (!$this->isValidAdmin($ns->get('adminEmail'), $ns->get('adminPassword'), $ns->get('adminName'))) {
            throw new Exception('Administrator\'s account is invalid');
        }
    }

    private function generateEmailTemplates(): bool
    {
        $emailService = new Service();
        $di = include PATH_ROOT . '/di.php';
        $di['translate']();
        $emailService->setDi($di);

        return $emailService->templateBatchGenerate();
    }

    public function rmAllDir($dir)
    {
        if (is_dir($dir)) {
            $contents = scandir($dir);
            foreach ($contents as $content) {
                if ('.' !== $content && '..' !== $content) {
                    if ('dir' === filetype($dir . DIRECTORY_SEPARATOR . $content)) {
                        $this->rmAllDir($dir . DIRECTORY_SEPARATOR . $content);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $content);
                    }
                }
            }
            reset($contents);
            rmdir($dir);
        }
    }
}

// Load action and initalize the installer
$action = $_GET['a'] ?? 'index';
$installer = new Box_Installer();

// Run the installer only in non-CLI mode
if (! Environment::isCLI()) {
    $installer->run($action);
}