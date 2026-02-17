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

beforeEach(function (): void {
    $this->guestApi = new \Box\Mod\Cart\Api\Guest();
});

test('get cart', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn(new \Model_Cart());
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $this->guestApi->setService($serviceMock);

    $result = $this->guestApi->get();

    expect($result)->toBeArray();
});

test('reset cart', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn(new \Model_Cart());
    $serviceMock->shouldReceive('resetCart')->atLeast()->once()
        ->andReturn(true);

    $this->guestApi->setService($serviceMock);

    $result = $this->guestApi->reset();

    expect($result)->toBeTrue();
});

test('set currency', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn(new \Model_Cart());
    $serviceMock->shouldReceive('changeCartCurrency')->atLeast()->once()
        ->andReturn(true);

    $currencyStub = $this->createStub('\\' . \Box\Mod\Currency\Entity\Currency::class);

    $currencyRepositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock
    ->shouldReceive('findOneByCode')
    ->atLeast()->once()
    ->andReturn($currencyStub);

    $currencyServiceMock = Mockery::mock('\\' . \Box\Mod\Currency\Service::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $this->guestApi->set_currency($data);

    expect($result)->toBeTrue();
});

test('set currency throws not found exception when currency does not exist', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')
        ->andReturn(new \Model_Cart());
    $serviceMock->shouldReceive('changeCartCurrency')
        ->andReturn(true);

    $currencyRepositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock
    ->shouldReceive('findOneByCode')
    ->atLeast()->once()
    ->andReturn(null);

    $currencyServiceMock = Mockery::mock('\\' . \Box\Mod\Currency\Service::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Currency not found');
    $this->guestApi->set_currency($data);
});

test('get currency', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);

    $currencyMock = Mockery::mock('\\' . \Box\Mod\Currency\Entity\Currency::class);
    $currencyMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $currencyRepositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock
    ->shouldReceive('find')
    ->atLeast()->once()
    ->andReturn($currencyMock);
    $currencyRepositoryMock->shouldReceive("findDefault")->never();

    $currencyServiceMock = Mockery::mock('\\' . \Box\Mod\Currency\Service::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $this->guestApi->get_currency();

    expect($result)->toBeArray();
});

test('get currency not found returns default', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);

    $currencyMock = Mockery::mock('\\' . \Box\Mod\Currency\Entity\Currency::class);
    $currencyMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $currencyRepositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock
    ->shouldReceive('find')
    ->atLeast()->once()
    ->andReturn(null);
    $currencyRepositoryMock
    ->shouldReceive('findDefault')
    ->atLeast()->once()
    ->andReturn($currencyMock);

    $currencyServiceMock = Mockery::mock('\\' . \Box\Mod\Currency\Service::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $this->guestApi->get_currency();

    expect($result)->toBeArray();
});

test('apply promo', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);
    $serviceMock->shouldReceive('applyPromo')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()
        ->andReturn(new \Model_Promo());
    $serviceMock->shouldReceive('promoCanBeApplied')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];
    $result = $this->guestApi->apply_promo($data);

    expect($result)->toBeTrue();
});

test('apply promo not found exception', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')
        ->andReturn($cart);
    $serviceMock->shouldReceive('applyPromo')
        ->andReturn(true);
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('promoCanBeApplied')
        ->andReturn(true);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')
        ->andReturn(true);

    $di = container();
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];

    $this->expectException(\FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('The promo code has expired or does not exist');
    $this->guestApi->apply_promo($data);
});

test('apply promo can not be applied', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')
        ->andReturn($cart);
    $serviceMock->shouldReceive('applyPromo')
        ->andReturn(true);
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()
        ->andReturn(new \Model_Promo());
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('promoCanBeApplied')->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];

    $this->expectException(\FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('The promo code has expired or does not exist');
    $this->guestApi->apply_promo($data);
});

test('apply promo can not be applied for user', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')
        ->andReturn($cart);
    $serviceMock->shouldReceive('applyPromo')
        ->andReturn(true);
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()
        ->andReturn(new \Model_Promo());
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];

    $this->expectException(\FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('Promo code cannot be applied to your account');
    $this->guestApi->apply_promo($data);
});

test('remove promo', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);
    $serviceMock->shouldReceive('removePromo')->atLeast()->once()
        ->andReturn(true);

    $this->guestApi->setService($serviceMock);

    $result = $this->guestApi->remove_promo();

    expect($result)->toBeTrue();
});

test('remove item', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);
    $serviceMock->shouldReceive('removeProduct')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];

    $result = $this->guestApi->remove_item($data);

    expect($result)->toBeTrue();
});

test('add item', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);
    $serviceMock->shouldReceive('addItem')->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_Product());

    $di = container();
    $di['db'] = $dbMock;

    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'id' => 1,
        'multiple' => true,
    ];

    $result = $this->guestApi->add_item($data);

    expect($result)->toBeTrue();
});

test('add item single resets cart', function (): void {
    $api = new \Box\Mod\Cart\Api\Guest();
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()
        ->andReturn($cart);
    $serviceMock->shouldReceive('addItem')->atLeast()->once()
        ->andReturn(true);

    $apiMock = Mockery::mock(\Box\Mod\Cart\Api\Guest::class)->makePartial();
    $apiMock->shouldReceive('reset')->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_Product());

    $di = container();
    $di['db'] = $dbMock;
    $apiMock->setDi($di);

    $apiMock->setService($serviceMock);

    $data = [
        'id' => 1,
        'multiple' => false, // should reset cart before adding
    ];

    $result = $apiMock->add_item($data);

    expect($result)->toBeTrue();
});
