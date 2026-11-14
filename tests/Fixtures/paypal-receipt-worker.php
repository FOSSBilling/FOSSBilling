<?php

declare(strict_types=1);

define('DEBUG', false);
define('PATH_ROOT', dirname(__DIR__, 2) . '/src');
define('PATH_MODS', PATH_ROOT . '/modules');
define('PATH_CONFIG', PATH_ROOT . '/config-sample.php');
define('PATH_DATA', $argv[2]);
require PATH_ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Helpers/Container.php';
require dirname(__DIR__) . '/Helpers/DeferredPayPal.php';
putenv('APP_ENV=test');
$config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
$config->setProxyDir(sys_get_temp_dir());
$config->setProxyNamespace('PayPalReceiptWorkerProxies');
$connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $argv[1]]);
$em = new Doctrine\ORM\EntityManager($connection, $config);
$di = Tests\Helpers\container();
$di['em'] = $em;
$billing = new Box\Mod\Invoice\Service();
$billing->setDi($di);
$di['mod_service'] = $di->protect(fn (): object => $billing);
// Deliberately preload stale ORM state, as ServiceTransaction does before dispatch.
$receipt = $em->find(Box\Mod\Invoice\Entity\Transaction::class, (int) $argv[3]);
$invoice = $receipt->getInvoice();
$client = $em->find(Box\Mod\Client\Entity\Client::class, $invoice->getClientId());
if ($argv[4] === 'claim') {
    $transactions = new Box\Mod\Invoice\ServiceTransaction();
    $transactions->setDi($di);
    if (!$transactions->claimForProcessing($receipt->getId())) {
        throw new RuntimeException('Could not reclaim the expired processing lease');
    }
}
echo "ready\n";
flush();
if ($argv[4] === 'wait') {
    fgets(STDIN);
}
$c = compact('connection', 'em', 'di', 'client');
$api = deferredPayPalApi($c);
$adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
$adapter->setDi($di);
$adapter->processTransaction($api, $receipt->getId(), [
    'get' => ['invoice_id' => $invoice->getId()],
    'post' => ['txn_type' => 'subscr_payment', 'payment_status' => 'Completed', 'subscr_id' => 'held-subscription', 'txn_id' => $receipt->getTxnId(), 'mc_gross' => $receipt->getAmount(), 'mc_currency' => 'USD'],
], $receipt->getGateway()->getId());
echo "processed\n";
