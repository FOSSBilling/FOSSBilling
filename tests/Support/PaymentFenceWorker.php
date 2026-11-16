<?php

declare(strict_types=1);

putenv('APP_ENV=test');
require __DIR__ . '/../../src/load.php';
require __DIR__ . '/../../src/vendor/autoload.php';

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\ServiceTransaction;
use FOSSBilling\Core\Doctrine\DriverManagerFactory;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Payment\ProcessingFence;

function paymentWorkerWait(): void
{
    if (trim((string) fgets(STDIN)) !== 'go') {
        throw new RuntimeException('Missing worker barrier');
    }
}

$config = json_decode((string) getenv('PAYMENT_FENCE_DB'), true, flags: JSON_THROW_ON_ERROR);
$db = DriverManagerFactory::getConnection(dbConfig: $config);
$em = EntityManagerFactory::create($db);
$di = new Pimple\Container();
$di['em'] = $em;
$di['logger'] = new Psr\Log\NullLogger();
$di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
$service = new ServiceTransaction();
$service->setDi($di);
$action = (string) getenv('PAYMENT_FENCE_ACTION');
$id = (int) (getenv('PAYMENT_FENCE_TX') ?: 1);
if ($action === 'release') {
    fwrite(STDOUT, "ready\n");

    try {
        $expected = $db->fetchOne('SELECT processing_token FROM ' . $db->quoteSingleIdentifier('transaction') . ' WHERE id = 1');
        $service->releaseStaleProcessing(1, 9, $expected);
        echo json_encode(['released' => true]) . "\n";
    } catch (FOSSBilling\Core\Exception\InformationException) {
        echo json_encode(['released' => false]) . "\n";
    }
    exit;
}
if (!$service->claimForProcessing($id)) {
    throw new RuntimeException('Worker could not acquire initial claim');
}
$tx = $em->find(Transaction::class, $id);
$invoice = $em->find(Invoice::class, 1);
$client = $em->find(Client::class, 1);
$token = $tx->getProcessingToken();
$prepared = $db->prepare('UPDATE client_balance SET amount = 999 WHERE id = 1');
$clients = new Box\Mod\Client\Service();
$clients->setDi($di);
$invoices = new Box\Mod\Invoice\Service();
$invoices->setDi($di);
$otherEm = EntityManagerFactory::create(DriverManagerFactory::getConnection(dbConfig: $config));
$otherTx = $otherEm->find(Transaction::class, 1);
$claim = (string) getenv('PAYMENT_FENCE_CHARGE');
$chargeToken = $claim === '' ? null : $db->fetchOne('SELECT owner_token FROM invoice_gateway_charge_attempt WHERE id = ?', [$claim]);
$work = function () use ($db, $em, $tx, $invoice, $client, $prepared, $clients, $invoices, $service, $otherEm, $otherTx, $action): void {
    fwrite(STDOUT, "ready\n");
    paymentWorkerWait();
    if (in_array($action, ['stripe_initial', 'stripe_initial_unpaid', 'stripe_invoice'], true)) {
        $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy', 'gateway_id' => 1]);
        $di = new Pimple\Container();
        $di['em'] = $em;
        $di['logger'] = new Psr\Log\NullLogger();
        require_once __DIR__ . '/../Helpers/Container.php';
        require_once __DIR__ . '/../../src/modules/Invoice/tests/Fixtures/GatewayReceiptContext.php';
        $billing = gatewayDepositTestService($em);
        $adapter->setDi($billing->getDi());
        $api = new stdClass();
        $latest = Stripe\Invoice::constructFrom(['id' => 'in_initial_' . $db->getDatabase(), 'status' => 'paid', 'amount_paid' => 1000, 'currency' => 'usd']);
        $subscription = Stripe\Subscription::constructFrom(['id' => 'sub_initial', 'latest_invoice' => $latest]);
        if ($action === 'stripe_invoice') {
            $latest->billing_reason = 'subscription_create';
            $latest->subscription = 'sub_initial';
            $remoteSubscription = Stripe\Subscription::constructFrom(['id' => 'sub_initial', 'metadata' => ['invoice_id' => '1', 'client_id' => '1', 'gateway_id' => '1']]);
            $subscriptions = Mockery::mock();
            $subscriptions->shouldReceive('retrieve')->andReturn($remoteSubscription);
            $stripe = Mockery::mock(Stripe\StripeClient::class);
            $stripe->subscriptions = $subscriptions;
            (new ReflectionProperty($adapter, 'stripe'))->setValue($adapter, $stripe);
            $event = (object) ['data' => (object) ['object' => $latest]];
            if (!(new ReflectionMethod($adapter, 'handleInvoicePaymentSucceeded'))->invoke($adapter, $api, $tx, $event, 1)) {
                $tx->setAmount('0');
            }
        } else {
            $amount = (new ReflectionMethod($adapter, 'processInitialSubscriptionPayment'))->invoke($adapter, $api, $tx, $invoice, $subscription);
            $tx->setAmount((string) ($amount ?? 0));
        }
        $tx->setStatus(Transaction::STATUS_PROCESSED);
        $em->flush();
        fwrite(STDOUT, "settled\n");
        paymentWorkerWait();

        return;
    }
    if ($action === 'prefix_credit') {
        $clients->addFunds($client, 10, 'Credit before takeover');
        fwrite(STDOUT, "credited\n");
        paymentWorkerWait();
        $db->executeStatement("UPDATE invoice SET status = 'paid' WHERE id = 1");

        return;
    }
    if ($action === 'credit_snapshot') {
        $isolationSql = "SHOW SESSION VARIABLES WHERE Variable_name IN ('transaction_isolation', 'tx_isolation')";
        $sessionIsolation = $db->fetchAllAssociative($isolationSql);
        $db->transactional(function () use ($db, $clients, $client): void {
            $db->fetchOne('SELECT COUNT(*) FROM client_balance');
            fwrite(STDOUT, "snapshot\n");
            paymentWorkerWait();
            $clients->addFunds($client, 10, 'Same receipt after snapshot', ['type' => 'transaction', 'payment_reference' => 'pi_once']);
        });
        if ($db->fetchAllAssociative($isolationSql) !== $sessionIsolation) {
            throw new RuntimeException('Payment processing changed the session isolation level.');
        }

        return;
    }
    if ($action === 'credit_once') {
        $clients->addFunds($client, 10, 'Same provider receipt', ['type' => 'transaction', 'payment_reference' => 'pi_once']);

        return;
    }
    if ($action === 'settlement_commit' || $action === 'settlement_rollback') {
        $db->beginTransaction();
        $clients->addFunds($client, 10, 'Fenced settlement');
        $invoice->setStatus(Invoice::STATUS_PAID);
        $tx->setStatus(Transaction::STATUS_PROCESSED);
        $em->flush();
        fwrite(STDOUT, "written\n");
        paymentWorkerWait();
        if ($action === 'settlement_commit') {
            $db->commit();
        } else {
            $db->rollBack();
            fwrite(STDOUT, "rolled_back\n");
            paymentWorkerWait();
            $db->executeStatement('UPDATE invoice SET status = ? WHERE id = 1', ['paid']);
        }

        return;
    }
    match ($action) {
        'raw' => $db->executeStatement('UPDATE invoice SET status = ? WHERE id = 1', ['paid']),
        'swallowed' => (function () use ($db, $tx): void {
            $tx->setAmount('999');

            try {
                $db->fetchOne('SELECT 1');
            } catch (FOSSBilling\Core\Payment\OwnershipLost) { /* an adapter must not turn this into success */
            }
        })(),
        'reconnect' => (function () use ($db): void {
            $db->close();
            $db->executeStatement("UPDATE invoice SET status = 'paid' WHERE id = 1");
        })(),
        'refresh_then_write' => (function () use ($em, $tx): void {
            $em->refresh($tx);
            $tx->setAmount('999');
            $em->flush();
        })(),
        'refund_write' => $db->executeStatement('UPDATE invoice SET refund = 999 WHERE id = 1'),
        'query_write' => $db->executeQuery("UPDATE invoice SET status = 'paid' WHERE id = 1"),
        'prepared' => $prepared->executeStatement(),
        'wrapped_prepared' => $prepared->getWrappedStatement()->execute(),
        'client_funds' => $clients->addFunds($client, 10, 'Late credit'),
        'invoice_paid' => $invoices->markAsPaid($invoice, false, false),
        'clearing' => $invoices->settleClearingPayment($invoice, $tx, $tx, 'PayPal'),
        'transaction_update' => $service->update($tx, ['amount' => '999', 'status' => 'processed']),
        'orm' => (function () use ($em, $tx): void {
            $tx->setAmount('999');
            $em->flush();
        })(),
        'other_manager' => (function () use ($otherEm, $otherTx): void {
            $otherTx->setAmount('999');
            $otherEm->flush();
        })(),
        default => throw new RuntimeException('Unknown payment worker action'),
    };
};

try {
    if ($claim !== '') {
        ProcessingFence::charge($db, $claim, $chargeToken, $work);
    } else {
        ProcessingFence::transaction($db, $id, $token, $work);
    }
    echo json_encode(['completed' => true, 'scopes' => count(ProcessingFence::claims())]) . "\n";
} catch (Throwable $error) {
    // Exercise the real failure recorder after the manager may have closed.
    // A stale worker must not change the new owner's error/status either.
    if ($claim === '') {
        (new ReflectionClass($service))->getMethod('markTransactionError')->invoke($service, $id, $error);
    }
    echo json_encode(['error' => $error::class, 'message' => $error->getMessage(), 'trace' => $error->getTraceAsString(), 'scopes' => count(ProcessingFence::claims())]) . "\n";
}
