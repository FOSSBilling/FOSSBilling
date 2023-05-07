<?php
/**
 * Set up an instance of FOSSBilling. Usually used for setting up a test environment.
 * 
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

// Path constants
const PATH_ROOT = __DIR__ . DIRECTORY_SEPARATOR . '../src';
const PATH_VENDOR = PATH_ROOT . DIRECTORY_SEPARATOR . 'vendor';
const PATH_LIBRARY = PATH_ROOT . DIRECTORY_SEPARATOR . 'library';
const PATH_THEMES = PATH_ROOT . DIRECTORY_SEPARATOR . 'themes';
const PATH_MODS = PATH_ROOT . DIRECTORY_SEPARATOR . 'modules';
const PATH_LANGS = PATH_ROOT . DIRECTORY_SEPARATOR . 'locale';
const PATH_UPLOADS = PATH_ROOT . DIRECTORY_SEPARATOR . 'uploads';
const PATH_DATA = PATH_ROOT . DIRECTORY_SEPARATOR . 'data';
const PATH_CONFIG = PATH_ROOT . DIRECTORY_SEPARATOR . 'config.php';
const PATH_INSTALL = PATH_ROOT . DIRECTORY_SEPARATOR . 'install';

require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';

// Set up the autoloader
$loader = new AntCMS\AntLoader(PATH_CACHE . DIRECTORY_SEPARATOR . 'classMap.php');
$loader->addPrefix('', PATH_LIBRARY, 'psr0');
$loader->addPrefix('Box\\Mod\\', PATH_MODS);
$loader->checkClassMap();
$loader->register();

use \FOSSBilling\Environment;
use \Symfony\Component\Filesystem\Filesystem;

// Load the environment variables
$env = new Environment();
$env->loadDotEnv();

// Make sure the install folder exists
$filesystem = new Filesystem();
if (!$filesystem->exists(PATH_INSTALL)) {
    throw new Exception('The \'install\' folder is missing. Please clone the repository again.');
}

// Determine which SQL dump to use
$sqlStructure = PATH_INSTALL . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'structure.sql';
if ($env->isTesting()) {
    $sqlContent = PATH_INSTALL . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'content_test.sql';
} else {
    $sqlContent = PATH_INSTALL . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'content.sql';
}

$type = $_ENV['DB_TYPE'] ?? 'mysql';
$host = $_ENV['DB_HOST'] ?? null;
$dbname = $_ENV['DB_NAME'] ?? null;
$user = $_ENV['DB_USER'] ?? null;
$password = $_ENV['DB_PASSWORD'] ?? null;
$port = $_ENV['DB_PORT'] ?? 3306;

if (!$host || !$dbname || !$user || !$password) {
    throw new Exception('Missing database credentials. Please set the DB_HOST, DB_NAME, DB_USER and DB_PASSWORD environment variables. You can also set the DB_PORT variable if you are not using the default port.');
}

echo sprintf("Setting up a new FOSSBilling instance for the %s environment", $env->getCurrentEnvironment()) . PHP_EOL;
echo sprintf("Attempting to connect to the database: %s@%s/%s", $user, $host, $dbname) . PHP_EOL;

$iter = 30;
$waitIntervalInSeconds = 2;
$connected = false;

while (!$connected && $iter > 0) {
    try {
        $db = new PDO($type . ':host=' . $host . ';port=' . $port, $user, $password, [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $version = $db->query('SELECT version()');
        echo sprintf("Connected to the database server, version: %s", $version->fetchColumn()) . PHP_EOL;

        $connected = true;
    } catch (Exception $e) {
        $message = $e->getMessage();

        if (str_contains($message, 'Connection refused')) {
            sleep($waitIntervalInSeconds); // The server might still be initializing. Wait a bit.
            $iter--;

            echo sprintf("Waiting for the database container to go up (Attempt: %s, Message: %s)", $iter, $message) . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

function execSQL(PDO $db, string $sql)
{
    $db->exec($sql);
    $error = $db->errorInfo();
    if ($error[2]) {
        var_dump($db->errorInfo());
        exit;
    }
}

echo sprintf("Dropping database: %s", $dbname) . PHP_EOL;
execSQL($db, sprintf("DROP DATABASE IF EXISTS %s;", $dbname));

echo sprintf("Creating database: %s", $dbname) . PHP_EOL;
execSQL($db, sprintf("CREATE DATABASE %s;", $dbname));

echo sprintf("Connecting to the %s database with the user: %s", $dbname, $user) . PHP_EOL;
$sql = sprintf("use %s;", $dbname);

echo sprintf("Setting up the database structure from the dump: %s", $sqlStructure) . PHP_EOL;
$sql = file_get_contents($sqlStructure);
execSQL($db, $sql);

echo sprintf("Importing the database content from the dump: %s", $sqlContent) . PHP_EOL;
$sql = file_get_contents($sqlContent);
$stmt = $db->prepare($sql);
$stmt->execute();

echo ("Creating the configuration file: config.php") . PHP_EOL;
$payload = [
    'db_host' => $host,
    'db_name' => $dbname,
    'db_user' => $user,
    'db_pass' => $password,
    'db_port' => $port,
];

require PATH_INSTALL . DIRECTORY_SEPARATOR . 'install.php';

$installer = new Box_Installer();
$installer->_createConfigurationFile($payload);

echo ("Successfully set up FOSSBilling.") . PHP_EOL;