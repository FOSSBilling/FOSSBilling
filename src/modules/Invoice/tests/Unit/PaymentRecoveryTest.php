<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\GatewayChargeAttempt;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceCardCharge;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\GatewayChargeAttempts;
use Box\Mod\Invoice\ServicePaymentRecovery;
use Box\Mod\Invoice\ServiceSavedCards;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Exception\InformationException;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

function chargeRecoveryFixture(?Doctrine\DBAL\Connection $connection = null): array
{
    $db = $connection ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $realEm = EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($realEm))->createSchema([$realEm->getClassMetadata(GatewayChargeAttempt::class)]);
    $db->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, status TEXT, gateway_id INTEGER)');
    $db->insert('invoice', ['id' => 21, 'status' => 'unpaid']);
    $table = $db->quoteSingleIdentifier('transaction');
    $db->executeStatement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, invoice_id INTEGER, gateway_id INTEGER, txn_id TEXT, status TEXT, txn_status TEXT, amount TEXT, currency TEXT, created_at TEXT, updated_at TEXT)");
    $invoice = createEntity(Invoice::class, ['id' => 21, 'client_id' => 7, 'currency' => 'USD']);
    $gateway = createEntity(PayGateway::class, ['id' => 5, 'gateway' => 'Stripe']);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('find')->with(Invoice::class, 21)->andReturn($invoice);
    $em->shouldReceive('find')->with(PayGateway::class, 5)->andReturn($gateway);
    $em->shouldReceive('find')->with(InvoiceCardCharge::class, 21)->andReturn(null);
    $em->shouldReceive('persist')->byDefault();
    $em->shouldReceive('flush')->byDefault();
    $di = container();
    $di['em'] = $em;

    return [$di, $db, $invoice];
}

test('an interrupted charge is durably recorded and blocks even a staff retry', function (): void {
    [$di, $db, $invoice] = chargeRecoveryFixture();
    $fees = Mockery::mock();
    $fees->shouldReceive('apply')->once();
    $di['mod_service'] = $di->protect(moduleService(['invoice:gatewayfees' => $fees]));
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('gatewayId')->andReturn(5);
    $adapter->shouldReceive('savedCard')->once()->andReturn(['card' => 'card']);
    $adapter->shouldReceive('chargeSavedCard')->once()->andReturnUsing(function ($invoice, $attempt) use ($db): never {
        expect(GatewayChargeAttempts::pending($db, 21)['id'])->toBe($attempt)
            ->and($db->isTransactionActive())->toBeFalse();

        throw new RuntimeException('Connection lost after request');
    });
    $service = new ServiceSavedCards();
    $service->setDi($di);
    expect($service->charge($invoice, $adapter, true)['paid'])->toBeFalse()
        ->and($service->charge($invoice, $adapter, true)['error'])->toContain('reconciliation')
        ->and(GatewayChargeAttempts::pending($db, 21)['payment_id'])->toBeNull();
});

test('saved payments refuse an outer transaction before sending anything', function (): void {
    [$di, $db, $invoice] = chargeRecoveryFixture();
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldNotReceive('chargeSavedCard');
    $service = new ServiceSavedCards();
    $service->setDi($di);
    $db->beginTransaction();
    expect(fn () => $service->charge($invoice, $adapter, true))->toThrow(LogicException::class);
    $db->rollBack();
    expect(GatewayChargeAttempts::pending($db, 21))->toBeNull();
});

test('late results cannot change a resolved attempt or its replacement', function (): void {
    [$di, $db] = chargeRecoveryFixture();
    $first = GatewayChargeAttempts::begin($db, 21, 5);
    GatewayChargeAttempts::resolve($db, $first, 'failed');
    $second = GatewayChargeAttempts::begin($db, 21, 5);
    expect(fn () => GatewayChargeAttempts::recordPayment($db, $first, 'late'))->toThrow(LogicException::class)
        ->and(GatewayChargeAttempts::pending($db, 21)['id'])->toBe($second)
        ->and(GatewayChargeAttempts::pending($db, 21)['payment_id'])->toBeNull();
});

test('unknown outcomes stay blocked until staff records a provider reconciliation note', function (): void {
    [$di, $db] = chargeRecoveryFixture();
    $id = GatewayChargeAttempts::begin($db, 21, 5);
    $adapter = new stdClass(); // unsupported provider: never infer failure from lack of lookup support
    $gateways = Mockery::mock();
    $gateways->shouldReceive('getPaymentAdapter')->andReturn($adapter);
    $di['mod_service'] = $di->protect(moduleService(['invoice:paygateway' => $gateways]));
    $service = new ServicePaymentRecovery();
    $service->setDi($di);
    expect($service->inspect($id)['state'])->toBe('unknown')
        ->and(fn () => $service->reconcile($id, null, false, '', 9))->toThrow(InformationException::class)
        ->and(fn () => $service->reconcile($id, null, true, '', 9))->toThrow(InformationException::class)
        ->and(GatewayChargeAttempts::pending($db, 21)['id'])->toBe($id);
    $service->reconcile($id, null, true, 'Provider dashboard confirmed no charge; worker stopped.', 9);
    expect(GatewayChargeAttempts::pending($db, 21))->toBeNull()
        ->and(json_decode($db->fetchOne('SELECT resolution FROM invoice_gateway_charge_attempt WHERE id = ?', [$id]), true)['staff_id'])->toBe(9);
});

test('read-only lookup cannot replace a known reference and does not settle the attempt', function (): void {
    [$di, $db] = chargeRecoveryFixture();
    $id = GatewayChargeAttempts::begin($db, 21, 5);
    GatewayChargeAttempts::recordPayment($db, $id, 'pi_original');
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('inspectPayment')->once()->with('pi_original', 21)->andReturn(['state' => 'settled', 'reference' => 'pi_original', 'currency' => 'USD']);
    $gateways = Mockery::mock();
    $gateways->shouldReceive('getPaymentAdapter')->andReturn($adapter);
    $di['mod_service'] = $di->protect(moduleService(['invoice:paygateway' => $gateways]));
    $service = new ServicePaymentRecovery();
    $service->setDi($di);
    expect(fn () => $service->inspect($id, 'pi_other'))->toThrow(InformationException::class)
        ->and($service->inspect($id)['state'])->toBe('settled')
        ->and(GatewayChargeAttempts::pending($db, 21)['id'])->toBe($id);
});

test('saved PayPal charges persist the reference before routing the correct payment payload', function (string $adapterClass, string $parameter): void {
    [$di, $db, $invoice] = chargeRecoveryFixture();
    $adapter = Mockery::mock($adapterClass);
    $adapter->shouldReceive('gatewayId')->andReturn(5);
    $adapter->shouldReceive('savedCard')->andReturn(['card' => 'PayPal']);
    $adapter->shouldReceive('chargeSavedCard')->once()->andReturn('capture_ok');
    $fees = Mockery::mock();
    $fees->shouldReceive('apply')->once();
    $transactions = Mockery::mock();
    $transactions->shouldReceive('createAndProcess')->once()->andReturnUsing(function (array $data) use ($db, $parameter): int {
        expect($data['get'])->toBe([$parameter => 'capture_ok', 'invoice_id' => 21])
            ->and(GatewayChargeAttempts::pending($db, 21)['payment_id'])->toBe('capture_ok');
        $db->executeStatement("UPDATE invoice SET status = 'paid' WHERE id = 21");
        $db->insert($db->quoteSingleIdentifier('transaction'), ['id' => 44, 'invoice_id' => 21, 'gateway_id' => 5, 'txn_id' => 'capture_ok', 'status' => 'processed']);

        return 44;
    });
    $di['mod_service'] = $di->protect(moduleService(['invoice:gatewayfees' => $fees, 'invoice:transaction' => $transactions]));
    $service = new ServiceSavedCards();
    $service->setDi($di);
    expect($service->charge($invoice, $adapter, true)['paid'])->toBeTrue()
        ->and(GatewayChargeAttempts::pending($db, 21))->toBeNull();
})->with([[Payment_Adapter_PayPalEmail::class, 'paypal_capture'], [Payment_Adapter_PayPalAutoPay::class, 'paypal_txn']]);

test('reconciliation reuses a processed payment without settling it twice', function (): void {
    [$di, $db] = chargeRecoveryFixture();
    $id = GatewayChargeAttempts::begin($db, 21, 5);
    GatewayChargeAttempts::recordPayment($db, $id, 'pi_paid');
    $db->insert($db->quoteSingleIdentifier('transaction'), ['id' => 44, 'invoice_id' => 21, 'gateway_id' => 5, 'txn_id' => 'pi_paid', 'status' => 'processed']);
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('inspectPayment')->with('pi_paid', 21)->andReturn(['state' => 'settled', 'reference' => 'pi_paid', 'currency' => 'USD']);
    $gateways = Mockery::mock();
    $gateways->shouldReceive('getPaymentAdapter')->andReturn($adapter);
    $transactions = Mockery::mock();
    $transactions->shouldNotReceive('createAndProcess');
    $transactions->shouldNotReceive('preProcessTransaction');
    $di['mod_service'] = $di->protect(moduleService(['invoice:paygateway' => $gateways, 'invoice:transaction' => $transactions]));
    $service = new ServicePaymentRecovery();
    $service->setDi($di);
    $service->reconcile($id, null, false, 'Provider confirmed', 9);
    expect(GatewayChargeAttempts::pending($db, 21))->toBeNull()
        ->and(fn () => $service->reconcile($id, null, false, '', 9))->toThrow(InformationException::class);
});

test('payment recovery cannot replace an attempt owned by a live database session', function (string $driver): void {
    $raw = getenv('ATTACHMENT_TEST_' . strtoupper($driver) . '_PARAMS');
    if ($raw === false) {
        $this->markTestSkipped('An isolated database server is required.');
    }
    $params = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    $admin = DriverManager::getConnection($params);
    $database = 'fb_charge_' . bin2hex(random_bytes(6));
    $admin->executeStatement('CREATE DATABASE ' . $admin->quoteSingleIdentifier($database));
    $params['dbname'] = $database;
    $owner = DriverManager::getConnection($params);
    $recovery = DriverManager::getConnection($params);

    try {
        [$di, $db] = chargeRecoveryFixture($recovery);
        $id = GatewayChargeAttempts::begin($owner, 21, 5);
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($owner, 'fb:card-charge:21', 0))->toBeTrue();
        $service = new ServicePaymentRecovery();
        $service->setDi($di);
        expect(fn () => $service->reconcile($id, null, true, 'Must not release a live worker', 9))->toThrow(InformationException::class, 'still active')
            ->and(GatewayChargeAttempts::pending($recovery, 21)['id'])->toBe($id);
        FOSSBilling\Core\Doctrine\NamedLock::release($owner, 'fb:card-charge:21');
        GatewayChargeAttempts::recordPayment($owner, $id, 'known_reference');
        expect(GatewayChargeAttempts::pending($recovery, 21)['payment_id'])->toBe('known_reference');
    } finally {
        $owner->close();
        $recovery->close();
        $admin->executeStatement('DROP DATABASE ' . $admin->quoteSingleIdentifier($database));
        $admin->close();
    }
})->with(['mysql', 'pgsql']);
