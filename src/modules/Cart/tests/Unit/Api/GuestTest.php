<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cart\Api\Guest;
use Box\Mod\Cart\Service;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service as CurrencyService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Service as ProductService;

use function Tests\Helpers\container;

function getAllowedRateLimiter(): object
{
    return new class {
        public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): FOSSBilling\Security\RateLimitResult
        {
            return new FOSSBilling\Security\RateLimitResult($policy, false, 10, 9);
        }
    };
}

test('get returns array', function (): void {
    $guestApi = new Guest();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn(new Model_Cart());
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $guestApi->setService($serviceMock);

    $result = $guestApi->get();

    expect($result)->toBeArray();
});

test('reset returns true', function (): void {
    $guestApi = new Guest();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn(new Model_Cart());
    $serviceMock->shouldReceive('resetCart')->atLeast()->once()->andReturn(true);

    $guestApi->setService($serviceMock);

    $result = $guestApi->reset();

    expect($result)->toBeTrue();
});

test('setCurrency returns true', function (): void {
    $guestApi = new Guest();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn(new Model_Cart());
    $serviceMock->shouldReceive('changeCartCurrency')->atLeast()->once()->andReturn(true);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();

    $currencyMock = Mockery::mock(Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyMock);

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['validator'] = $validatorMock;
    $di['mod_service'] = $di->protect(fn () => $currencyServiceMock);
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];
    $result = $guestApi->set_currency($data);

    expect($result)->toBeTrue();
});

test('setCurrency throws exception when currency is not found', function (): void {
    $guestApi = new Guest();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldNotReceive('getSessionCart');
    $serviceMock->shouldNotReceive('changeCartCurrency');

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn(null);

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['validator'] = $validatorMock;
    $di['mod_service'] = $di->protect(fn () => $currencyServiceMock);
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'currency' => 'EUR',
    ];

    expect(fn () => $guestApi->set_currency($data))->toThrow(FOSSBilling\InformationException::class, 'Currency not found');
});

test('getCurrency returns array when currency found', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);

    $currencyMock = Mockery::mock(Currency::class)->makePartial();
    $currencyMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    $currencyRepositoryMock->shouldReceive('find')->atLeast()->once()->andReturn($currencyMock);
    $currencyRepositoryMock->shouldNotReceive('findDefault');

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $currencyServiceMock);
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $result = $guestApi->get_currency();

    expect($result)->toBeArray();
});

test('getCurrency returns default currency array when currency not found', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);

    $currencyMock = Mockery::mock(Currency::class)->makePartial();
    $currencyMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $currencyRepositoryMock = Mockery::mock(CurrencyRepository::class)->makePartial();
    $currencyRepositoryMock->shouldReceive('find')->atLeast()->once()->andReturn(null);
    $currencyRepositoryMock->shouldReceive('findDefault')->atLeast()->once()->andReturn($currencyMock);

    $currencyServiceMock = Mockery::mock(CurrencyService::class)->makePartial();
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $currencyServiceMock);
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $result = $guestApi->get_currency();

    expect($result)->toBeArray();
});

test('applyPromo returns true', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;
    $promo = new Promo();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);
    $serviceMock->shouldReceive('applyPromo')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()->andReturn($promo);
    $serviceMock->shouldReceive('promoCanBeApplied')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['rate_limiter'] = getAllowedRateLimiter();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];
    $result = $guestApi->apply_promo($data);

    expect($result)->toBeTrue();
});

test('applyPromo throws exception when promo not found', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldNotReceive('getSessionCart');
    $serviceMock->shouldNotReceive('applyPromo');
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()->andReturn(null);
    $serviceMock->shouldNotReceive('promoCanBeApplied');
    $serviceMock->shouldNotReceive('isPromoAvailableForClientGroup');

    $di = container();
    $di['rate_limiter'] = getAllowedRateLimiter();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];

    expect(fn () => $guestApi->apply_promo($data))
        ->toThrow(FOSSBilling\InformationException::class, 'The promo code has expired or does not exist');
});

test('applyPromo throws exception when promo cannot be applied', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;
    $promo = new Promo();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldNotReceive('getSessionCart');
    $serviceMock->shouldNotReceive('applyPromo');
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()->andReturn($promo);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('promoCanBeApplied')->atLeast()->once()->andReturn(false);

    $di = container();
    $di['rate_limiter'] = getAllowedRateLimiter();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];

    expect(fn () => $guestApi->apply_promo($data))
        ->toThrow(FOSSBilling\InformationException::class, 'The promo code has expired or does not exist');
});

test('applyPromo throws exception when promo cannot be applied for user', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;
    $promo = new Promo();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldNotReceive('getSessionCart');
    $serviceMock->shouldNotReceive('applyPromo');
    $serviceMock->shouldReceive('findActivePromoByCode')->atLeast()->once()->andReturn($promo);
    $serviceMock->shouldReceive('isPromoAvailableForClientGroup')->atLeast()->once()->andReturn(false);

    $di = container();
    $di['rate_limiter'] = getAllowedRateLimiter();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'promocode' => 'CODE',
    ];

    expect(fn () => $guestApi->apply_promo($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Promo code cannot be applied to your account');
});

test('removePromo returns true', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);
    $serviceMock->shouldReceive('removePromo')->atLeast()->once()->andReturn(true);

    $guestApi->setService($serviceMock);

    $result = $guestApi->remove_promo();

    expect($result)->toBeTrue();
});

test('removeItem returns true', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);
    $serviceMock->shouldReceive('removeProduct')->atLeast()->once()->andReturn(true);

    $di = container();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];

    $result = $guestApi->remove_item($data);

    expect($result)->toBeTrue();
});

test('addItem returns true when multiple is true', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;
    $product = new Product();
    $product->setIsAddon(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);
    $serviceMock->shouldReceive('addItem')->atLeast()->once()->andReturn(true);

    $productServiceMock = Mockery::mock(ProductService::class);
    $productServiceMock->shouldReceive('findOneActiveById')->once()->with(1)->andReturn($product);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productServiceMock);

    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'id' => 1,
        'multiple' => true,
    ];

    $result = $guestApi->add_item($data);

    expect($result)->toBeTrue();
});

test('addItem returns true when multiple is false', function (): void {
    $guestApi = new Guest();
    $cart = new Model_Cart();
    $cart->loadBean(new Tests\Helpers\DummyBean());
    $cart->currency_id = 1;
    $product = new Product();
    $product->setIsAddon(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSessionCart')->atLeast()->once()->andReturn($cart);
    $serviceMock->shouldReceive('addItem')->atLeast()->once()->andReturn(true);

    $apiMock = Mockery::mock(Guest::class)->makePartial();
    $apiMock->shouldReceive('reset')->atLeast()->once()->andReturn(true);

    $productServiceMock = Mockery::mock(ProductService::class);
    $productServiceMock->shouldReceive('findOneActiveById')->once()->with(1)->andReturn($product);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $productServiceMock);
    $apiMock->setDi($di);

    $apiMock->setService($serviceMock);

    $data = [
        'id' => 1,
        'multiple' => false, // should reset cart before adding
    ];

    $result = $apiMock->add_item($data);

    expect($result)->toBeTrue();
});
