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
    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'toApiArray'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn(new \Model_Cart());
    $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
        ->willReturn([]);

    $this->guestApi->setService($serviceMock);

    $result = $this->guestApi->get();

    expect($result)->toBeArray();
});

test('reset cart', function (): void {
    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'resetCart'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn(new \Model_Cart());
    $serviceMock->expects($this->atLeastOnce())->method('resetCart')
        ->willReturn(true);

    $this->guestApi->setService($serviceMock);

    $result = $this->guestApi->reset();

    expect($result)->toBeTrue();
});

test('set currency', function (): void {
    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'changeCartCurrency'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn(new \Model_Cart());
    $serviceMock->expects($this->atLeastOnce())->method('changeCartCurrency')
        ->willReturn(true);

    $currencyStub = $this->createStub('\\' . \Box\Mod\Currency\Entity\Currency::class);

    $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
        ->disableOriginalConstructor()
        ->getMock();
    $currencyRepositoryMock->expects($this->atLeastOnce())
        ->method('findOneByCode')
        ->willReturn($currencyStub);

    $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
        ->onlyMethods(['getCurrencyRepository'])->getMock();
    $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
        ->willReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $this->guestApi->set_currency($data);

    expect($result)->toBeTrue();
});

test('set currency throws not found exception when currency does not exist', function (): void {
    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'changeCartCurrency'])->getMock();
    $serviceMock->expects($this->never())->method('getSessionCart')
        ->willReturn(new \Model_Cart());
    $serviceMock->expects($this->never())->method('changeCartCurrency')
        ->willReturn(true);

    $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
        ->disableOriginalConstructor()
        ->getMock();
    $currencyRepositoryMock->expects($this->atLeastOnce())
        ->method('findOneByCode')
        ->willReturn(null);

    $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
        ->onlyMethods(['getCurrencyRepository'])->getMock();
    $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
        ->willReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);

    $currencyMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
        ->disableOriginalConstructor()
        ->getMock();
    $currencyMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->willReturn([]);

    $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
        ->disableOriginalConstructor()
        ->getMock();
    $currencyRepositoryMock->expects($this->atLeastOnce())
        ->method('find')
        ->willReturn($currencyMock);
    $currencyRepositoryMock->expects($this->never())
        ->method('findDefault');

    $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
        ->onlyMethods(['getCurrencyRepository'])->getMock();
    $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
        ->willReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $this->guestApi->get_currency();

    expect($result)->toBeArray();
});

test('get currency not found returns default', function (): void {
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);

    $currencyMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
        ->disableOriginalConstructor()
        ->getMock();
    $currencyMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->willReturn([]);

    $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
        ->disableOriginalConstructor()
        ->getMock();
    $currencyRepositoryMock->expects($this->atLeastOnce())
        ->method('find')
        ->willReturn(null);
    $currencyRepositoryMock->expects($this->atLeastOnce())
        ->method('findDefault')
        ->willReturn($currencyMock);

    $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
        ->onlyMethods(['getCurrencyRepository'])->getMock();
    $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
        ->willReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
    $this->guestApi->setDi($di);

    $this->guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $this->guestApi->get_currency();

    expect($result)->toBeArray();
});

test('apply promo', function (): void {
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->atLeastOnce())->method('applyPromo')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
        ->willReturn(new \Model_Promo());
    $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
        ->willReturn(true);

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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'])->getMock();
    $serviceMock->expects($this->never())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->never())->method('applyPromo')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
        ->willReturn(null);
    $serviceMock->expects($this->never())->method('promoCanBeApplied')
        ->willReturn(true);
    $serviceMock->expects($this->never())->method('isPromoAvailableForClientGroup')
        ->willReturn(true);

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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'])->getMock();
    $serviceMock->expects($this->never())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->never())->method('applyPromo')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
        ->willReturn(new \Model_Promo());
    $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
        ->willReturn(false);

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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'isPromoAvailableForClientGroup'])->getMock();
    $serviceMock->expects($this->never())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->never())->method('applyPromo')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
        ->willReturn(new \Model_Promo());
    $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
        ->willReturn(false);

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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'removePromo'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->atLeastOnce())->method('removePromo')
        ->willReturn(true);

    $this->guestApi->setService($serviceMock);

    $result = $this->guestApi->remove_promo();

    expect($result)->toBeTrue();
});

test('remove item', function (): void {
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'removeProduct'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->atLeastOnce())->method('removeProduct')
        ->willReturn(true);

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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'addItem'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->atLeastOnce())->method('addItem')
        ->willReturn(true);

    $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_Product());

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
    $cart = new \Model_Cart();
    $cart->loadBean(new \Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSessionCart', 'addItem'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
        ->willReturn($cart);
    $serviceMock->expects($this->atLeastOnce())->method('addItem')
        ->willReturn(true);

    $apiMock = $this->getMockBuilder(\Box\Mod\Cart\Api\Guest::class)
        ->onlyMethods(['reset'])->getMock();
    $apiMock->expects($this->atLeastOnce())->method('reset')
        ->willReturn(true);

    $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_Product());

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
