<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Email\Service;
use FOSSBilling\Environment;
use Ramsey\Uuid\Uuid;
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
const PATH_LOG = PATH_ROOT . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'log';
const HURAGA_CONFIG = PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings_data.json';
const HURAGA_CONFIG_TEMPLATE = PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings_data.json.example';
const PATH_HTACCESS = PATH_ROOT . DIRECTORY_SEPARATOR . '.htaccess';
const PAGE_INSTALL = './assets/install.html.twig';
const PAGE_RESULT = './assets/result.html.twig';

// Some functions and classes reference this, so we define it here to avoid errors.
const DEBUG = false;

// Set default include path
set_include_path(implode(PATH_SEPARATOR, [
    PATH_LIBRARY,
    get_include_path(),
]));

// Load autoloader
require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';
include PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'Autoloader.php';

// Build the environment
$loader = new FOSSBilling\AutoLoader();
$loader->register();
$protocol = FOSSBilling\Tools::isHTTPS() ? 'https' : 'http';

// Detect if FOSSBilling is behind a proxy server
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
} else {
    $host = $_SERVER['HTTP_HOST'];
}

$url = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
$current_url = pathinfo($url, PATHINFO_DIRNAME);
$root_url = str_replace('/install', '', $current_url) . '/';
define('SYSTEM_URL', $root_url);
const URL_INSTALL = SYSTEM_URL . 'install/';
const URL_ADMIN = SYSTEM_URL . 'admin';

// Load action and initialize the installer
$action = $_GET['a'] ?? 'index';
$installer = new FOSSBilling_Installer();

// Run the installer only in non-CLI mode
if (!Environment::isCLI()) {
    $installer->run($action);
}

// Inline installer class.
final class FOSSBilling_Installer
{
    private readonly Session $session;
    private PDO $pdo;

    public function __construct()
    {
        require_once 'session.php';
        $this->session = new Session();
    }

    /**
     * Action router.
     *
     * @param string $action
     */
    public function run($action): void
    {
        switch ($action) {
            case 'install':
                // Make sure this is a POST request
                if ($_SERVER['REQUEST_METHOD'] != 'POST') {
                    header('Location: ' . URL_INSTALL);
                    exit;
                }

                // Installer validation
                try {
                    // Make sure we are not already installed. Prevents tampered requests from being able to trigger the installer.
                    if ($this->isAlreadyInstalled()) {
                        throw new Exception('FOSSBilling is already installed.');
                    }

                    // Set if they've opted into error reporting
                    $this->session->set('error_reporting', $_POST['error_reporting']);

                    // Handle database information
                    $this->session->set('database_hostname', $_POST['database_hostname']);
                    $this->session->set('database_port', $_POST['database_port']);
                    $this->session->set('database_name', $_POST['database_name']);
                    $this->session->set('database_username', $_POST['database_username']);
                    $this->session->set('database_password', $_POST['database_password']);
                    $this->connectDatabase();

                    // Handle admin information
                    $this->session->set('admin_name', $_POST['admin_name']);
                    $this->session->set('admin_email', $_POST['admin_email']);
                    $this->session->set('admin_password', $_POST['admin_password']);

                    if (Environment::isTesting()) {
                        $this->session->set('admin_api_token', $_POST['admin_api_token'] ?? null);
                    } else {
                        $this->session->set('admin_api_token', null);
                    }

                    $this->validateAdmin();

                    // Set up default currency
                    $this->session->set('currency_code', $_POST['currency_code']);
                    $this->session->set('currency_title', $_POST['currency_title']);
                    $this->session->set('currency_format', $_POST['currency_format'] ?? '${{price}}');

                    // Attempt installation
                    $this->install();
                    $this->generateEmailTemplates();
                    session_destroy();

                    // Installation is successful
                    echo $this->render(PAGE_RESULT, [
                        'success' => true,
                        'config_file_path' => PATH_CONFIG,
                        'cron_path' => PATH_CRON,
                        'install_module_path' => PATH_INSTALL,
                        'url_customer' => SYSTEM_URL,
                        'url_admin' => URL_ADMIN,
                    ]);

                    // Try to remove install folder
                    try {
                        // Delete install directory only if debug mode is NOT enabled.
                        $config = require PATH_CONFIG;
                        if (!$config['debug_and_monitoring']['debug']) {
                            unlink(__DIR__ . DIRECTORY_SEPARATOR . 'install.php');
                        }
                    } catch (Exception) {
                        // Do nothing and fail silently. New warnings are presented on the installation completed page for a leftover install directory.
                    }
                } catch (Exception $e) {
                    // Route to result page with exception information
                    echo $this->render(PAGE_RESULT, [
                        'success' => false,
                        'message' => $e->getMessage(),
                    ]);
                }

                break;
            case 'index':
            default:
                $requirements = new FOSSBilling\Requirements();
                $compatibility = $requirements->checkCompat();
                $vars = [
                    'compatibility' => $compatibility,
                    'os' => PHP_OS,
                    'os_ok' => (str_starts_with(strtoupper(PHP_OS), 'WIN')) ? false : true,
                    'is_subfolder' => $this->isSubfolder(),
                    'fossbilling_ver' => FOSSBilling\Version::VERSION,
                    'canInstall' => !$this->isSubfolder() && $compatibility['can_install'],
                    'alreadyInstalled' => $this->isAlreadyInstalled(),
                    'database_hostname' => $this->session->get('database_hostname'),
                    'database_name' => $this->session->get('database_name'),
                    'database_username' => $this->session->get('database_username'),
                    'database_password' => $this->session->get('database_password'),
                    'admin_name' => $this->session->get('admin_name'),
                    'admin_email' => $this->session->get('admin_email'),
                    'admin_password' => $this->session->get('admin_password'),
                    'currency_code' => $this->session->get('currency_code') ?: 'USD',
                    'currency_title' => $this->session->get('currency_title') ?: 'US Dollar',
                    'currency_format' => $this->session->get('currency_format') ?: '${{price}}',
                    'install_module_path' => PATH_INSTALL,
                    'cron_path' => PATH_CRON,
                    'config_file_path' => PATH_CONFIG,
                    'live_site' => SYSTEM_URL,
                    'admin_site' => URL_ADMIN,
                    'domain' => pathinfo(SYSTEM_URL, PATHINFO_BASENAME),
                ];
                echo $this->render(PAGE_INSTALL, $vars);

                break;
        }
    }

    /**
     * Render a page with Twig.
     *
     * @param string $name
     * @param array  $vars
     */
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
        $twig->addGlobal('request', $_REQUEST);
        $twig->addGlobal('version', FOSSBilling\Version::VERSION);

        return $twig->render($name, $vars);
    }

    /**
     * Attempt to open the database connection.
     */
    private function connectDatabase(): void
    {
        // Open the connection
        $this->pdo = new PDO('mysql:host=' . $this->session->get('database_hostname') . ';' . $this->session->get('database_port'),
            $this->session->get('database_username'),
            $this->session->get('database_password'),
            [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Set required MySQL environment settings
        $this->pdo->exec('SET NAMES "utf8"');
        $this->pdo->exec('SET CHARACTER SET utf8');
        $this->pdo->exec('SET CHARACTER_SET_CONNECTION = utf8');
        $this->pdo->exec('SET character_set_results = utf8');
        $this->pdo->exec('SET character_set_server = utf8');
        $this->pdo->exec('SET SESSION interactive_timeout = 28800');
        $this->pdo->exec('SET SESSION wait_timeout = 28800');

        // Attempt to create the database.
        try {
            $this->pdo->exec('CREATE DATABASE `' . $this->session->get('database_name') . '` CHARACTER SET utf8 COLLATE utf8_general_ci;');
        } catch (PDOException) {
            // Silently fail if the database already exists.
        }

        // Select the database as default for future queries
        $this->pdo->query('USE `' . $this->session->get('database_name') . '`;');
    }

    /**
     * Validate admin information meets all required parameters.
     */
    private function validateAdmin(): bool
    {
        if (!filter_var($this->session->get('admin_email'), FILTER_VALIDATE_EMAIL)) {
            throw new Exception('The admin email is not a valid address.');
        }

        if (strlen($this->session->get('admin_password')) < 8) {
            throw new Exception('Minimum admin password length is 8 characters.');
        }

        if (!preg_match('#[0-9]+#', $this->session->get('admin_password'))) {
            throw new Exception('Admin password must include at least one number.');
        }

        if (!preg_match('#[a-z]+#', $this->session->get('admin_password'))) {
            throw new Exception('Admin password must include at least one lowercase letter.');
        }

        if (!preg_match('#[A-Z]+#', $this->session->get('admin_password'))) {
            throw new Exception('Admin password must include at least one uppercase letter.');
        }

        if (empty($this->session->get('admin_name'))) {
            throw new Exception('You must enter an Admin Name.');
        }

        return true;
    }

    /**
     * Attempt to detect if the application is under a subfolder.
     */
    private function isSubfolder(): bool
    {
        return substr_count(URL_INSTALL, '/') > 4 ? true : false;
    }

    /**
     * Check if we are already installed.
     */
    public function isAlreadyInstalled(): bool
    {
        return file_exists(PATH_CONFIG) ? true : false;
    }

    /**
     * Installation processor.
     */
    private function install(): bool
    {
        // Load database structure
        $sql = file_get_contents(PATH_SQL);
        $sql_content = file_get_contents(PATH_SQL_DATA);
        if (!$sql || !$sql_content) {
            throw new Exception('Could not read structure.sql file');
        }

        // Read content, parse queries into an array, then loop and execute each query
        $sql .= $sql_content;
        $sql = preg_split('/\;[\r]*\n/ism', $sql);
        $sql = array_map('trim', $sql);
        foreach ($sql as $query) {
            if (!trim($query)) {
                continue;
            }
            $this->pdo->query($query);
        }

        // Create default administrator
        $passwordObject = new FOSSBilling\PasswordManager();
        $stmt = $this->pdo->prepare("INSERT INTO admin (role, name, email, pass, protected, created_at, updated_at, api_token) VALUES('admin', :admin_name, :admin_email, :admin_password, 1, NOW(), NOW(), :api_token);");
        $stmt->execute([
            'admin_name' => $this->session->get('admin_name'),
            'admin_email' => $this->session->get('admin_email'),
            'admin_password' => $passwordObject->hashIt($this->session->get('admin_password')),
            'api_token' => $this->session->get('admin_api_token'),
        ]);

        // Delete default currency from content file and use currency passed in the installer
        $stmt = $this->pdo->prepare("DELETE FROM currency WHERE code='USD'");
        $stmt->execute();
        $stmt = $this->pdo->prepare('INSERT INTO currency (id, title, code, is_default, conversion_rate, format, price_format, created_at, updated_at) VALUES(1, :currency_title, :currency_code, 1, 1.000000, :currency_format, 1,  NOW(), NOW());');
        $stmt->execute([
            'currency_title' => $this->session->get('currency_title'),
            'currency_code' => $this->session->get('currency_code'),
            'currency_format' => $this->session->get('currency_format'),
        ]);

        $stmt = $this->pdo->prepare('INSERT INTO setting (param, value, created_at, updated_at) VALUES (:param, :value, NOW(), NOW())');
        $stmt->execute([
            ':param' => 'last_error_reporting_nudge',
            ':value' => FOSSBilling\Version::VERSION,
        ]);

        // Copy config templates when applicable
        if (!file_exists(HURAGA_CONFIG) && file_exists(HURAGA_CONFIG_TEMPLATE)) {
            copy(HURAGA_CONFIG_TEMPLATE, HURAGA_CONFIG); // Copy the file instead of renaming it. This allows local dev instances to not need to restore the original file manually.
        }

        // If .htaccess doesn't exist, fetch the latest from GitHub.
        if (!file_exists(PATH_HTACCESS)) {
            try {
                $client = HttpClient::create();
                $response = $client->request('GET', 'https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/main/src/.htaccess');
                file_put_contents(PATH_HTACCESS, $response->getContent());
            } catch (Exception $e) {
                throw new Exception('Unable to write required .htaccess file to ' . PATH_HTACCESS . '. Check file and folder permissions.', $e->getCode());
            }
        }

        // Create the configuration file
        $output = $this->getConfigOutput();
        if (!file_put_contents(PATH_CONFIG, $output)) {
            throw new Exception('Configuration file is not writable or does not exist. Please create the file at ' . PATH_CONFIG . ' and make it writable', 101);
        }

        // Installation completed successfully
        return true;
    }

    /**
     * Generate the `config.php` file using the `config-sample.php` as a template.
     */
    private function getConfigOutput(): string
    {
        $updateBranch = FOSSBilling\Version::isPreviewVersion() ? 'preview' : 'release';

        // Load default sample config
        $data = require PATH_CONFIG_SAMPLE;

        // Handle dynamic configs
        $data['security']['force_https'] = FOSSBilling\Tools::isHTTPS();
        $data['debug_and_monitoring']['report_errors'] = (bool) $this->session->get('error_reporting');
        $data['update_branch'] = $updateBranch;
        $data['info']['instance_id'] = Uuid::uuid4()->toString();
        $data['url'] = SYSTEM_URL;
        $data['path_data'] = PATH_ROOT . DIRECTORY_SEPARATOR . 'data';
        $data['db'] = [
            'type' => 'mysql',
            'host' => $this->session->get('database_hostname'),
            'port' => $this->session->get('database_port'),
            'name' => $this->session->get('database_name'),
            'user' => $this->session->get('database_username'),
            'password' => $this->session->get('database_password'),
        ];
        $data['twig']['cache'] = PATH_CACHE;

        // Build and return data
        $output = '<?php ' . PHP_EOL;

        return $output . ('return ' . var_export($data, true) . ';');
    }

    /**
     * Generate the default email templates.
     */
    private function generateEmailTemplates(): bool
    {
        $emailService = new Service();
        $di = include PATH_ROOT . DIRECTORY_SEPARATOR . 'di.php';
        $di['translate']();
        $emailService->setDi($di);

        return $emailService->templateBatchGenerate();
    }
}
