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
$config->setProxyNamespace('OrderCreateDeleteWorkerProxies');
$connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $argv[1]]);
$em = new Doctrine\ORM\EntityManager($connection, $config);
$di = Tests\Helpers\container();
$di['em'] = $em;
$orderService = new Box\Mod\Order\Service();
$orderService->setDi($di);
$licenseService = new Box\Mod\Servicelicense\Service();
$licenseService->setDi($di);
$subscription = new class {
    public function cancelForOrder(...$args): never
    {
        throw new RuntimeException('Gateway unavailable');
    }
};
$di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
    'order' => $orderService, 'servicelicense' => $licenseService, 'invoice' => $subscription,
});
$order = $em->find(Box\Mod\Order\Entity\Order::class, (int) $argv[3]);
$name = 'order_lifecycle_' . $order->getId();
$acquired = FOSSBilling\Core\Doctrine\NamedLock::acquire($connection, $name, 0);
if ($acquired) {
    FOSSBilling\Core\Doctrine\NamedLock::release($connection, $name);
}
echo $acquired ? "unlocked\n" : "locked\n";
flush();

try {
    if (($argv[4] ?? 'order') === 'client') {
        $clientService = new Box\Mod\Client\Service();
        $clientService->setDi($di);
        $clientService->remove($em->find(Box\Mod\Client\Entity\Client::class, $order->getClientId()));
        echo "client removed\n";
    } else {
        $orderService->deleteFromOrder($order);
        echo "order removed\n";
    }
} catch (RuntimeException|FOSSBilling\Core\Exception\InformationException $e) {
    echo $e->getMessage() . "\n";
}
