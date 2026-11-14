<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\DeletionRecovery;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Doctrine\ORM\EntityManager;

function deferredPayPalApi(array $c): object
{
    return new class($c) {
        public bool $crash = false;
        public bool $allowInvoiceRefund = false;
        public bool $crashRefund = false;
        public array $refunds = [];
        public ?Closure $refundHandler = null;
        public ?Closure $beforeInvoiceRead = null;

        public function __construct(private array $c)
        {
        }

        public function invoice_transaction_get(array $data): array
        {
            return $this->c['connection']->fetchAssociative('SELECT invoice_id, type, txn_id, txn_status, amount, currency, status FROM ' . $this->c['connection']->quoteSingleIdentifier('transaction') . ' WHERE id = ?', [$data['id']]);
        }

        public function invoice_transaction_update(array $data): bool
        {
            if ($this->crash && ($data['status'] ?? '') === 'processed') {
                throw new Error('Interrupted before receipt completion');
            }
            $service = new Box\Mod\Invoice\ServiceTransaction();
            $service->setDi($this->c['di']);

            return $service->update($this->c['em']->find(Box\Mod\Invoice\Entity\Transaction::class, $data['id']), $data);
        }

        public function invoice_get(array $data): array
        {
            if ($this->beforeInvoiceRead !== null) {
                $callback = $this->beforeInvoiceRead;
                $this->beforeInvoiceRead = null;
                $callback();
            }

            return ['client' => ['id' => $this->c['client']->getId()], 'currency' => 'USD'];
        }

        public function invoice_refund(array $data): bool
        {
            if ($this->crashRefund) {
                throw new Error('Interrupted before invoice refund');
            }
            if (!$this->allowInvoiceRefund) {
                throw new RuntimeException('A deferred credit refund must not refund an invoice');
            }
            $this->refunds[] = $data;
            if ($this->refundHandler !== null) {
                return (bool) ($this->refundHandler)($data);
            }
            $invoice = $this->c['em']->find(Invoice::class, (int) $data['id']);
            $invoice->setStatus(Invoice::STATUS_REFUNDED)->setRefund($this->c['billing']->getTotalWithTax($invoice));
            $this->c['em']->flush();

            return true;
        }

        public function client_balance_add_funds(array $data): bool
        {
            $service = new Box\Mod\Client\Service();
            $service->setDi($this->c['di']);

            return $service->addFunds($this->c['client'], $data['amount'], $data['description'], $data);
        }
    };
}

function deferredPayPalMysqlEntityManager(?string $connectionClass = null): EntityManager
{
    $connection = Doctrine\DBAL\DriverManager::getConnection([
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) getenv('DELETION_HOLD_MYSQL_PORT'),
        'user' => 'root', 'password' => getenv('DELETION_HOLD_MYSQL_PASSWORD'), 'dbname' => 'hold_regression',
    ] + ($connectionClass === null ? [] : ['wrapperClass' => $connectionClass]));
    $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 4'); // cspell:ignore innodb
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('DeferredPayPalMysqlProxies');

    return new EntityManager($connection, $config);
}

function heldOrderPaymentContext(?string $databasePath = null): array
{
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('HeldOrderPaymentProxies');
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite'] + ($databasePath === null ? ['memory' => true] : ['path' => $databasePath]));
    $em = new EntityManager($connection, $config);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Order::class, OrderMeta::class, Invoice::class, InvoiceItem::class, Box\Mod\Invoice\Entity\Transaction::class, Box\Mod\Invoice\Entity\Subscription::class, Box\Mod\Invoice\Entity\PayGateway::class, Box\Mod\Client\Entity\Client::class, Box\Mod\Client\Entity\ClientBalance::class]));
    $order = (new Order())->setServiceType('custom')->setServiceId(1)->setStatus(Order::STATUS_ACTIVE)->setGroupMaster(false)
        ->setPrice(10)->setPeriod('1M')->setInvoiceOption('issue-invoice')->setExpiresAt(new DateTime('+1 day'));
    $em->persist($order);
    $em->flush();
    $di = Tests\Helpers\container();
    $di['em'] = $em;
    $c = compact('config', 'connection', 'em', 'order', 'di');
    $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
    $gateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('PayPalEmail')->setEnabled(true);
    $c['em']->persist($client);
    $c['em']->persist($gateway);
    $c['em']->flush();
    $c['order']->setClientId($client->getId())->setCurrency('USD');
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setCurrency('USD')->setClientId($client->getId())->setGateway($gateway);
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_ORDER)->setRelId((string) $c['order']->getId())->setPrice(10)->setQuantity(1);
    $c['em']->persist($invoice);
    $c['em']->persist($item);
    $c['em']->flush();
    $billing = new Box\Mod\Invoice\Service();
    $billing->setDi($c['di']);
    $items = new Box\Mod\Invoice\ServiceInvoiceItem();
    $items->setDi($c['di']);
    $c['di']['mod_service'] = $c['di']->protect(fn ($module, $submodule = null): object => $submodule === 'InvoiceItem' ? $items : $billing);

    return $c + compact('client', 'gateway', 'invoice', 'item', 'billing');
}

function deferredPayPalReceiptContext(?string $databasePath = null, bool $earlierAttempt = false, string $txnType = 'subscr_payment', string $amount = '3', string $currency = 'USD'): array
{
    $c = heldOrderPaymentContext($databasePath);
    $c['invoice']->setStatus(Invoice::STATUS_PAID);
    $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setSid('held-subscription')->setRelType('invoice')->setRelId($c['invoice']->getId());
    $receipt = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($c['invoice'])->setGateway($c['gateway'])->setStatus('processing')->setTxnId('PAYPAL-DEFERRED')->setTxnStatus('Completed')->setType($txnType)->setAmount($amount)->setCurrency($currency);
    if ($earlierAttempt) {
        $uncredited = (clone $receipt)->setAmount(null)->setType(null)->setTxnStatus(null);
        $c['em']->persist($uncredited);
        $c['em']->flush();
    }
    $c['em']->persist($subscription);
    $c['em']->persist($receipt);
    $c['em']->flush();
    DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
    $adapter->setDi($c['di']);
    $api = deferredPayPalApi($c);
    $payload = [
        'get' => ['invoice_id' => $c['invoice']->getId()],
        'post' => ['txn_type' => $txnType, 'payment_status' => 'Completed', 'txn_id' => 'PAYPAL-DEFERRED', 'subscr_id' => 'held-subscription', 'mc_gross' => $amount, 'mc_currency' => $currency],
    ];

    return $c + compact('receipt', 'adapter', 'api', 'payload');
}

function deferredPayPalRefund(array $c, string $externalId, string $amount, string $currency = 'USD'): array
{
    $refund = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($c['invoice'])->setGateway($c['gateway'])->setStatus('processing');
    $c['em']->persist($refund);
    $c['em']->flush();
    $payload = $c['payload'];
    $payload['post'] = ['txn_type' => 'subscr_payment', 'payment_status' => 'Refunded', 'txn_id' => $externalId, 'parent_txn_id' => 'PAYPAL-DEFERRED', 'subscr_id' => 'held-subscription', 'mc_gross' => $amount, 'mc_currency' => $currency];

    return [$refund, $payload];
}

function settledPayPalRenewal(array $c): Invoice
{
    $invoice = (new Invoice())->setClientId($c['client']->getId())->setCurrency('USD')->setGateway($c['gateway'])->setIssued(true)->setStatus(Invoice::STATUS_PAID);
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setPrice(1)->setQuantity(3)->setTaxed(false);
    $c['em']->persist($invoice);
    $c['em']->persist($item);
    $c['receipt']->setInvoice($invoice)->setStatus('processed');
    $c['em']->flush();

    return $invoice;
}
