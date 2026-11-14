<?php

declare(strict_types=1);

define('DEBUG', false);
define('PATH_ROOT', dirname(__DIR__, 2) . '/src');
define('PATH_MODS', PATH_ROOT . '/modules');
define('PATH_CONFIG', PATH_ROOT . '/config-sample.php');
define('PATH_DATA', $argv[1]);
require PATH_ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Helpers/Container.php';
require dirname(__DIR__) . '/Helpers/DeferredPayPal.php';
putenv('APP_ENV=test');

class PayPalRefundBarrierConnection extends Doctrine\DBAL\Connection
{
    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        $result = parent::fetchOne($query, $params, $types);
        if (str_starts_with($query, 'SELECT id FROM client WHERE id = :client_id')) {
            // Both clients own their real wallet mutex before either refund
            // reads checkpoints or inserts its balancing ledger entry.
            echo "ready\n";
            flush();
            fgets(STDIN);
        }

        return $result;
    }
}

$em = deferredPayPalMysqlEntityManager(PayPalRefundBarrierConnection::class);
$connection = $em->getConnection();
$receipt = $em->find(Box\Mod\Invoice\Entity\Transaction::class, (int) $argv[2]);
$invoice = $receipt->getInvoice();
$client = $em->find(Box\Mod\Client\Entity\Client::class, $invoice->getClientId());
$di = Tests\Helpers\container();
$di['em'] = $em;
$api = deletionPayPalApi(compact('connection', 'em', 'di', 'client'));
$adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
$adapter->setDi($di);
$adapter->processTransaction($api, $receipt->getId(), [
    'get' => ['invoice_id' => $invoice->getId()],
    'post' => [
        'txn_type' => 'subscr_payment', 'payment_status' => 'Refunded', 'txn_id' => 'REFUND-' . $receipt->getId(),
        'parent_txn_id' => 'PAYMENT-' . $client->getId(), 'subscr_id' => 'SUB-' . $client->getId(),
        'mc_gross' => '-3', 'mc_currency' => 'USD',
    ],
], $receipt->getGateway()->getId());
echo "processed\n";
