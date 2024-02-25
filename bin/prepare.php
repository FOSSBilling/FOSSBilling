<?php
/**
 * Set up an instance of FOSSBilling. Usually used for setting up a test environment.
 * 
 * Copyright 2022-2024 FOSSBilling
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
const PATH_CONFIG = PATH_ROOT . DIRECTORY_SEPARATOR . 'config.php';
const PATH_CONFIG_SAMPLE = PATH_ROOT . DIRECTORY_SEPARATOR . 'config-sample.php';
const PATH_INSTALL = PATH_ROOT . DIRECTORY_SEPARATOR . 'install';
const PATH_CACHE = PATH_ROOT . DIRECTORY_SEPARATOR . 'cache';

const HURAGA_CONFIG = PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings_data.json';
const HURAGA_CONFIG_TEMPLATE = PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'settings_data.json.example';

require PATH_VENDOR . DIRECTORY_SEPARATOR . 'autoload.php';

// Set up the autoloader
include PATH_LIBRARY . DIRECTORY_SEPARATOR . 'FOSSBilling' . DIRECTORY_SEPARATOR . 'Autoloader.php';
$loader = new FOSSBilling\AutoLoader();
$loader->register();

use FOSSBilling\Environment;
use \Symfony\Component\Filesystem\Filesystem;

// Make sure the install folder exists
$filesystem = new Filesystem();
if (!$filesystem->exists(PATH_INSTALL)) {
    throw new Exception('The \'install\' folder is missing. Please clone the repository again.');
}

if (!$filesystem->exists(PATH_CONFIG_SAMPLE)) {
    throw new Exception('The \'config-sample.php\' file is missing. Please clone the repository again.');
}

// Determine which SQL dump to use
$sqlBase = PATH_INSTALL . DIRECTORY_SEPARATOR . 'sql';
$sqlStructure = $sqlBase . DIRECTORY_SEPARATOR . 'structure.sql';
$sqlContent = $sqlBase . DIRECTORY_SEPARATOR . 'content' . (Environment::isTesting() ? '_test' : '') . '.sql';

$db = [
    'type' => 'mysql',
    'host' => getenv('DB_HOST') ?? null,
    'dbname' => getenv('DB_NAME') ?? null,
    'user' => getenv('DB_USER') ?? null,
    'password' => getenv('DB_PASS') ?? null,
    'port' => getenv('DB_PORT') ?? 3306,
];

if (in_array(null, $databaseConfig, true)) {
    throw new Exception('Missing database credentials. Please set the DB_HOST, DB_NAME, DB_USER and DB_PASS environment variables. You can also set the DB_PORT variable if you are not using the default port.');
}

echo sprintf("Setting up a new FOSSBilling instance for the %s environment", Environment::getCurrentEnvironment()) . PHP_EOL;
echo sprintf("Attempting to connect to the database: %s@%s/%s", $db['user'], $db['host'], $db['dbname']) . PHP_EOL;

$iter = 30;
$waitIntervalInSeconds = 2;
$connected = false;

while (!$connected && $iter > 0) {
    try {
        $pdo = new PDO($db['type'] . ':host=' . $db['host'] . ';port=' . $db['port'], $db['user'], $db['password'], [
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $version = $pdo->query('SELECT version()');
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

function execSQL(PDO $pdo, string $sql)
{
    $pdo->exec($sql);
    $error = $pdo->errorInfo();
    if ($error[2]) {
        var_dump($pdo->errorInfo());
        exit;
    }
}

echo sprintf("Dropping database: %s", $db['dbname']) . PHP_EOL;
execSQL($pdo, sprintf("DROP DATABASE IF EXISTS %s;", $db['dbname']));

echo sprintf("Creating database: %s", $db['dbname']) . PHP_EOL;
execSQL($pdo, sprintf("CREATE DATABASE %s;", $db['dbname']));

echo sprintf("Connecting to the %s database with the user: %s", $db['dbname'], $db['user']) . PHP_EOL;
execSQL($pdo, sprintf("USE %s;", $db['dbname']));

echo sprintf("Setting up the database structure from the dump: %s", $sqlStructure) . PHP_EOL;
$sql = file_get_contents($sqlStructure);
execSQL($pdo, $sql);

echo sprintf("Importing the database content from the dump: %s", $sqlContent) . PHP_EOL;
$sql = file_get_contents($sqlContent);
execSQL($pdo, $sql);

echo ("Creating the configuration file: config.php") . PHP_EOL;
$filesystem->copy(PATH_CONFIG_SAMPLE, PATH_CONFIG, true);

echo ("Creating the configuration file for Huraga") . PHP_EOL;
$filesystem->copy(HURAGA_CONFIG_TEMPLATE, HURAGA_CONFIG, true);

echo ("Successfully set up FOSSBilling.") . PHP_EOL;
