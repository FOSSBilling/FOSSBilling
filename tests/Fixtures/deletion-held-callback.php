<?php

declare(strict_types=1);

define('DEBUG', false);
define('PATH_ROOT', dirname(__DIR__, 2) . '/src');
define('PATH_CONFIG', PATH_ROOT . '/config-sample.php');
define('PATH_DATA', $argv[2]);
require PATH_ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Helpers/Container.php';
putenv('APP_ENV=test');
$config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_ROOT . '/modules'], true);
$config->setProxyDir(sys_get_temp_dir());
$config->setProxyNamespace('DeletionHeldCallbackProxies');
$connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $argv[1]]);
$em = new Doctrine\ORM\EntityManager($connection, $config);
$di = Tests\Helpers\container();
$di['em'] = $em;
$di['mod_service'] = $di->protect(function (): never { throw new RuntimeException('The held callback reached its service backend'); });
$service = new Box\Mod\Order\Service();
$service->setDi($di);
$order = $em->find(Box\Mod\Order\Entity\Order::class, (int) $argv[3]);
echo "ready\n";
flush();

try {
    (new ReflectionMethod($service, '_callOnService'))->invoke($service, $order, Box\Mod\Order\Entity\Order::ACTION_RENEW);
    echo json_encode(['blocked' => false], JSON_THROW_ON_ERROR);
} catch (FOSSBilling\Core\Exception\InformationException $e) {
    echo json_encode(['blocked' => str_contains($e->getMessage(), 'awaiting recovery')], JSON_THROW_ON_ERROR);
}
