<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Email\Service;
use FOSSBilling\Environment;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;
use Twig\Loader\FilesystemLoader;

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
ini_set('log_errors', '1');
ini_set('error_log', 'php_error.log');

// Define required paths.
define('PATH_ROOT', dirname(__DIR__));
define('PATH_LIBRARY', PATH_ROOT . DIRECTORY_SEPARATOR . 'library');
define('PATH_VENDOR', PATH_ROOT . DIRECTORY_SEPARATOR . 'vendor');

// Set the default include path to include the library directory.
set_include_path(get_include_path() . PATH_SEPARATOR . PATH_LIBRARY);

// Check vendor folder exists and load Composer autoloader.
if (!file_exists(PATH_VENDOR)) {
    throw new Exception('The composer packages are missing.', 1);
}
require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';

// Define global paths.
define('PATH_INSTALL_THEMES', Path::join(PATH_ROOT, 'install'));
define('PATH_THEMES', Path::join(PATH_ROOT, 'themes'));
define('PATH_LICENSE', Path::join(PATH_ROOT, 'LICENSE'));
define('PATH_SQL', Path::join(PATH_ROOT, 'install', 'sql', 'structure.sql'));
define('PATH_SQL_DATA', Path::join(PATH_ROOT, 'install', 'sql', 'content.sql'));
define('PATH_INSTALL', Path::join(PATH_ROOT, 'install'));
define('PATH_CONFIG', Path::join(PATH_ROOT, 'config.php'));
define('PATH_CONFIG_SAMPLE', Path::join(PATH_ROOT, 'config-sample.php'));
define('PATH_CRON', Path::join(PATH_ROOT, 'cron.php'));
define('PATH_LANGS', Path::join(PATH_ROOT, 'locale'));
define('PATH_MODS', Path::join(PATH_ROOT, 'modules'));
define('PATH_CACHE', Path::join(PATH_ROOT, 'data', 'cache'));
define('PATH_LOG', Path::join(PATH_ROOT, 'data', 'log'));
define('HURAGA_CONFIG', Path::join(PATH_THEMES, 'huraga', 'config', 'settings_data.json'));
define('HURAGA_CONFIG_TEMPLATE', Path::join(PATH_THEMES, 'huraga', 'config', 'settings_data.json.example'));
define('PATH_HTACCESS', Path::join(PATH_ROOT, '.htaccess'));
define('PAGE_INSTALL', Path::join('./assets', 'install.html.twig'));
define('PAGE_RESULT', Path::join('./assets', 'result.html.twig'));

// Some functions and classes reference this, so we define it here to avoid errors.
const DEBUG = false;

// Set default include path
set_include_path(implode(PATH_SEPARATOR, [
    PATH_LIBRARY,
    get_include_path(),
]));

// Set up custom autoloader.
require Path::join(PATH_LIBRARY, 'FOSSBilling', 'Autoloader.php');
$loader = new FOSSBilling\AutoLoader();
$loader->register();

// Check whether using HTTPS or HTTP.
$protocol = FOSSBilling\Tools::isHTTPS() ? 'https' : 'http';

// Detect if FOSSBilling is behind a proxy server.
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
} else {
    $host = $_SERVER['HTTP_HOST'];
}

$url = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
$current_url = Path::getDirectory($url);
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
    private bool $isDebug = false;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        require_once 'session.php';
        $this->session = new Session();
        $this->filesystem = new Filesystem();

        if (!$this->isDebug && $this->filesystem->exists(PATH_CONFIG)) {
            $config = require PATH_CONFIG;
            $this->isDebug = $config['debug_and_monitoring']['debug'] || !Environment::isProduction();
        }

        if (getenv('IS_DDEV') === 'true' && ($_GET['a'] ?? 'index') === 'index') {
            $this->session->set('database_hostname', 'db');
            $this->session->set('database_name', 'db');
            $this->session->set('database_username', 'db');
            $this->session->set('database_password', 'db');
        }
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
                    if (!$this->isDebug && $this->isAlreadyInstalled()) {
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
                        if (!$this->isDebug) {
                            $this->filesystem->remove(Path::normalize(__DIR__ . '/install.php'));
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
                    'domain' => SYSTEM_URL,
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
        return !$this->isDebug && $this->filesystem->exists(PATH_CONFIG) ? true : false;
    }

    /**
     * Installation processor.
     */
    private function install(): bool
    {
        // Load database structure
        $sql = $this->filesystem->readFile(PATH_SQL);
        $sql_content = $this->filesystem->readFile(PATH_SQL_DATA);
        if (!$sql || !$sql_content) {
            throw new Exception('Could not read structure.sql file');
        }

        // Read content, parse queries into an array, then loop and execute each query
        $sql .= $sql_content;
        $sql = preg_split('/\;[\r]*\n/ism', $sql);
        $sql = array_map(trim(...), $sql);
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
        if (!$this->filesystem->exists(HURAGA_CONFIG) && $this->filesystem->exists(HURAGA_CONFIG_TEMPLATE)) {
            $this->filesystem->copy(HURAGA_CONFIG_TEMPLATE, HURAGA_CONFIG); // Copy the file instead of renaming it. This allows local dev instances to not need to restore the original file manually.
        }

        // If .htaccess doesn't exist, fetch the latest from GitHub.
        if (!$this->filesystem->exists(PATH_HTACCESS)) {
            try {
                $client = HttpClient::create();
                $response = $client->request('GET', 'https://raw.githubusercontent.com/FOSSBilling/FOSSBilling/main/src/.htaccess');
                $this->filesystem->dumpFile(PATH_HTACCESS, $response->getContent());
            } catch (Exception $e) {
                throw new Exception('Unable to write required .htaccess file to ' . PATH_HTACCESS . '. Check file and folder permissions.', $e->getCode());
            }
        }

        // Create the configuration file
        $output = $this->getConfigOutput();

        try {
            $this->filesystem->dumpFile(PATH_CONFIG, $output);
        } catch (IOException) {
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
        $data['debug_and_monitoring']['debug'] = $this->isDebug;
        $data['update_branch'] = $updateBranch;
        $data['info']['instance_id'] = Uuid::uuid4()->toString();
        $data['url'] = str_replace(['https://', 'http://'], '', SYSTEM_URL);
        $data['path_data'] = Path::join(PATH_ROOT, 'data');
        $data['db'] = [
            'type' => 'mysql',
            'host' => $this->session->get('database_hostname'),
            'port' => $this->session->get('database_port'),
            'name' => $this->session->get('database_name'),
            'user' => $this->session->get('database_username'),
            'password' => $this->session->get('database_password'),
        ];
        $data['twig']['cache'] = PATH_CACHE;
        $data['disable_auto_cron'] = !FOSSBilling\Version::isPreviewVersion() && !Environment::isDevelopment();

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
        $di = include Path::join(PATH_ROOT, 'di.php');
        $di['translate']();
        $emailService->setDi($di);

        return $emailService->templateBatchGenerate();
    }
}
