<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('Stripe keeps the canonical receipt through post-commit failure and competing delivery', function (string $mode, bool $crash, string $entrypoint): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(Subscription::class)]);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setTitle('Service')->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setPrice(15)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setStatus(Transaction::STATUS_RECEIVED);
    $sourceId = $invoice->getId();
    if ($mode === 'subscription') {
        $source = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
        $em->persist($source);
        $em->flush();
        $sourceId = $source->getId();
        $transaction->setOriginalInvoiceId($sourceId);
    }
    $em->flush();
    $gatewayId = $gateway->getId();
    $clientId = $client->getId();
    $transactionId = $transaction->getId();
    $invoiceId = $invoice->getId();
    $providerId = $mode === 'payment' ? 'pi_recovery' : 'in_recovery';
    $transactionKey = $mode === 'setup' ? 'seti_recovery' : $providerId;
    $metadata = ['invoice_id' => (string) $sourceId, 'client_id' => (string) $clientId, 'gateway_id' => (string) $gatewayId, 'price_id' => 'price_recovery'];
    $subscription = Stripe\Subscription::constructFrom([
        'id' => 'sub_recovery', 'object' => 'subscription', 'metadata' => $metadata,
        'latest_invoice' => ['id' => $providerId, 'object' => 'invoice', 'status' => 'paid', 'amount_paid' => 1500, 'currency' => 'usd'],
    ]);
    $subscriptions = Mockery::mock();
    $subscriptions->shouldReceive('retrieve')->andReturn($subscription);
    $subscriptions->shouldReceive('create')->andReturn($subscription);
    $setup = Stripe\SetupIntent::constructFrom(['id' => 'seti_recovery', 'object' => 'setup_intent', 'status' => 'succeeded', 'customer' => 'cus_recovery', 'payment_method' => 'pm_recovery', 'metadata' => $metadata]);
    $setups = Mockery::mock();
    $setups->shouldReceive('retrieve')->andReturn($setup);
    $sdk = Mockery::mock(Stripe\StripeClient::class);
    $sdk->subscriptions = $subscriptions;
    $sdk->setupIntents = $setups;
    $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy', 'test_webhook_secret' => 'whsec_recovery', 'gateway_id' => $gatewayId]);
    (new ReflectionProperty($adapter, 'stripe'))->setValue($adapter, $sdk);
    $api = Mockery::mock();
    $api->shouldReceive('invoice_subscription_create')->andReturnUsing(function (array $data) use ($em): int {
        $row = (new Subscription())->setSid($data['sid'])->setStatus('active');
        $em->persist($row);
        $em->flush();

        return $row->getId();
    });
    $notified = 0;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$notified, $crash): void {
        if (++$notified === 1) {
            throw $crash ? new Error('process stopped after receipt commit') : new RuntimeException('payment listener failed');
        }
    });
    $install = function (EntityManager $manager) use ($adapter, $dispatcher): Box\Mod\Invoice\Service {
        $billing = gatewayDepositTestService($manager);
        $di = $billing->getDi();
        $di['event_dispatcher'] = $dispatcher;
        $services = $di['mod_service'];
        unset($di['mod_service']);
        $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptions->shouldReceive('getSubscriptionPeriod')->andReturn('1M');
        $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $sub === 'Subscription' ? $subscriptions : $services($module, $sub));
        $adapter->setDi($di);

        return $billing;
    };
    $billing = $install($em);
    $payload = json_encode(['id' => 'evt_recovery', 'object' => 'event', 'type' => $mode === 'payment' ? 'payment_intent.succeeded' : 'invoice.paid', 'data' => ['object' => $mode === 'payment'
        ? ['id' => $providerId, 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 1500, 'currency' => 'usd', 'metadata' => $metadata]
        : ['id' => $providerId, 'object' => 'invoice', 'status' => 'paid', 'subscription' => 'sub_recovery', 'billing_reason' => 'subscription_cycle', 'amount_paid' => 1500, 'currency' => 'usd', 'metadata' => $metadata]]], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $data = ['http_raw_post_data' => $payload, 'server' => ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, 'whsec_recovery')]];
    $adapterCalls = 0;
    $run = function (Transaction $tx, Invoice $target) use ($mode, $adapter, $api, $data, $gatewayId, &$adapterCalls): void {
        ++$adapterCalls;
        if ($mode === 'setup') {
            (new ReflectionMethod($adapter, 'processSetupIntent'))->invoke($adapter, $api, $tx, $target, ['get' => ['setup_intent' => 'seti_recovery']], $gatewayId);
        } else {
            (new ReflectionMethod($adapter, 'processWebhookEvent'))->invoke($adapter, $api, $tx, $data, $gatewayId);
        }
    };
    $makeWorker = function (EntityManager $manager, Box\Mod\Invoice\Service $billing) use ($run, $invoiceId): Box\Mod\Invoice\ServiceTransaction {
        $di = $billing->getDi();
        $di['api_system'] = new stdClass();
        $bridge = new readonly class($manager, $run, $invoiceId) {
            public function __construct(private EntityManager $manager, private Closure $run, private int $invoiceId)
            {
            }

            public function processTransaction(mixed $api, int $id, array $payload, int $gatewayId): void
            {
                $tx = $this->manager->find(Transaction::class, $id);
                $this->manager->refresh($tx);
                ($this->run)($tx, $this->manager->find(Invoice::class, $this->invoiceId));
            }
        };
        $gateways = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $gateways->shouldReceive('getPaymentAdapter')->andReturn($bridge);
        $services = $di['mod_service'];
        unset($di['mod_service']);
        $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $sub === 'PayGateway' ? $gateways : $services($module, $sub));
        // Only creation is substituted for the synchronous entry point; the
        // real claim, dispatch, completion and error handling all run.
        $worker = Mockery::mock(Box\Mod\Invoice\ServiceTransaction::class)->makePartial();
        $worker->setDi($di);

        return $worker;
    };
    if ($crash) {
        // Stop at the durable boundary, as a killed process would: the worker
        // never gets to its catch block or completion acknowledgement.
        expect(fn () => $run($transaction, $invoice))->toThrow(Error::class);
    } else {
        $makeWorker($em, $billing)->processAndCatchErrors($transactionId);
    }
    $connection = $em->getConnection();
    $table = $connection->quoteSingleIdentifier('transaction');
    expect((int) $connection->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe($transactionId)
        ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoiceId]))->toBe(Invoice::STATUS_PAID)
        ->and($connection->fetchOne("SELECT status FROM $table WHERE id = ?", [$transactionId]))->toBe(Transaction::STATUS_PROCESSED)
        ->and((int) $connection->fetchOne("SELECT gateway_completion_state FROM $table WHERE id = ?", [$transactionId]))->toBe($crash ? Transaction::GATEWAY_COMPLETION_RUNNING : Transaction::GATEWAY_COMPLETION_PENDING);
    if ($crash) {
        expect($connection->fetchOne("SELECT error FROM $table WHERE id = ?", [$transactionId]))->toBeNull();
        // Advance past the existing processing lease without changing status,
        // diagnostics or the financial checkpoint.
        $connection->update($table, ['updated_at' => date('Y-m-d H:i:s', time() - 600)], ['id' => $transactionId]);
    }
    $fresh = new EntityManager($connection, $em->getConfiguration());
    if ($fresh->getConnection()->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
        $fresh->getEventManager()->addEventListener(Doctrine\ORM\Tools\ToolEvents::postGenerateSchemaTable, new FOSSBilling\Core\Doctrine\IndexLengthsListener());
    }
    $billing = $install($fresh);
    $canonical = $fresh->find(Transaction::class, $transactionId);
    $target = $fresh->find(Invoice::class, $invoiceId);
    $duplicate = (new Transaction())->setGateway($canonical->getGateway())->setInvoice($target)->setCurrency('USD')->setAmount('15')->setTxnId($transactionKey)->setStatus(Transaction::STATUS_PROCESSING);
    $fresh->persist($duplicate);
    $fresh->flush();
    $duplicateId = $duplicate->getId();
    $worker = $makeWorker($fresh, $billing);
    expect(array_column($worker->getReceived(), 'id'))->toContain($transactionId);
    $beforeRetry = $adapterCalls;
    if ($entrypoint === 'synchronous') {
        $worker->shouldReceive('create')->once()->with([])->andReturn($transactionId);
        $worker->createAndProcess([]);
    } else {
        $worker->processAndCatchErrors($transactionId);
    }
    expect($adapterCalls)->toBe($beforeRetry);
    expect($canonical->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE);
    expect($canonical->getStatus())->toBe(Transaction::STATUS_PROCESSED)->and($canonical->getError())->toBeNull();
    $connection->update($table, ['updated_at' => date('Y-m-d H:i:s', time() - 600)], ['id' => $duplicateId]);
    $worker->processAndCatchErrors($duplicateId);
    expect($fresh->find(Transaction::class, $duplicateId)->getStatus())->toBe(Transaction::STATUS_PROCESSING);
    // Ambiguous processing is never reclaimed just because its timestamp aged.
    $worker->releaseStaleProcessing($duplicateId, null, null);
    $worker->processAndCatchErrors($duplicateId);
    expect($fresh->find(Transaction::class, $transactionId))->toBe($canonical)
        ->and($fresh->find(Transaction::class, $duplicateId))->toBeNull()
        ->and($billing->getFreeCredit($clientId))->toBe(0.0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
        ->and((int) $connection->fetchOne("SELECT COUNT(*) FROM client_balance WHERE type = 'invoice'"))->toBe(1)
        ->and($notified)->toBe(2);
})->with(['payment', 'subscription', 'setup'])->with(['listener failure' => false, 'crash' => true])->with(['background', 'synchronous']);

test('Stripe replay keeps a legacy invoice link when provenance was not yet recorded', function (string $state): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $source = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $target = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $em->persist($source);
    $em->persist($target);
    $transaction->setInvoice($target)->setTxnId('in_legacy');
    $em->flush();

    $billing = gatewayDepositTestService($em);
    $di = $billing->getDi();
    $dispatcher = new EventDispatcher();
    $notified = [];
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function ($event) use (&$notified): void {
        $notified[] = $event->invoiceId;
    });
    $di['event_dispatcher'] = $dispatcher;
    $billing->shouldNotReceive('generateRenewalInvoiceForSubscriptionPayment');
    if ($state !== 'no receipt') {
        $amount = (new Box\Mod\Client\Entity\ClientBalance())->setAmount('15')->setClient($client)->setType('transaction')
            ->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':in_legacy'))->setGatewayTransactionId($transaction->getId());
        $em->persist($amount);
        $transaction->setStatus(Transaction::STATUS_PROCESSED)
            ->setGatewayCompletionState($state === 'interrupted' ? Transaction::GATEWAY_COMPLETION_PENDING : Transaction::GATEWAY_COMPLETION_DONE);
        $em->flush();
    }

    $subscriptions = Mockery::mock();
    $subscriptions->shouldReceive('retrieve')->once()->with('sub_legacy', [])->andReturn((object) [
        'id' => 'sub_legacy', 'metadata' => (object) ['invoice_id' => $source->getId(), 'client_id' => $client->getId()],
    ]);
    $sdk = Mockery::mock(Stripe\StripeClient::class);
    $sdk->subscriptions = $subscriptions;
    $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy', 'gateway_id' => $gateway->getId()]);
    (new ReflectionProperty($adapter, 'stripe'))->setValue($adapter, $sdk);
    $adapter->setDi($di);
    $invoice = (object) ['id' => 'in_legacy', 'billing_reason' => 'subscription_cycle', 'amount_paid' => 1500, 'currency' => 'usd'];
    $replay = fn () => (new ReflectionMethod($adapter, 'handleInvoicePaymentSucceededUnderLock'))->invoke($adapter, new stdClass(), $transaction, $invoice, 'sub_legacy');
    if ($state === 'no receipt') {
        expect($replay)->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'stored invoice could not be verified');
    } else {
        expect($replay())->toBeTrue();
    }

    $em->refresh($transaction);
    expect($transaction->getInvoice()?->getId())->toBe($target->getId())
        ->and($transaction->getOriginalInvoiceId())->toBeNull()
        ->and($notified)->toBe($state === 'interrupted' ? [$target->getId()] : []);
})->with(['interrupted', 'completed', 'no receipt']);
