<?php

$pathApp = realpath(dirname(__FILE__) . '/..');
$pathAppRoot = $pathApp . '/src';
$pathAppInstall = $pathApp . '/src/install';
$config = include $pathAppRoot . '/bb-config.php';

$structureSql = '/sql/structure.sql';
$contentSql = '/sql/content_test.sql';
if (isset($argv[1]) && $argv[1] == 'production') {
    $contentSql = '/sql/content.sql';
    echo "Production content" . PHP_EOL;
}

$type = $config['db']['type'];
$host = $config['db']['host'];
$dbname = $config['db']['name'];
$user = $config['db']['user'];
$password = $config['db']['password'];

echo sprintf("Connecting to database %s@%s/%s", $user, $host, $dbname) . PHP_EOL;

$iter = 30;
$connected = false;
while ($connected == false && $iter > 0) {

    try {
        $dbh = new PDO($type . ':host=' . $host, $user, $password,        array(
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY         => true,
            PDO::ATTR_ERRMODE                          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE               => PDO::FETCH_ASSOC,
        ));
        $dbh->query('select 1;');
        $connected = true;
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message = "SQLSTATE[HY000] [2002] Connection refused in /var/www/html/bin/prepare.php:23";
        if(strpos($e->getMessage(), "Connection refused") !== false) {
            sleep(1); // mysql container might still not be up, lets wait
            $iter--;
            echo "Waiting for database container to go up ".$iter . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

echo sprintf("Dropping database %s", $dbname) . PHP_EOL;
$sql = sprintf("DROP DATABASE IF EXISTS %s;", $dbname);
$dbh->exec($sql);
$error = $dbh->errorInfo();
if ($error[2]) {
    var_dump($dbh->errorInfo());
    exit;
}

echo sprintf("Creating database %s", $dbname) . PHP_EOL;
$sql = sprintf("CREATE DATABASE %s;", $dbname);
$dbh->exec($sql);
$error = $dbh->errorInfo();
if ($error[2]) {
    var_dump($dbh->errorInfo());
    exit;
}

echo sprintf("Connecting to %s database with user %s", $dbname, $user) . PHP_EOL;
$sql = sprintf("use %s;", $dbname);
$dbh->exec($sql);
$error = $dbh->errorInfo();
if ($error[2]) {
    var_dump($dbh->errorInfo());
    exit;
}

echo sprintf("Create SQL database structure from file %s", $structureSql) . PHP_EOL;
$sql = file_get_contents($pathAppInstall . $structureSql);
$dbh->exec($sql);
$error = $dbh->errorInfo();
if ($error[2]) {
    var_dump($dbh->errorInfo());
    exit;
}

echo sprintf("Import content to database from file %s", $contentSql) . PHP_EOL;
$sql = file_get_contents($pathAppInstall . $contentSql);
$stmt = $dbh->prepare($sql);
$stmt->execute();
$i = 0;
do{
}while($stmt->nextRowset());


echo "Finished" . PHP_EOL;