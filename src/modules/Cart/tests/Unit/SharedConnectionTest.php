<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Cart\Entity\CartProduct;
use Box\Mod\Client\Entity\Client;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Entity\OrderStatus;
use Box\Mod\Product\Entity\Product;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\DriverManagerFactory;
use RedBeanPHP\Facade;

beforeEach(function (): void {
    $this->previousToolboxes = Facade::$toolboxes;
    $this->previousDatabase = Facade::$currentDB;
    Facade::$toolboxes = [];
    $this->sharedProperty = new ReflectionProperty(DriverManagerFactory::class, 'sharedConnection');
    $this->previousConnection = $this->sharedProperty->getValue();
    $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $this->sharedProperty->setValue(null, $this->connection);
    $this->di = require PATH_ROOT . '/di.php';

    // The production PDO factory also initializes MySQL session settings.
    // Use the shared native PDO for this isolated SQLite fixture.
    $this->di['pdo'] = $this->connection->getNativeConnection();
    $this->di['logger'] = new Box_Log();
    $this->di['events_manager'] = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $this->db = $this->di['db'];
    Facade::freeze(false);
    $this->em = $this->di['em'];
    $schema = (new SchemaTool($this->em))->getSchemaFromMetadata(array_map(
        fn (string $class) => $this->em->getClassMetadata($class),
        [Order::class, OrderMeta::class, OrderStatus::class],
    ));
    // MySQL permits duplicate index names across tables; SQLite does not.
    foreach ($schema->getTables() as $table) {
        foreach ($table->getIndexes() as $index) {
            if (!$index->isPrimary()) {
                $table->renameIndex($index->getName(), $table->getName() . '_' . $index->getName());
            }
        }
    }
    foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
        $this->connection->executeStatement($sql);
    }
});

afterEach(function (): void {
    Facade::close();
    $this->connection->close();
    $this->sharedProperty->setValue(null, $this->previousConnection);
    Facade::$toolboxes = $this->previousToolboxes;
    Facade::$currentDB = '';
    if ($this->previousDatabase !== '') {
        Facade::selectDatabase($this->previousDatabase);
    }
});

test('database services share the entity manager transaction', function (): void {
    expect($this->di['dbal'])->toBe($this->em->getConnection());
    $order = new Order();
    $order->setStatus(Order::STATUS_PENDING_SETUP);
    $this->em->wrapInTransaction(function () use ($order): void {
        $this->em->persist($order);
        $this->em->flush();
        expect($this->db->getExistingModelById('ClientOrder', $order->getId())->status)
            ->toBe(Order::STATUS_PENDING_SETUP);
    });
});

test('the PDO factory wraps the shared connection', function (): void {
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('setAttribute')->with(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC)->once()->andReturn(true);
    $pdo->shouldReceive('setAttribute')->with(PDO::ATTR_STATEMENT_CLASS, Mockery::type('array'))->andReturn(true);
    $pdo->shouldReceive('exec')->andReturn(0);
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('getNativeConnection')->once()->andReturn($pdo);
    $this->sharedProperty->setValue(null, $connection);
    $di = require PATH_ROOT . '/di.php';
    $wrapper = $di['pdo'];
    expect($wrapper)->toBeInstanceOf(DebugBar\DataCollector\PDO\TraceablePDO::class)
        ->and((new ReflectionProperty($wrapper, 'pdo'))->getValue($wrapper))->toBe($pdo)
        ->and($di['dbal'])->toBe($connection);
});

test('legacy transactions cannot commit the outer Doctrine transaction', function (): void {
    $this->connection->executeStatement('CREATE TABLE transaction_probe (id INTEGER PRIMARY KEY)');
    expect(fn () => $this->em->wrapInTransaction(function (): void {
        $order = new Order();
        $order->setStatus(Order::STATUS_PENDING_SETUP);
        $this->em->persist($order);
        $this->em->flush();
        $this->db->transaction(function (): void {
            $this->db->exec('INSERT INTO transaction_probe VALUES (1)');
        });
        expect($this->connection->getTransactionNestingLevel())->toBe(1);

        throw new RuntimeException('Roll back checkout');
    }))->toThrow(RuntimeException::class, 'Roll back checkout');
    expect((int) $this->connection->fetchOne('SELECT COUNT(*) FROM transaction_probe'))->toBe(0);
    expect((int) $this->connection->fetchOne('SELECT COUNT(*) FROM client_order'))->toBe(0);
});

test('a failed nested legacy transaction rolls back to its savepoint', function (): void {
    $this->connection->executeStatement('CREATE TABLE transaction_probe (id INTEGER PRIMARY KEY)');
    $this->connection->transactional(function (): void {
        $this->db->exec('INSERT INTO transaction_probe VALUES (1)');
        expect(fn () => $this->db->transaction(function (): void {
            $this->db->exec('INSERT INTO transaction_probe VALUES (2)');

            throw new RuntimeException('Nested failure');
        }))->toThrow(RuntimeException::class, 'Nested failure');
        expect($this->connection->getTransactionNestingLevel())->toBe(1);
    });
    expect($this->connection->fetchFirstColumn('SELECT id FROM transaction_probe'))->toBe([1]);
});

test('free hosting checkout provisions once through the legacy service', function (string $setup, bool $fail): void {
    $legacyClient = $this->db->dispense('Client');
    $legacyClient->currency = 'USD';
    $clientId = $this->db->store($legacyClient);
    $client = new Client();
    (new ReflectionProperty($client, 'id'))->setValue($client, $clientId);
    $client->setCurrency('USD');

    $server = $this->db->dispense('ServiceHostingServer');
    $server->ip = '127.0.0.1';
    $serverId = $this->db->store($server);
    $plan = $this->db->dispense('ServiceHostingHp');
    $plan->name = 'test';
    $planId = $this->db->store($plan);
    // Create the legacy table before checkout, as on a real installation.
    $this->connection->executeStatement('CREATE TABLE service_hosting (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, service_hosting_server_id INTEGER, service_hosting_hp_id INTEGER, sld TEXT, tld TEXT, ip TEXT, reseller INTEGER, username TEXT, pass TEXT, created_at TEXT, updated_at TEXT)');
    Facade::freeze(true);

    $product = new Product();
    (new ReflectionProperty($product, 'id'))->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('hosting');
    $product->setSetup($setup);
    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('findProductById')->twice()->with(5)->andReturn($product);
    $productService->shouldReceive('reduceStock')->with(5, 1)->andReturn(true);

    $currency = new Currency('USD');
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getCurrencyRepository->find')->with(2)->andReturn($currency);
    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->with($client)->andReturn(false);

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    // API presentation is outside this regression; activation and persistence are real.
    $orderService->shouldReceive('toApiArray')->andReturn(['product_id' => 5, 'total' => 0, 'discount' => 0]);
    $adapter = Mockery::mock(Server_Manager::class);
    $adapter->shouldReceive('getPasswordLength')->andReturn(12);
    $adapter->shouldReceive('createAccount')->once()->andReturnUsing(function () use ($fail): void {
        expect($this->connection->isTransactionActive())->toBeTrue();
        if ($fail) {
            throw new RuntimeException('Provisioning unavailable');
        }
    });
    $hostingService = Mockery::mock(Box\Mod\Servicehosting\Service::class)->makePartial();
    $hostingService->shouldReceive('getServerManager')->andReturn($adapter);
    $hostingService->shouldReceive('_getAM')->andReturn([$adapter, new Server_Account()]);
    $this->di['tools'] = new FOSSBilling\Tools();
    $this->di['mod_service'] = $this->di->protect(fn (string $name) => match (strtolower($name)) {
        'order' => $orderService,
        'product' => $productService,
        'currency' => $currencyService,
        'client' => $clientService,
        'servicehosting' => $hostingService,
        default => throw new RuntimeException('Unexpected module ' . $name),
    });
    // Order\Service also resolves the module before calling action_create.
    $module = Mockery::mock(Box_Mod::class);
    $module->shouldReceive('getService')->andReturn($hostingService);
    $this->di['mod'] = $this->di->protect(fn () => $module);
    $orderService->setDi($this->di);
    $hostingService->setDi($this->di);

    $cart = new Cart();
    $cart->setId(3);
    $cart->setCurrencyId(2);
    $cartProduct = new CartProduct();
    $cartService = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $cartService->shouldReceive('getSessionCart')->andReturn($cart);
    $cartService->shouldReceive('toApiArray')->andReturn(['items' => [['id' => 1]], 'total' => 0]);
    $cartService->shouldReceive('getCartProducts')->andReturn([$cartProduct]);
    $cartService->shouldReceive('isStockAvailable')->andReturn(true);
    $cartService->shouldReceive('cartProductToApiArray')->andReturn([
        'product_id' => 5, 'form_id' => null, 'title' => 'Free hosting', 'type' => 'hosting',
        'unit' => 'service', 'period' => null, 'quantity' => 1, 'price' => 0,
        'discount_price' => 0, 'setup_price' => 0, 'discount_setup' => 0,
        'server_id' => $serverId, 'hosting_plan_id' => $planId, 'sld' => 'example',
        'tld' => '.test', 'username' => 'example',
    ]);
    $cartService->setDi($this->di);
    [$order, $invoice] = $cartService->createFromCart($client);

    expect($invoice)->toBeNull()
        ->and($order->getServiceId())->not->toBeNull()
        ->and($order->getStatus())->toBe($fail ? Order::STATUS_FAILED_SETUP : Order::STATUS_ACTIVE)
        ->and($this->connection->isTransactionActive())->toBeFalse();
    $this->em->clear();
    expect($this->em->find(Order::class, $order->getId())->getStatus())->toBe($order->getStatus());
    if (!$fail) {
        expect($this->db->getExistingModelById('ServiceHosting', $order->getServiceId())->username)->toBe('example');
    }
})->with([
    'after order' => [Box\Mod\Product\Service::SETUP_AFTER_ORDER, false],
    'after payment with zero total' => [Box\Mod\Product\Service::SETUP_AFTER_PAYMENT, false],
    'provisioning failure' => [Box\Mod\Product\Service::SETUP_AFTER_ORDER, true],
]);
