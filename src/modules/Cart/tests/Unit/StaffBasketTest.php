<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Cart\Entity\CartProduct;
use Box\Mod\Cart\Event\AfterStaffOrderCreateEvent;
use Box\Mod\Cart\Event\BeforeStaffCheckoutEvent;
use Box\Mod\Cart\Repository\CartProductRepository;
use Box\Mod\Cart\Repository\CartRepository;
use Box\Mod\Cart\Service;
use Box\Mod\Client\Entity\Client;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Service as ProductService;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('staff basket keys are scoped to admin and client', function (): void {
    expect(Service::staffBasketKey(4, 9))->toBe('staff:4:9');
    expect(Service::staffBasketKey(4, 10))->not->toBe(Service::staffBasketKey(4, 9));
    expect(Service::staffBasketKey(5, 9))->not->toBe(Service::staffBasketKey(4, 9));
});

test('isStaffBasket only matches staff keys', function (): void {
    $service = new Service();

    $staff = new Cart();
    $staff->setSessionId('staff:4:9');
    expect($service->isStaffBasket($staff))->toBeTrue();

    $session = new Cart();
    $session->setSessionId('rrcpqo7tkjh14d2vmf0car64k7');
    expect($service->isStaffBasket($session))->toBeFalse();
});

test('cart pricing only trusts overrides from staff baskets', function (): void {
    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 10;

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getCartProductViewData')
        ->once()
        ->with($cartProduct, false)
        ->andReturn([
            'product_id' => 5,
            'form_id' => null,
            'type' => 'custom',
            'quantity' => 1,
            'unit' => 'service',
            'price' => 20.0,
            'setup_price' => 0.0,
            'title' => 'Custom product',
            'config' => [ProductService::PRICE_OVERRIDE_KEY => 0],
        ]);
    $productService->shouldReceive('getCartProductViewData')
        ->once()
        ->with($cartProduct, true)
        ->andReturn([
            'product_id' => 5,
            'form_id' => null,
            'type' => 'custom',
            'quantity' => 1,
            'unit' => 'service',
            'price' => 7.5,
            'setup_price' => 0.0,
            'title' => 'Custom product',
            'config' => [ProductService::PRICE_OVERRIDE_KEY => 7.5],
        ]);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getProductDiscount')->twice()->andReturn([0.0, 0.0]);
    $service->setDi($di);

    $clientCart = (new Cart())->setSessionId('client-session');
    $staffCart = (new Cart())->setSessionId('staff:4:9');

    expect($service->cartProductToApiArray($cartProduct, $clientCart)['price'])->toBe(20.0);
    expect($service->cartProductToApiArray($cartProduct, $staffCart)['price'])->toBe(7.5);
});

test('staff basket promo discounts use the overridden price', function (): void {
    $cart = (new Cart())->setSessionId('staff:4:9');
    $cartProduct = (new CartProduct())
        ->setCart($cart)
        ->setProductId(5)
        ->setConfig(json_encode([ProductService::PRICE_OVERRIDE_KEY => 10]));
    $promo = createEntity(Promo::class);
    $promo->id = 7;

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getProductDiscountById')
        ->once()
        ->with(5, $promo, [ProductService::PRICE_OVERRIDE_KEY => 10], true)
        ->andReturn(2.0);
    $productService->shouldReceive('getCartProductViewData')
        ->once()
        ->with($cartProduct, true)
        ->andReturn(['price' => 10.0, 'quantity' => 1]);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $service = new Service();
    $service->setDi($di);

    expect($service->getItemPromoDiscountShares($cartProduct, [$promo], $cart))->toBe([7 => 2.0]);
});

test('getStaffBasket returns the existing basket for the admin and client', function (): void {
    $client = createEntity(Client::class);
    $client->id = 9;

    $basket = new Cart();
    $basket->setSessionId('staff:4:9');

    $cartRepo = Mockery::mock(CartRepository::class);
    $cartRepo->shouldReceive('findBySessionId')->once()->with('staff:4:9')->andReturn($basket);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Cart::class)->andReturn($cartRepo);

    $di = container();
    $di['em'] = $emMock;

    $service = new Service();
    $service->setDi($di);

    expect($service->getStaffBasket($client, 4))->toBe($basket);
});

test('getStaffBasket creates a basket in the client currency', function (): void {
    $client = createEntity(Client::class);
    $client->id = 9;

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getId')->andReturn(2);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyByClientId')->once()->with(9)->andReturn($currency);

    $cartRepo = Mockery::mock(CartRepository::class);
    $cartRepo->shouldReceive('findBySessionId')->once()->with('staff:4:9')->andReturn(null);

    $created = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Cart::class)->andReturn($cartRepo);
    $emMock->shouldReceive('persist')->once()->with(Mockery::on(function ($entity) use (&$created): bool {
        $created[] = $entity;

        return true;
    }));
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($name) => $name === 'currency' ? $currencyService : new stdClass());

    $service = new Service();
    $service->setDi($di);

    $result = $service->getStaffBasket($client, 4);

    expect($result)->toBeInstanceOf(Cart::class);
    expect($result->getSessionId())->toBe('staff:4:9');
    expect($result->getCurrencyId())->toBe(2);
    expect($created)->toHaveCount(1);
});

test('addItem strips a forged price override from client input', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $productModel = createProductEntity(id: 5, type: 'custom');

    $eventDispatcher = new SymfonyEventDispatcher();

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $productServiceMock = Mockery::mock(ProductService::class);
    $productServiceMock->shouldReceive('getProductModuleService')->atLeast()->once()->andReturn(new stdClass());
    $productServiceMock->shouldReceive('prepareCartProductConfig')->atLeast()->once()->andReturnUsing(fn (Product $product, array $config): array => $config);

    $storedConfigs = [];
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')
        ->once()
        ->with(Mockery::type(Cart::class), Mockery::type(Product::class), Mockery::type('array'))
        ->andReturnUsing(function (Cart $cart, Product $product, array $config) use (&$storedConfigs): bool {
            $storedConfigs[] = $config;

            return true;
        });

    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(fn ($name) => $name === 'Product' ? $productServiceMock : new stdClass());
    $di['logger'] = new FOSSBilling\Logger();
    $serviceMock->setDi($di);

    expect($serviceMock->addItem($cartModel, $productModel, [ProductService::PRICE_OVERRIDE_KEY => 0.01]))->toBeTrue();
    expect($storedConfigs)->toHaveCount(1);
    expect($storedConfigs[0])->not->toHaveKey(ProductService::PRICE_OVERRIDE_KEY);
});

test('addItem stamps a staff price override on the main row only', function (): void {
    $cartModel = new Cart();
    $cartReflection = new ReflectionProperty($cartModel, 'id');
    $cartReflection->setValue($cartModel, 1);

    $parentModel = createProductEntity(id: 5, type: 'custom');
    $addonModel = createProductEntity(id: 9, type: 'custom');

    $eventDispatcher = new SymfonyEventDispatcher();

    $cartProductRepo = Mockery::mock(CartProductRepository::class);
    $cartProductRepo->shouldReceive('findByCartId')->atLeast()->once()->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(CartProduct::class)->andReturn($cartProductRepo);

    $productServiceMock = Mockery::mock(ProductService::class);
    $productServiceMock->shouldReceive('getProductModuleService')->atLeast()->once()->andReturn(new stdClass());
    $productServiceMock->shouldReceive('prepareCartProductConfig')->atLeast()->once()->andReturnUsing(fn (Product $product, array $config): array => $config);
    $productServiceMock->shouldReceive('getSelectedAddonsForCart')
        ->once()
        ->andReturn([['product' => $addonModel, 'config' => ['selected' => true, ProductService::PRICE_OVERRIDE_KEY => 0.01]]]);

    $storedConfigs = [];
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')
        ->twice()
        ->andReturnUsing(function (Cart $cart, Product $product, array $config) use (&$storedConfigs): bool {
            $storedConfigs[] = $config;

            return true;
        });

    $di = container();
    $di['em'] = $emMock;
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(fn ($name) => $name === 'Product' ? $productServiceMock : new stdClass());
    $di['logger'] = new FOSSBilling\Logger();
    $serviceMock->setDi($di);

    expect($serviceMock->addItem($cartModel, $parentModel, ['addons' => ['9' => ['selected' => true]]], 7.5))->toBeTrue();
    expect($storedConfigs)->toHaveCount(2);
    expect($storedConfigs[0][ProductService::PRICE_OVERRIDE_KEY] ?? null)->toBe(7.5);
    // A forged override smuggled in through the addon config must not
    // survive on any row but the stamped main one.
    expect($storedConfigs[1])->not->toHaveKey(ProductService::PRICE_OVERRIDE_KEY);
    expect($storedConfigs[1][Service::CART_FAMILY_KEY] ?? null)->toBe($storedConfigs[0][Service::CART_FAMILY_KEY] ?? null);
});

test('addItem rejects a negative price override', function (): void {
    $service = new Service();
    $service->setDi(container());

    expect(fn () => $service->addItem(new Cart(), createProductEntity(type: 'custom'), [], -1.0))
        ->toThrow(FOSSBilling\InformationException::class, 'Price override cannot be negative');
});

test('checkoutStaffBasket rejects baskets outside staff keying', function (): void {
    $service = new Service();
    $service->setDi(container());

    $client = createEntity(Client::class);
    $client->id = 9;

    $sessionCart = new Cart();
    $sessionCart->setSessionId('rrcpqo7tkjh14d2vmf0car64k7');

    expect(fn () => $service->checkoutStaffBasket($sessionCart, $client, 4))
        ->toThrow(FOSSBilling\Exception::class, 'Not a staff basket');
});

test('checkoutStaffBasket rejects a basket owned by another admin or client', function (): void {
    $service = new Service();
    $service->setDi(container());

    $client = createEntity(Client::class);
    $client->id = 9;

    $otherBasket = new Cart();
    $otherBasket->setSessionId('staff:4:10');

    expect(fn () => $service->checkoutStaffBasket($otherBasket, $client, 4))
        ->toThrow(FOSSBilling\Exception::class, 'does not belong');
});

test('checkoutStaffBasket checks out and destroys the basket', function (): void {
    $client = createEntity(Client::class);
    $client->id = 9;

    $basket = new Cart();
    $basketReflection = new ReflectionProperty($basket, 'id');
    $basketReflection->setValue($basket, 3);
    $basket->setSessionId('staff:4:9');

    $masterOrder = createEntity(Order::class);
    $masterOrder->id = 21;

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldReceive('getStatus')->andReturn(Invoice::STATUS_UNPAID);
    $invoice->shouldReceive('getId')->andReturn(31);
    $invoice->shouldReceive('getHash')->andReturn('basket-hash');

    $productServiceMock = Mockery::mock(ProductService::class);

    $events = [];
    $eventDispatcher = new SymfonyEventDispatcher();
    foreach ([BeforeStaffCheckoutEvent::class, AfterStaffOrderCreateEvent::class] as $eventClass) {
        $eventDispatcher->addListener($eventClass, static function (object $event) use (&$events): void {
            $events[] = $event;
        });
    }

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('createOrdersFromCart')
        ->once()
        ->with($basket, $client, ['gateway_id' => 5, 'allow_disabled' => true, 'activate' => true])
        ->andReturn([$masterOrder, $invoice, [21, 22]]);
    $serviceMock->shouldReceive('rm')->once()->with($basket)->andReturn(true);

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn ($name) => $name === 'Product' ? $productServiceMock : new stdClass());
    $serviceMock->setDi($di);

    $result = $serviceMock->checkoutStaffBasket($basket, $client, 4, ['gateway_id' => 5]);

    expect($result['gateway_id'])->toBe(5);
    expect($result['invoice_id'])->toBe(31);
    expect($result['invoice_hash'])->toBe('basket-hash');
    expect($result['order_id'])->toBe(21);
    expect($result['orders'])->toBe([21, 22]);
    expect(array_map(static fn (object $event): string => $event::class, $events))->toBe([BeforeStaffCheckoutEvent::class, AfterStaffOrderCreateEvent::class]);
    expect($events[0]->cartId)->toBe(3);
    expect($events[1]->orderId)->toBe(21);
});

test('checkoutStaffBasket marks the invoice paid when requested', function (): void {
    $client = createEntity(Client::class);
    $client->id = 9;

    $basket = new Cart();
    $basketReflection = new ReflectionProperty($basket, 'id');
    $basketReflection->setValue($basket, 3);
    $basket->setSessionId('staff:4:9');

    $masterOrder = createEntity(Order::class);
    $masterOrder->id = 21;

    $invoice = Mockery::mock(Invoice::class)->makePartial();
    $invoice->shouldReceive('getStatus')->andReturn(Invoice::STATUS_UNPAID);
    $invoice->shouldReceive('getId')->andReturn(31);
    $invoice->shouldReceive('getHash')->andReturn('basket-hash');

    $productServiceMock = Mockery::mock(ProductService::class);

    $invoiceServiceMock = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceServiceMock->shouldReceive('markAsPaidByAdmin')
        ->once()
        ->with($invoice, ['gateway_id' => 5, 'transactionId' => 'txn-1'])
        ->andReturn(true);

    $eventDispatcher = new SymfonyEventDispatcher();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('createOrdersFromCart')->once()->andReturn([$masterOrder, $invoice, [21]]);
    $serviceMock->shouldReceive('rm')->once()->andReturn(true);

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn ($name) => match ($name) {
        'Product' => $productServiceMock,
        'Invoice' => $invoiceServiceMock,
        default => new stdClass(),
    });
    $serviceMock->setDi($di);

    $result = $serviceMock->checkoutStaffBasket($basket, $client, 4, [
        'gateway_id' => 5,
        'mark_invoice_paid' => true,
        'transactionId' => 'txn-1',
    ]);

    expect($result['invoice_id'])->toBe(31);
});

test('createOrdersFromCart allows disabled products for staff and strips the override', function (): void {
    $cart = createEntity(Cart::class);
    $cart->id = 3;
    $cart->currency_id = 2;

    $client = createEntity(Client::class);
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->atLeast()->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $product = new Product();
    $reflection = new ReflectionProperty($product, 'id');
    $reflection->setValue($product, 5);
    $product->setStatus('disabled');
    $product->setType('custom');
    $product->setSetup('manual');
    $product->setIsAddon(false);

    $cartProduct = createEntity(CartProduct::class);
    $cartProduct->id = 13;

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->atLeast()->once()->with(5)->andReturn($product);
    $productService->shouldReceive('reserveStockForOrder')->once()->with(Mockery::type(Order::class));

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order Created');
    // Activation is opted out in this test, so the per-order view is never built.
    $orderService->shouldReceive('toApiArray')->zeroOrMoreTimes();

    $createdOrders = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('wrapInTransaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $emMock->shouldReceive('persist')->atLeast()->once()->with(Mockery::on(function ($entity) use (&$createdOrders): bool {
        if ($entity instanceof Order) {
            $createdOrders[] = $entity;
        }

        return true;
    }));
    $emMock->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart, false, null, $client)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Disabled product',
        'type' => 'custom',
        'unit' => 'service',
        'period' => null,
        'quantity' => 1,
        'price' => 7.5,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
        Service::CART_FAMILY_KEY => 'family-a',
        ProductService::PRICE_OVERRIDE_KEY => 7.5,
    ]);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($serviceName) => match ($serviceName) {
        'currency' => $currencyService,
        'client' => $clientService,
        'Product' => $productService,
        'order', 'Order' => $orderService,
        default => null,
    });

    $serviceMock->setDi($di);
    [$masterOrder, $invoiceModel, $ids] = $serviceMock->createOrdersFromCart($cart, $client, ['allow_disabled' => true, 'activate' => false]);

    expect($createdOrders)->toHaveCount(1);
    expect((float) $createdOrders[0]->getPrice())->toBe(7.5);
    expect(json_decode((string) $createdOrders[0]->getConfig(), true))->not->toHaveKey(ProductService::PRICE_OVERRIDE_KEY);
    expect($masterOrder)->toBe($createdOrders[0]);
    expect($invoiceModel)->toBeNull();
    expect($ids)->toBeArray()->toHaveCount(1);
});
