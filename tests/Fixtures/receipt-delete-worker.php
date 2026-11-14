<?php

declare(strict_types=1);

define('DEBUG', false);
define('PATH_ROOT', dirname(__DIR__, 2) . '/src');
define('PATH_MODS', PATH_ROOT . '/modules');
define('PATH_CONFIG', PATH_ROOT . '/config-sample.php');
define('PATH_DATA', $argv[2]);
require PATH_ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Helpers/Container.php';
putenv('APP_ENV=test');
$config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
$config->setProxyDir(sys_get_temp_dir());
$config->setProxyNamespace('ReceiptDeleteWorkerProxies');
$connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $argv[1]]);
$em = new Doctrine\ORM\EntityManager($connection, $config);
$di = Tests\Helpers\container();
$di['em'] = $em;
$receipt = $em->find(Box\Mod\Invoice\Entity\Transaction::class, (int) $argv[3]);
$service = new Box\Mod\Invoice\ServiceTransaction();
$service->setDi($di);
echo "ready\n";
flush();

try {
    $service->delete($receipt);
    echo "deleted\n";
} catch (FOSSBilling\Core\Exception\InformationException $e) {
    if (!str_contains($e->getMessage(), 'record money') && !str_contains($e->getMessage(), 'backing gateway receipt')) {
        throw $e;
    }
    echo "blocked\n";
}
