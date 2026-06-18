<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cart\Service;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\PromoRedemption;
use Box\Mod\Product\Service as ProductService;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

function createProductEntity(?int $id = null, ?string $type = null, ?string $config = null): Product
{
    $product = new Product();
    if ($id !== null) {
        $reflection = new ReflectionProperty($product, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($product, $id);
    }
    if ($type !== null) {
        $product->setType($type);
    }
    if ($config !== null) {
        $product->setConfig($config);
    }

    return $product;
}

function createPromoEntity(int $id): Promo
{
    $promo = new Promo();
    $reflection = new ReflectionProperty($promo, 'id');
    $reflection->setAccessible(true);
    $reflection->setValue($promo, $id);

    return $promo;
}

test('gets dependency injection container', function (): void {
    $service = new Service();

    $di = container();
    $db = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();

    $di['db'] = $db;
    $service->setDi($di);
    $result = $service->getDi();
    expect($result)->toEqual($di);
});

test('gets search query', function (): void {
    $service = new Service();
    $result = $service->getSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect(strpos($result[0], 'SELECT cart.id FROM cart'))->not->toBeFalse();
});

test('getSessionCart returns existing cart', function (): void {
    $service = new Service();

    $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';

    $model = new Model_Cart();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->session_id = $session_id;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($model);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();
    $sessionMock->shouldReceive('getId')->atLeast()->once()->andReturn($session_id);

    $di = container();
    $di['db'] = $dbMock;
    $di['session'] = $sessionMock;
    $service->setDi($di);

    $result = $service->getSessionCart();

    expect($result)->toBeInstanceOf(Model_Cart::class);
    expect($result->session_id)->toEqual($session_id);
});

test('getSessionCart creates a new cart when one does not exist', function (?int $sessionGetWillReturn, string $getCurrencyByClientIdExpects, string $getDefaultExpects): void {
    $service = new Service();

    $currencyModel = Mockery::mock(Currency::class)->shouldIgnoreMissing();
    $currencyId = random_int(0, 1000);
    $currencyModel->shouldReceive('getId')->andReturn($currencyId);

    $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);
    $modelCart = new Model_Cart();
    $modelCart->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($modelCart);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();
    $sessionMock->shouldReceive('getId')->atLeast()->once()->andReturn($session_id);
    $sessionMock->shouldReceive('get')->atLeast()->once()->andReturn($sessionGetWillReturn);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    if ($sessionGetWillReturn === null) {
        $currencyRepositoryMock->shouldReceive('findDefault')->atLeast()->once()->andReturn($currencyModel);
    } else {
        $currencyRepositoryMock->shouldNotReceive('findDefault');
    }

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial();
    if ($getCurrencyByClientIdExpects === 'atLeastOnce') {
        $currencyServiceMock->shouldReceive('getCurrencyByClientId')->atLeast()->once()->andReturn($currencyModel);
    } else {
        $currencyServiceMock->shouldNotReceive('getCurrencyByClientId');
    }
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['db'] = $dbMock;
    $di['session'] = $sessionMock;
    $di['mod_service'] = $di->protect(fn () => $currencyServiceMock);
    $service->setDi($di);

    $result = $service->getSessionCart();

    expect($result)->toBeInstanceOf(Model_Cart::class);
    expect($result->session_id)->toEqual($session_id);
    expect($result->currency_id)->toEqual($currencyId);
})->with([
    [100, 'atLeastOnce', 'never'],
    [null, 'never', 'atLeastOnce'],
]);

test('isStockAvailable returns false when product out of stock', function (): void {
    $product = createProductEntity();
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isStockAvailable')->once()->with($product, 6)->andReturn(false);

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);
    $result = $service->isStockAvailable($product, 6);
    expect($result)->toBeFalse();
});

test('isStockAvailable returns true when product in stock', function (): void {
    $product = createProductEntity();
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isStockAvailable')->once()->with($product, 6)->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);
    $result = $service->isStockAvailable($product, 6);
    expect($result)->toBeTrue();
});

test('isRecurrentPricing returns true', function (): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isRecurrentProductPricing')->once()->with(Mockery::type(Product::class))->andReturn(true);

    $productModelMock = createProductEntity();

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isRecurrentPricing($productModelMock);

    expect($result)->toBeTrue();
});

test('isPeriodEnabledForProduct returns false', function (): void {
    $enabled = false;
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isProductPeriodEnabled')->once()->with(Mockery::type(Product::class), 'monthly')->andReturn($enabled);

    $productModelMock = createProductEntity();

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isPeriodEnabledForProduct($productModelMock, 'monthly');

    expect($result)->toBeBool();
    expect($result)->toEqual($enabled);
});

test('isPeriodEnabledForProduct returns true', function (): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isProductPeriodEnabled')->once()->with(Mockery::type(Product::class), 'monthly')->andReturn(true);

    $productModelMock = createProductEntity();

    $di = container();
    $di['mod_service'] = $di->protect(static fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isPeriodEnabledForProduct($productModelMock, 'monthly');

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('removeProduct returns true', function (): void {
    $cartProduct = new Model_CartProduct();
    $cartProduct->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($cartProduct);
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([$cartProduct]);
    $dbMock->shouldReceive('trash')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $result = $service->removeProduct($cart, 1);
    expect($result)->toBeTrue();
});

test('removeProduct throws exception when cart product not found', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);
    $dbMock->shouldNotReceive('trash');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    expect(fn () => $service->removeProduct($cart, 1))->toThrow(FOSSBilling\Exception::class);
});

test('changeCartCurrency returns true', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    $currency = Mockery::mock(Currency::class)->shouldIgnoreMissing();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->changeCartCurrency($cart, $currency);
    expect($result)->toBeTrue();
});

test('resetCart returns true', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([new Model_CartProduct(), new Model_CartProduct()]);
    $dbMock->shouldReceive('trash')->atLeast()->once()->andReturn(null);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->resetCart($cart);
    expect($result)->toBeTrue();
});

test('removePromo returns true', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->removePromo($cart);
    expect($result)->toBeTrue();
});

test('applyPromo returns true', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([new Model_CartProduct(), new Model_CartProduct()]);

    $promo = createPromoEntity(2);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->applyPromo($cart, $promo);
    expect($result)->toBeTrue();
});

test('applyPromo returns true when already applied', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldNotReceive('store');

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldNotReceive('isEmptyCart');

    $promo = createPromoEntity(5);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->promo_id = 5;

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->applyPromo($cart, $promo);
    expect($result)->toBeTrue();
});

test('applyPromo throws exception when cart is empty', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldNotReceive('store');

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isEmptyCart')->atLeast()->once()->andReturn(true);

    $promo = createPromoEntity(2);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->applyPromo($cart, $promo))->toThrow(FOSSBilling\Exception::class);
});

test('rm returns true', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([new Model_CartProduct()]);
    $dbMock->shouldReceive('trash')->atLeast()->once()->andReturn(null);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service = new Service();
    $service->setDi($di);

    $result = $service->rm($cart);
    expect($result)->toBeTrue();
});

test('isClientAbleToUsePromo returns false when client cannot use promo', function (): void {
    $promo = createPromoEntity(1)
        ->setOncePerClient(true);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(false);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = new Service();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
    expect($result)->toBeFalse();
});

test('clientHadUsedPromo returns true', function (): void {
    $promo = createPromoEntity(1);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('clientHasActivePromoApplication')->once()->with($client, $promo)->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $reflection = new ReflectionObject($service);
    $method = $reflection->getMethod('clientHadUsedPromo');
    $result = $method->invoke($service, $client, $promo);

    expect($result)->toBeTrue();
});

test('isClientAbleToUsePromo returns true once per client', function (): void {
    $promo = createPromoEntity(1);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = new Service();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
    expect($result)->toBeTrue();
});

test('isClientAbleToUsePromo returns false when promo cannot be applied', function (): void {
    $promo = createPromoEntity(1);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('canClientUsePromo')->once()->with($client, $promo)->andReturn(false);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = new Service();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
    expect($result)->toBeFalse();
});

test('promoCanBeApplied returns expected result', function (Promo $promo, bool $expectedResult): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('promoCanBeApplied')->once()->with($promo)->andReturn($expectedResult);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->promoCanBeApplied($promo);
    expect($result)->toEqual($expectedResult);
})->with([
    [createPromoEntity(1)->setActive(false), false],
    [createPromoEntity(2)->setActive(true)->setMaxUses(5)->setUsed(5), false],
    [createPromoEntity(3)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('tomorrow')), false],
    [createPromoEntity(4)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('yesterday'))->setEndAt(new DateTime('yesterday')), false],
    [createPromoEntity(5)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('yesterday'))->setEndAt(new DateTime('tomorrow')), true],
]);

test('getCartProducts returns array of cart products', function (): void {
    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([new Model_CartProduct()]);

    $di = container();
    $di['db'] = $dbMock;
    $service = new Service();
    $service->setDi($di);

    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->getCartProducts($cart);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Model_CartProduct::class);
});

test('checkoutCart returns array with expected keys', function (): void {
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('createFromCart')->atLeast()->once()->andReturn([$order, 1, [1]]);
    $serviceMock->shouldReceive('isClientAbleToUsePromo')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('rm')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $invoice = new Model_Invoice();
    $invoice->loadBean(new Tests\Helpers\DummyBean());
    $invoice->hash = sha1('str');

    $promo = new Promo();

    $dbMock = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promo);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['request'] = new Request();
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock->setDi($di);
    $result = $serviceMock->checkoutCart($cart, $client);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('gateway_id');
    expect($result)->toHaveKey('invoice_hash');
    expect($result)->toHaveKey('order_id');
    expect($result)->toHaveKey('orders');
});

test('checkoutCart throws exception when client is not able to use promo', function (): void {
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isClientAbleToUsePromo')->atLeast()->once()->andReturn(false);

    $dbMock = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();
    $promo = new Promo();
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promo);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->checkoutCart($cart, $client))->toThrow(FOSSBilling\Exception::class);
});

test('usePromo returns null', function (): void {
    $promo = createPromoEntity(1);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('usePromo')->once()->with($promo);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->usePromo($promo);

    expect($result)->toBeNull();
});

test('createFromCart uses database transaction', function (): void {
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 2;

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);
    $currencyRepository->shouldNotReceive('findDefault');

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 99;

    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturn([$order, null, [99]]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $clientService) {
        if ($serviceName === 'currency') {
            return $currencyService;
        }
        if ($serviceName === 'client') {
            return $clientService;
        }
    });

    $serviceMock->setDi($di);
    $result = $serviceMock->createFromCart($client);

    expect($result)->toBe([$order, null, [99]]);
});

test('createFromCart with promo entity uses product promo service', function (): void {
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->id = 3;
    $cart->currency_id = 2;
    $cart->promo_id = 7;

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $promo = new Promo();
    $promo->setCode('PROMO');
    $promoIdReflection = new ReflectionProperty($promo, 'id');
    $promoIdReflection->setAccessible(true);
    $promoIdReflection->setValue($promo, 7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);
    $productService->shouldReceive('reservePromoForOrder')->once()->with($promo, Mockery::type(Model_ClientOrder::class));
    $productService->shouldReceive('createCheckoutPromoRedemptions')->once()->with(
        $promo,
        $client,
        Mockery::on(function (array $orders): bool {
            return count($orders) === 1 && $orders[0] instanceof Model_ClientOrder;
        }),
        null,
        PromoRedemption::STATUS_COMMITTED
    );

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setAccessible(true);
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup('manual');

    $cartProduct = new Model_CartProduct();
    $cartProduct->loadBean(new Tests\Helpers\DummyBean());
    $cartProduct->id = 13;

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 42;

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Model_ClientOrder::class), 'Order Created');
    $orderService->shouldReceive('toApiArray')->once()->with(Mockery::type(Model_ClientOrder::class), false, $client)->andReturn([
        'product_id' => 5,
        'total' => 0,
        'discount' => 0,
    ]);

    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $dbMock->shouldReceive('dispense')->once()->with('ClientOrder')->andReturn($order);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct)->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $productService->shouldReceive('findProductById')->twice()->with(5)->andReturn($product);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $clientService, $productService, $orderService) {
        return match ($serviceName) {
            'currency' => $currencyService,
            'client' => $clientService,
            'Product' => $productService,
            'order', 'Order' => $orderService,
            default => null,
        };
    });

    $serviceMock->setDi($di);
    $result = $serviceMock->createFromCart($client);

    expect($result)->toBe([$order, null, [42]]);
});

test('createFromCart compensates promo usage on transaction failure', function (): void {
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->id = 3;
    $cart->currency_id = 2;
    $cart->promo_id = 7;

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 9;
    $client->currency = 'USD';

    $currency = Mockery::mock(Currency::class)->makePartial();
    $currency->shouldReceive('getCode')->once()->andReturn('USD');
    $currency->shouldReceive('getConversionRate')->atLeast()->once()->andReturn(1.0);

    $currencyRepository = Mockery::mock(CurrencyRepository::class);
    $currencyRepository->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $currencyService = Mockery::mock(CurrencyService::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('isClientTaxable')->once()->with($client)->andReturn(false);

    $promo = new Promo();
    $promo->setCode('PROMO');
    $promoIdReflection = new ReflectionProperty($promo, 'id');
    $promoIdReflection->setAccessible(true);
    $promoIdReflection->setValue($promo, 7);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findPromoById')->once()->with(7)->andReturn($promo);
    $productService->shouldReceive('reservePromoForOrder')->once()->with($promo, Mockery::type(Model_ClientOrder::class));

    // Simulate Doctrine-side failure during redemption creation.
    $productService->shouldReceive('createCheckoutPromoRedemptions')
        ->andThrow(new RuntimeException('Doctrine flush failed'));

    // The compensating method must be invoked with the order ID and count.
    $productService->shouldReceive('compensateCheckoutPromoFailure')
        ->once()
        ->with($promo, [42], 1);

    $product = new Product();
    $productIdReflection = new ReflectionProperty($product, 'id');
    $productIdReflection->setAccessible(true);
    $productIdReflection->setValue($product, 5);
    $product->setStatus('enabled');
    $product->setType('service');
    $product->setSetup('manual');

    $cartProduct = new Model_CartProduct();
    $cartProduct->loadBean(new Tests\Helpers\DummyBean());
    $cartProduct->id = 13;

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 42;

    $orderService = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orderService->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Model_ClientOrder::class), 'Order Created');

    $dbMock = Mockery::mock(Box_Database::class)->makePartial();
    $dbMock->shouldReceive('transaction')->once()->with(Mockery::type(Closure::class))->andReturnUsing(fn (Closure $callback) => $callback());
    $dbMock->shouldReceive('dispense')->once()->with('ClientOrder')->andReturn($order);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->once()->andReturn($cart);
    $serviceMock->shouldReceive('toApiArray')->once()->with($cart)->andReturn([
        'items' => [['id' => 1]],
        'total' => 0,
    ]);
    $serviceMock->shouldReceive('getCartProducts')->once()->with($cart)->andReturn([$cartProduct]);
    $serviceMock->shouldReceive('cartProductToApiArray')->once()->with($cartProduct)->andReturn([
        'product_id' => 5,
        'form_id' => null,
        'title' => 'Example product',
        'type' => 'service',
        'unit' => 'service',
        'period' => '1M',
        'quantity' => 1,
        'price' => 0,
        'discount_price' => 0,
        'setup_price' => 0,
        'discount_setup' => 0,
        'notes' => null,
    ]);
    $serviceMock->shouldReceive('isStockAvailable')->once()->with($product, 1)->andReturn(true);

    $productService->shouldReceive('findProductById')->once()->with(5)->andReturn($product);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $clientService, $productService, $orderService) {
        return match ($serviceName) {
            'currency' => $currencyService,
            'client' => $clientService,
            'Product' => $productService,
            'order', 'Order' => $orderService,
            default => null,
        };
    });

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createFromCart($client))
        ->toThrow(RuntimeException::class, 'Doctrine flush failed');
});

test('usePromo throws exception when limit reached', function (): void {
    $promo = createPromoEntity(1);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('usePromo')->once()->with($promo)->andThrow(new FOSSBilling\InformationException('This promo code has reached its maximum number of uses.'));

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    expect(fn () => $service->usePromo($promo))->toThrow(FOSSBilling\InformationException::class);
});

test('findActivePromoByCode returns promo', function (): void {
    $promo = new Promo();

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findActivePromoByCode')->once()->with('CODE')->andReturn($promo);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->findActivePromoByCode('CODE');

    expect($result)->toBeInstanceOf(Promo::class);
});

test('addItem throws exception when recurring payment period param missing', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = createProductEntity(type: 'Custom');

    $data = [];

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();
    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(true);

    $productService = new ProductService();
    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->andThrow(new FOSSBilling\Exception('Period parameter not passed'));
    $di['validator'] = $validatorMock;
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Period parameter not passed');
});

test('addItem throws exception when recurring payment period is not enabled', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = createProductEntity(type: 'hosting');

    $data = ['period' => '1W'];

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('isPeriodEnabledForProduct')->atLeast()->once()->andReturn(false);

    $productService = new ProductService();
    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();
    $di['validator'] = $validatorMock;
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Selected billing period is invalid');
});

test('addItem throws exception when out of stock', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = createProductEntity(type: 'hosting');

    $data = [];

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(false);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([]);

    $productService = new ProductService();
    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });

    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, $data))
        ->toThrow(FOSSBilling\Exception::class, 'This item is currently out of stock');
});

test('addItem rejects cumulative stock overflow', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());
    $cartModel->id = 10;

    $productModel = createProductEntity(id: 7, type: 'hosting');
    $productModel->setStockControl(true);
    $productModel->setQuantityInStock(1);

    $existingCartProduct = new Model_CartProduct();
    $existingCartProduct->loadBean(new Tests\Helpers\DummyBean());
    $existingCartProduct->product_id = 7;
    $existingCartProduct->config = json_encode(['quantity' => 1]);

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();
    $productServiceMock = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productServiceMock->shouldReceive('isStockAvailable')->once()->with($productModel, 2)->andReturn(false);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->with('CartProduct', Mockery::any(), Mockery::any())->andReturn([$existingCartProduct]);
    $dbMock->shouldNotReceive('store');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productServiceMock) {
        if ($name === 'Product') {
            return $productServiceMock;
        }

        return $serviceHostingServiceMock;
    });

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, ['quantity' => 1]))
        ->toThrow(FOSSBilling\Exception::class, 'This item is currently out of stock');
});

test('addItem rejects duplicate domain register', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());
    $cartModel->id = 1;

    $productModel = createProductEntity(type: 'domain');

    // An existing cart item already holds example.com via register keys.
    $existingCartProduct = new Model_CartProduct();
    $existingCartProduct->loadBean(new Tests\Helpers\DummyBean());
    $existingCartProduct->config = json_encode(['register_sld' => 'example', 'register_tld' => '.com']);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->with('CartProduct', Mockery::any(), Mockery::any())->andReturn([$existingCartProduct]);

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $productService = new ProductService();
    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, ['register_sld' => 'example', 'register_tld' => '.com']))
        ->toThrow(FOSSBilling\InformationException::class, 'This domain is already in the cart.');
});

test('addItem rejects duplicate domain transfer', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());
    $cartModel->id = 2;

    $productModel = createProductEntity(type: 'domain');

    // An existing cart item holds example.net via transfer keys.
    $existingCartProduct = new Model_CartProduct();
    $existingCartProduct->loadBean(new Tests\Helpers\DummyBean());
    $existingCartProduct->config = json_encode(['transfer_sld' => 'example', 'transfer_tld' => '.net']);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->with('CartProduct', Mockery::any(), Mockery::any())->andReturn([$existingCartProduct]);

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $productService = new ProductService();
    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, ['transfer_sld' => 'example', 'transfer_tld' => '.net']))
        ->toThrow(FOSSBilling\InformationException::class, 'This domain is already in the cart.');
});

test('addItem rejects duplicate domain nested', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());
    $cartModel->id = 3;

    $productModel = createProductEntity(type: 'hosting');

    // An existing hosting cart item stores the domain under the nested 'domain' key.
    $existingCartProduct = new Model_CartProduct();
    $existingCartProduct->loadBean(new Tests\Helpers\DummyBean());
    $existingCartProduct->config = json_encode([
        'domain' => ['register_sld' => 'mysite', 'register_tld' => '.org'],
    ]);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->with('CartProduct', Mockery::any(), Mockery::any())->andReturn([$existingCartProduct]);

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();

    $productService = new ProductService();
    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $productService->setDi($di);
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addItem($cartModel, $productModel, [
        'domain' => ['register_sld' => 'mysite', 'register_tld' => '.org'],
    ]))->toThrow(FOSSBilling\InformationException::class, 'This domain is already in the cart.');
});

test('addItem for hosting type returns true', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = createProductEntity(type: 'hosting');

    $data = [];

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $productDomainModel = createProductEntity(type: 'domain');
    $domainProduct = ['config' => [], 'product' => $productDomainModel];

    $serviceHostingServiceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class)->shouldIgnoreMissing();
    $serviceHostingServiceMock->shouldReceive('getDomainProductFromConfig')->atLeast()->once()->andReturn($domainProduct);
    $serviceHostingServiceMock->shouldReceive('attachOrderConfig')->atLeast()->once()->andReturn([]);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')->atLeast()->once();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([]);

    $productService = new ProductService();
    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceHostingServiceMock;
    });
    $di['logger'] = new Box_Log();

    $productService->setDi($di);
    $serviceMock->setDi($di);
    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('addItem for license type returns true', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = createProductEntity(type: 'license');

    $data = [];

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceLicenseServiceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class);
    $serviceLicenseServiceMock->shouldReceive('attachOrderConfig')->atLeast()->once()->andReturn([]);
    $serviceLicenseServiceMock->shouldReceive('validateOrderData')->atLeast()->once()->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('addProduct')->atLeast()->once();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([]);

    $productService = new ProductService();
    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceLicenseServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceLicenseServiceMock;
    });
    $di['logger'] = new Box_Log();
    $di['db'] = $dbMock;

    $productService->setDi($di);
    $serviceMock->setDi($di);
    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('addItem for custom type returns true', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = createProductEntity(type: 'custom');

    $data = [];

    $eventMock = Mockery::mock(Box_EventManager::class)->shouldIgnoreMissing();
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceCustomServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $serviceCustomServiceMock->shouldReceive('validateCustomForm')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')->atLeast()->once()->andReturn(true);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([]);
    $cartProduct = new Model_CartProduct();
    $cartProduct->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($cartProduct);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $productService = new ProductService();
    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(function ($name) use ($serviceCustomServiceMock, $productService) {
        if ($name === 'Product') {
            return $productService;
        }

        return $serviceCustomServiceMock;
    });
    $di['logger'] = new Box_Log();
    $di['db'] = $dbMock;

    $productService->setDi($di);
    $serviceMock->setDi($di);
    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('toApiArray returns expected structure', function (): void {
    $cartModel = new Model_Cart();
    $cartModel->loadBean(new Tests\Helpers\DummyBean());

    $cartProductModel = new Model_CartProduct();
    $cartProductModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCartProducts')->atLeast()->once()->andReturn([$cartProductModel]);
    $cartProductApiArray = [
        'total' => 1,
        'setup_price' => 0,
        'discount' => 0,
    ];
    $serviceMock->shouldReceive('cartProductToApiArray')->atLeast()->once()->andReturn($cartProductApiArray);

    $currencyService = Mockery::mock(CurrencyService::class)->shouldIgnoreMissing();

    $dbMock = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();
    $currencyModel = Mockery::mock(Currency::class)->shouldIgnoreMissing();
    $currencyModel->shouldReceive('toApiArray')->andReturn([]);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    $currencyRepositoryMock->shouldReceive('find')->atLeast()->once()->andReturn($currencyModel);

    $currencyService->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = new Pimple\Container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn () => $currencyService);

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($cartModel);

    $expected = [
        'promocode' => null,
        'discount' => 0,
        'total' => 1,
        'items' => [$cartProductApiArray],
        'currency' => [],
        'subtotal' => 1,
    ];
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('getProductDiscount returns discount array', function (): void {
    $cartProductModel = new Model_CartProduct();
    $cartProductModel->loadBean(new Tests\Helpers\DummyBean());

    $modelCart = new Model_Cart();
    $modelCart->loadBean(new Tests\Helpers\DummyBean());
    $modelCart->promo_id = 1;

    $promoModel = new Promo();

    $discountPrice = 25;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('Cart', Mockery::any())->andReturn($modelCart);

    $di = container();
    $di['db'] = $dbMock;
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promoModel);
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')->atLeast()->once()->andReturn(0);
    $serviceMock->shouldReceive('getItemPromoDiscount')->atLeast()->once()->andReturn($discountPrice);

    $serviceMock->setDi($di);
    $setupPrice = 0;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual($discountPrice);
    $discountSetup = 0;
    expect($result[1])->toEqual($discountSetup);
});

test('getProductDiscount returns zeros when no promo', function (): void {
    $cartProductModel = new Model_CartProduct();
    $cartProductModel->loadBean(new Tests\Helpers\DummyBean());

    $modelCart = new Model_Cart();
    $modelCart->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('Cart', Mockery::any())->andReturn($modelCart);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')->atLeast()->once()->andReturn(0);

    $serviceMock->setDi($di);
    $setupPrice = 0;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual(0);
    expect($result[1])->toEqual(0);
});

test('getProductDiscount returns free setup discount', function (): void {
    $cartProductModel = new Model_CartProduct();
    $cartProductModel->loadBean(new Tests\Helpers\DummyBean());

    $modelCart = new Model_Cart();
    $modelCart->loadBean(new Tests\Helpers\DummyBean());
    $modelCart->promo_id = 1;

    $promoModel = new Promo();
    $promoModel->setFreeSetup(true);

    $discountPrice = 25;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('Cart', Mockery::any())->andReturn($modelCart);
    $di = container();
    $di['db'] = $dbMock;
    $productService = Mockery::mock(ProductService::class)->shouldIgnoreMissing();
    $productService->shouldReceive('findPromoById')->once()->with(1)->andReturn($promoModel);
    $di['mod_service'] = $di->protect(fn () => $productService);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')->atLeast()->once()->andReturn(0);
    $serviceMock->shouldReceive('getItemPromoDiscount')->atLeast()->once()->andReturn($discountPrice);

    $serviceMock->setDi($di);
    $setupPrice = 25;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual($discountPrice);
    $discountSetup = $setupPrice;
    expect($result[1])->toEqual($discountSetup);
});

test('isPromoAvailableForClientGroup returns expected result', function (Promo $promo, ?Model_Client $client, bool $expectedResult): void {
    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('isPromoAvailableForClientGroup')->once()->with($promo)->andReturn($expectedResult);

    $di = container();
    $di['loggedin_client'] = $client;
    $di['mod_service'] = $di->protect(fn () => $productService);
    $service = new Service();
    $service->setDi($di);

    $result = $service->isPromoAvailableForClientGroup($promo);

    expect($result)->toEqual($expectedResult);
})->with([
    [createPromoEntity(1)->setClientGroups(json_encode([])), (function (): Model_Client {
        $c = new Model_Client();
        $c->loadBean(new Tests\Helpers\DummyBean());

        return $c;
    })(), true],
    [createPromoEntity(2)->setClientGroups(json_encode([1, 2])), (function (): Model_Client {
        $c = new Model_Client();
        $c->loadBean(new Tests\Helpers\DummyBean());
        $c->client_group_id = null;

        return $c;
    })(), false],
    [createPromoEntity(3)->setClientGroups(json_encode([1, 2])), (function (): Model_Client {
        $c = new Model_Client();
        $c->loadBean(new Tests\Helpers\DummyBean());
        $c->client_group_id = 3;

        return $c;
    })(), false],
    [createPromoEntity(4)->setClientGroups(json_encode([1, 2])), (function (): Model_Client {
        $c = new Model_Client();
        $c->loadBean(new Tests\Helpers\DummyBean());
        $c->client_group_id = 2;

        return $c;
    })(), true],
    [createPromoEntity(5)->setClientGroups(json_encode([])), null, true],
    [createPromoEntity(6)->setClientGroups(json_encode([1, 2])), null, false],
]);
