<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('dependency injection', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $service = new \Box\Mod\Cart\Service();

    $di = container();
    $db = Mockery::mock('Box_Database');

    $di['db'] = $db;
    $service->setDi($di);
    $result = $service->getDi();

    expect($result)->toEqual($di);
});

test('get search query', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $service = new \Box\Mod\Cart\Service();
    $result = $service->getSearchQuery([]);

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect(strpos($result[0], 'SELECT cart.id FROM cart'))->not->toBeFalse();
});

test('get session cart exists', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $service = new \Box\Mod\Cart\Service();

    $sessionId = 'rrcpqo7tkjh14d2vmf0car64k7';

    $model = new \Model_Cart();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->session_id = $sessionId;

    $dbMock = Mockery::mock('Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive('getId')
        ->atLeast()->once()
        ->andReturn($sessionId);

    $di = container();
    $di['db'] = $dbMock;
    $di['session'] = $sessionMock;
    $service->setDi($di);

    $result = $service->getSessionCart();

    expect($result)->toBeInstanceOf('Model_Cart');
    expect($result->session_id)->toEqual($sessionId);
});

dataset('getSessionCartDoesNotExistProvider', function (): array {
    return [
        [
            100,
            'atLeastOnce',
            'never',
        ],
        [
            null,
            'never',
            'atLeastOnce',
        ],
    ];
});

test('get session cart does not exist', function (?int $sessionGetWillReturn, string $getCurrencyByClientIdExpects, string $getDefaultExpects): void {
    $service = new \Box\Mod\Cart\Service();
    $service = new \Box\Mod\Cart\Service();

    $currencyModel = Mockery::mock(\Box\Mod\Currency\Entity\Currency::class);
    $currencyId = random_int(0, 1000);
    $currencyModel->shouldReceive('getId')
        ->byDefault()
        ->andReturn($currencyId);

    $sessionId = 'rrcpqo7tkjh14d2vmf0car64k7';
    $model = null;
    $dbMock = Mockery::mock('Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);
    $modelCart = new \Model_Cart();
    $modelCart->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($modelCart);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive('getId')
        ->atLeast()->once()
        ->andReturn($sessionId);
    $sessionMock->shouldReceive('get')
        ->atLeast()->once()
        ->andReturn($sessionGetWillReturn);

    $currencyRepositoryMock = Mockery::mock(\Box\Mod\Currency\Repository\CurrencyRepository::class);
    if ($sessionGetWillReturn === null) {
        $currencyRepositoryMock->shouldReceive('findDefault')
            ->atLeast()->once()
            ->andReturn($currencyModel);
    } else {
        $currencyRepositoryMock->shouldReceive('findDefault')->never();
    }

    $currencyServiceMock = Mockery::mock(\Box\Mod\Currency\Service::class)->makePartial();
    if ($getCurrencyByClientIdExpects === 'atLeastOnce') {
        $currencyServiceMock->shouldReceive('getCurrencyByClientId')
            ->atLeast()->once()
            ->andReturn($currencyModel);
    } else {
        $currencyServiceMock->shouldReceive('getCurrencyByClientId')->never();
    }
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['db'] = $dbMock;
    $di['session'] = $sessionMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $currencyServiceMock);
    $service->setDi($di);

    $result = $service->getSessionCart();

    expect($result)->toBeInstanceOf('Model_Cart');
    expect($result->session_id)->toEqual($sessionId);
    expect($result->currency_id)->toEqual($currencyId);
})->with('getSessionCartDoesNotExistProvider');

test('is stock available returns false when insufficient', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $product = new \Model_Product();
    $product->loadBean(new \Tests\Helpers\DummyBean());
    $product->stock_control = true;
    $product->quantity_in_stock = 5;

    $result = $service->isStockAvailable($product, 6);

    expect($result)->toBeFalse();
});

test('is stock available returns true when no stock control', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $product = new \Model_Product();
    $product->loadBean(new \Tests\Helpers\DummyBean());
    $product->stock_control = false;
    $product->quantity_in_stock = 5;

    $result = $service->isStockAvailable($product, 6);

    expect($result)->toBeTrue();
});

test('is recurrent pricing', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $productTable = Mockery::mock(\Model_ProductTable::class);
    $productTable->shouldReceive('getPricingArray')
        ->atLeast()->once()
        ->andReturn(['type' => \Model_ProductPayment::RECURRENT]);

    $productModelMock = Mockery::mock(\Model_Product::class)->makePartial();
    $productModelMock->shouldReceive('getTable')
        ->atLeast()->once()
        ->andReturn($productTable);

    $result = $service->isRecurrentPricing($productModelMock);

    expect($result)->toBeTrue();
});

test('is period enabled for product', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $enabled = false;
    $pricingArray = [
        'type' => \Model_ProductPayment::RECURRENT,
        'recurrent' => [
            'monthly' => [
                'enabled' => $enabled,
            ],
        ],
    ];
    $productTable = Mockery::mock(\Model_ProductTable::class);
    $productTable->shouldReceive('getPricingArray')
        ->atLeast()->once()
        ->andReturn($pricingArray);

    $productModelMock = Mockery::mock(\Model_Product::class)->makePartial();
    $productModelMock->shouldReceive('getTable')
        ->atLeast()->once()
        ->andReturn($productTable);

    $result = $service->isPeriodEnabledForProduct($productModelMock, 'monthly');

    expect($result)->toBeBool();
    expect($result)->toEqual($enabled);
});

test('is period enabled for product not recurrent', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $enabled = false;
    $pricingArray = [
        'type' => \Model_ProductPayment::FREE,
        'recurrent' => [
            'monthly' => [
                'enabled' => $enabled,
            ],
        ],
    ];
    $productTable = Mockery::mock(\Model_ProductTable::class);
    $productTable->shouldReceive('getPricingArray')
        ->atLeast()->once()
        ->andReturn($pricingArray);

    $productModelMock = Mockery::mock(\Model_Product::class)->makePartial();
    $productModelMock->shouldReceive('getTable')
        ->atLeast()->once()
        ->andReturn($productTable);

    $result = $service->isPeriodEnabledForProduct($productModelMock, 'monthly');

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('remove product', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartProduct = new \Model_CartProduct();
    $cartProduct->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($cartProduct);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$cartProduct]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $result = $service->removeProduct($cart, 1);

    expect($result)->toBeTrue();
});

test('remove product cart product not found', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);
    $dbMock->shouldReceive('trash')->never();

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $this->expectException(\FOSSBilling\Exception::class);
    $service->removeProduct($cart, 1);
});

test('change cart currency', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());

    $currency = Mockery::mock(\Box\Mod\Currency\Entity\Currency::class);
    $currency->shouldReceive('getId')->atLeast()->once()->andReturn(1);
    $currency->shouldReceive('getTitle')->atLeast()->once()->andReturn('USD');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->changeCartCurrency($cart, $currency);

    expect($result)->toBeTrue();
});

test('reset cart', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_Product(), new \Model_Product()]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->resetCart($cart);

    expect($result)->toBeTrue();
});

test('remove promo', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->removePromo($cart);

    expect($result)->toBeTrue();
});

test('apply promo', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_CartProduct(), new \Model_CartProduct()]);

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());
    $promo->id = 2;

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->applyPromo($cart, $promo);

    expect($result)->toBeTrue();
});

test('apply promo already applied', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')->never();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isEmptyCart')->never();

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());
    $promo->id = 5;

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->promo_id = 5;

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->applyPromo($cart, $promo);

    expect($result)->toBeTrue();
});

test('apply promo empty cart exception', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')->never();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isEmptyCart')
        ->atLeast()->once()
        ->andReturn(true);

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());
    $promo->id = 2;

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $serviceMock->applyPromo($cart, $promo);
});

test('rm', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_CartProduct()]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->rm($cart);

    expect($result)->toBeTrue();
});

test('is client able to use promo', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('promoCanBeApplied')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('clientHadUsedPromo')
        ->atLeast()->once()
        ->andReturn(true);

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());
    $promo->once_per_client = true;

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);

    expect($result)->toBeFalse();
});

test('client had used promo', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('promoCanBeApplied')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());
    $promo->once_per_client = true;

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);

    expect($result)->toBeFalse();
});

test('is client able to use promo once per client', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('promoCanBeApplied')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('clientHadUsedPromo')->never();

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);

    expect($result)->toBeTrue();
});

test('is client able to use promo can not be applied', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('promoCanBeApplied')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('clientHadUsedPromo')->never();

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->isClientAbleToUsePromo($client, $promo);

    expect($result)->toBeFalse();
});

dataset('promoCanBeAppliedProvider', function (): array {
    $promo1 = new \Model_Promo();
    $promo1->loadBean(new \Tests\Helpers\DummyBean());
    $promo1->active = false;

    $promo2 = new \Model_Promo();
    $promo2->loadBean(new \Tests\Helpers\DummyBean());
    $promo2->active = true;
    $promo2->maxuses = 5;
    $promo2->used = 5;

    $promo3 = new \Model_Promo();
    $promo3->loadBean(new \Tests\Helpers\DummyBean());
    $promo3->active = true;
    $promo3->maxuses = 10;
    $promo3->used = 5;
    $promo3->start_at = date('c', strtotime('tomorrow'));

    $promo4 = new \Model_Promo();
    $promo4->loadBean(new \Tests\Helpers\DummyBean());
    $promo4->active = true;
    $promo4->maxuses = 10;
    $promo4->used = 5;
    $promo4->start_at = date('c', strtotime('yesterday'));
    $promo4->end_at = date('c', strtotime('yesterday'));

    $promo5 = new \Model_Promo();
    $promo5->loadBean(new \Tests\Helpers\DummyBean());
    $promo5->active = true;
    $promo5->maxuses = 10;
    $promo5->used = 5;
    $promo5->start_at = date('c', strtotime('yesterday'));
    $promo5->end_at = date('c', strtotime('tomorrow'));

    return [
        [$promo1, false],
        [$promo2, false],
        [$promo3, false],
        [$promo4, false],
        [$promo5, true],
    ];
});

test('promo can be applied', function (\Model_Promo $promo, bool $expectedResult): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')->never();

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->promoCanBeApplied($promo);

    expect($result)->toEqual($expectedResult);
})->with('promoCanBeAppliedProvider');

test('get cart products', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_CartProduct()]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->getCartProducts($cart);

    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf('Model_CartProduct');
});

test('checkout cart', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('createFromCart')
        ->atLeast()->once()
        ->andReturn([$order, 1, [1]]);
    $serviceMock->shouldReceive('isClientAbleToUsePromo')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('rm')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')
        ->atLeast()->once()
        ->andReturn(true);

    $eventMock = Mockery::mock(\Box_EventManager::class);
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $invoice = new \Model_Invoice();
    $invoice->loadBean(new \Tests\Helpers\DummyBean());
    $invoice->hash = sha1('str');

    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($promo);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['request'] = new \Symfony\Component\HttpFoundation\Request();

    $serviceMock->setDi($di);
    $result = $serviceMock->checkoutCart($cart, $client);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('gateway_id');
    expect($result)->toHaveKey('invoice_hash');
    expect($result)->toHaveKey('order_id');
    expect($result)->toHaveKey('orders');
});

test('checkout cart client is not able to use promo exception', function (): void {
    $service = new \Box\Mod\Cart\Service();

    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->promo_id = 1;

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('isClientAbleToUsePromo')
        ->atLeast()->once()
        ->andReturn(false);

    $dbMock = Mockery::mock(\Box_Database::class);

    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new \Model_Promo());

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $serviceMock->checkoutCart($cart, $client);
});

test('use promo', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->usePromo($promo);

    expect($result)->toBeNull();
});

test('find active promo by code', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $promo = new \Model_Promo();
    $promo->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($promo);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->findActivePromoByCode('CODE');

    expect($result)->toBeInstanceOf('Model_Promo');
});

test('add item throws exception when out of stock', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = 'hosting';

    $data = [];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $serviceHostingServiceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceHostingServiceMock);

    $serviceMock->setDi($di);
    $productModel->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('This item is currently out of stock');
    $serviceMock->addItem($cartModel, $productModel, $data);
});

test('add item for hosting type product', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = 'hosting';

    $data = [];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $productDomainModel = new \Model_ProductDomain();
    $productDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $domainProduct = ['config' => [], 'product' => $productDomainModel];

    $serviceHostingServiceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceHostingServiceMock->shouldReceive('getDomainProductFromConfig')
        ->atLeast()->once()
        ->andReturn($domainProduct);
    $serviceHostingServiceMock->shouldReceive('prependOrderConfig')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceHostingServiceMock->shouldReceive('validateOrderData')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('addProduct')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceHostingServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);
    $productModel->setDi($di);
    $productDomainModel->setDi($di);

    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('add item for license type product', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = 'license';

    $data = [];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $serviceLicenseServiceMock = Mockery::mock(\Box\Mod\Servicelicense\Service::class);
    $serviceLicenseServiceMock->shouldReceive('attachOrderConfig')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceLicenseServiceMock->shouldReceive('validateOrderData')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isRecurrentPricing')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('addProduct')
        ->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($productModel);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceLicenseServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $productModel->setDi($di);

    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('add item for custom type product', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = 'custom';

    $data = [];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $serviceCustomServiceMock = Mockery::mock(\Box\Mod\Servicecustom\Service::class);
    $serviceCustomServiceMock->shouldReceive('validateCustomForm')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->andReturn(true);

    $cartProduct = new \Model_CartProduct();
    $cartProduct->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($cartProduct);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceCustomServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $productModel->setDi($di);

    $result = $serviceMock->addItem($cartModel, $productModel, $data);
    expect($result)->toBeTrue();
});

test('add item throws exception when recurring payment period param missing', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = 'Custom';

    $data = [];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $serviceHostingServiceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceHostingServiceMock);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->andThrow(new \FOSSBilling\Exception('Period parameter not passed'));
    $di['validator'] = $validatorMock;

    $serviceMock->setDi($di);
    $productModel->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Period parameter not passed');
    $serviceMock->addItem($cartModel, $productModel, $data);
});

test('add item throws exception when recurring payment period is not enabled', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = 'hosting';

    $data = ['period' => '1W'];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $serviceHostingServiceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('isRecurrentPricing')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('isPeriodEnabledForProduct')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceHostingServiceMock);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;

    $serviceMock->setDi($di);
    $productModel->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Selected billing period is invalid');
    $serviceMock->addItem($cartModel, $productModel, $data);
});

test('to api array', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartModel = new \Model_Cart();
    $cartModel->loadBean(new \Tests\Helpers\DummyBean());

    $cartProductModel = new \Model_CartProduct();
    $cartProductModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCartProducts')
        ->atLeast()->once()
        ->andReturn([$cartProductModel]);

    $cartProductApiArray = [
        'total' => 1,
        'setup_price' => 0,
        'discount' => 0,
    ];
    $serviceMock->shouldReceive('cartProductToApiArray')
        ->atLeast()->once()
        ->andReturn($cartProductApiArray);

    $currencyServiceMock = Mockery::mock(\Box\Mod\Currency\Service::class);
    $currencyModelMock = Mockery::mock(\Box\Mod\Currency\Entity\Currency::class);
    $currencyModelMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $currencyRepositoryMock = Mockery::mock(\Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($currencyModelMock);

    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $currencyServiceMock);

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

test('get product discount with promo', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartProductModel = new \Model_CartProduct();
    $cartProductModel->loadBean(new \Tests\Helpers\DummyBean());

    $modelCart = new \Model_Cart();
    $modelCart->loadBean(new \Tests\Helpers\DummyBean());
    $modelCart->promo_id = 1;

    $promoModel = new \Model_Promo();
    $promoModel->loadBean(new \Tests\Helpers\DummyBean());

    $discountPrice = 25;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($modelCart);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($promoModel);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')
        ->atLeast()->once()
        ->andReturn(0);
    $serviceMock->shouldReceive('getItemPromoDiscount')
        ->atLeast()->once()
        ->andReturn($discountPrice);

    $serviceMock->setDi($di);
    $setupPrice = 0;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual($discountPrice);
    expect($result[1])->toEqual(0);
});

test('get product discount with no promo', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartProductModel = new \Model_CartProduct();
    $cartProductModel->loadBean(new \Tests\Helpers\DummyBean());

    $modelCart = new \Model_Cart();
    $modelCart->loadBean(new \Tests\Helpers\DummyBean());

    $promoModel = new \Model_Promo();
    $promoModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($modelCart);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')
        ->atLeast()->once()
        ->andReturn(0);

    $serviceMock->setDi($di);
    $setupPrice = 0;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual(0);
    expect($result[1])->toEqual(0);
});

test('get product discount with product qty set and free setup', function (): void {
    $service = new \Box\Mod\Cart\Service();
    $cartProductModel = new \Model_CartProduct();
    $cartProductModel->loadBean(new \Tests\Helpers\DummyBean());

    $modelCart = new \Model_Cart();
    $modelCart->loadBean(new \Tests\Helpers\DummyBean());
    $modelCart->promo_id = 1;

    $promoModel = new \Model_Promo();
    $promoModel->loadBean(new \Tests\Helpers\DummyBean());
    $promoModel->freesetup = 1;

    $discountPrice = 25;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($modelCart);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($promoModel);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getRelatedItemsDiscount')
        ->atLeast()->once()
        ->andReturn(0);
    $serviceMock->shouldReceive('getItemPromoDiscount')
        ->atLeast()->once()
        ->andReturn($discountPrice);

    $serviceMock->setDi($di);
    $setupPrice = 25;
    $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

    expect($result)->toBeArray();
    expect($result[0])->toEqual($discountPrice);
    expect($result[1])->toEqual($setupPrice);
});

dataset('isPromoAvailableForClientGroupProvider', function () {
    $promo1 = new \Model_Promo();
    $promo1->loadBean(new \Tests\Helpers\DummyBean());
    $promo1->client_groups = json_encode([]);

    $client1 = new \Model_Client();
    $client1->loadBean(new \Tests\Helpers\DummyBean());

    $promo2 = new \Model_Promo();
    $promo2->loadBean(new \Tests\Helpers\DummyBean());
    $promo2->client_groups = json_encode([1, 2]);

    $client2 = new \Model_Client();
    $client2->loadBean(new \Tests\Helpers\DummyBean());
    $client2->client_group_id = null;

    $promo3 = new \Model_Promo();
    $promo3->loadBean(new \Tests\Helpers\DummyBean());
    $promo3->client_groups = json_encode([1, 2]);

    $client3 = new \Model_Client();
    $client3->loadBean(new \Tests\Helpers\DummyBean());
    $client3->client_group_id = 3;

    $promo4 = new \Model_Promo();
    $promo4->loadBean(new \Tests\Helpers\DummyBean());
    $promo4->client_groups = json_encode([1, 2]);

    $client4 = new \Model_Client();
    $client4->loadBean(new \Tests\Helpers\DummyBean());
    $client4->client_group_id = 2;

    $promo5 = new \Model_Promo();
    $promo5->loadBean(new \Tests\Helpers\DummyBean());
    $promo5->client_groups = json_encode([]);

    $promo6 = new \Model_Promo();
    $promo6->loadBean(new \Tests\Helpers\DummyBean());
    $promo6->client_groups = json_encode([1, 2]);

    return [
        'no client groups set for promo - any client valid' => [$promo1, $client1, true],
        'client groups set for promo - client not in any group' => [$promo2, $client2, false],
        'client groups set for promo - client group not included' => [$promo3, $client3, false],
        'client groups set for promo - applies to client' => [$promo4, $client4, true],
        'no client groups set - guest valid' => [$promo5, null, true],
        'client groups set - guest invalid' => [$promo6, null, false],
    ];
});

test('is promo available for client group', function (\Model_Promo $promo, ?\Model_Client $client, bool $expectedResult): void {
    $service = new \Box\Mod\Cart\Service();
    $di = container();
    $di['loggedin_client'] = $client;
    $service->setDi($di);

    $result = $service->isPromoAvailableForClientGroup($promo);

    expect($result)->toEqual($expectedResult);
})->with('isPromoAvailableForClientGroupProvider');
