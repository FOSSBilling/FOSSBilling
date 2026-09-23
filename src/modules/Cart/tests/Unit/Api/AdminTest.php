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

use function Tests\Helpers\container;

test('getList returns array', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $simpleResultArr = [
        'list' => [
            ['id' => 1],
        ],
    ];

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $paginatorMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($simpleResultArr);

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSearchQuery')->atLeast()->once()
        ->andReturn(['query', []]);
    $serviceMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);

    $cartRepo = Mockery::mock(Box\Mod\Cart\Repository\CartRepository::class);
    $cartRepo->shouldReceive('find')->atLeast()->once()->with(1)->andReturn($cart);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Cart::class)->andReturn($cartRepo);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['em'] = $emMock;

    $adminApi->setDi($di);

    $adminApi->setService($serviceMock);

    $data = [];
    $result = $adminApi->get_list($data);

    expect($result)->toBeArray();
});

test('get returns array', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $cart = new Cart();
    $cartReflection = new ReflectionProperty($cart, 'id');
    $cartReflection->setValue($cart, 1);

    $cartRepo = Mockery::mock(Box\Mod\Cart\Repository\CartRepository::class);
    $cartRepo->shouldReceive('find')->atLeast()->once()->with(1)->andReturn($cart);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Cart::class)->andReturn($cartRepo);

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['em'] = $emMock;
    $adminApi->setDi($di);

    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->get($data);

    expect($result)->toBeArray();
});

test('staff_basket_get returns the basket for the client', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $client = new Box\Mod\Client\Entity\Client();

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->once()->with(9)->andReturn($client);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->once()->andReturn($clientRepoMock);

    $admin = new Box\Mod\Staff\Entity\Admin();
    $adminReflection = new ReflectionProperty($admin, 'id');
    $adminReflection->setValue($admin, 4);

    $basket = new Cart();

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStaffBasket')->once()->with($client, 4)->andReturn($basket);
    $serviceMock->shouldReceive('toApiArray')->once()->with($basket, false, null, $client)->andReturn(['items' => []]);

    $di = container();
    $di['em'] = $emMock;
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(fn (string $name) => $name === 'staff' ? cartApiStaffServiceAllowingAll() : Mockery::mock()->shouldIgnoreMissing());

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect($adminApi->staff_basket_get(['client_id' => 9]))->toBe(['items' => []]);
});

test('staff_basket_add_item rejects standalone addons', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $addon = new Box\Mod\Product\Entity\Product();
    $addon->setIsAddon(true);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('findProductById')->once()->with(7)->andReturn($addon);

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStaffBasket')->andReturn(new Cart());
    $serviceMock->shouldReceive('addItem')->never();

    $di = container();
    $di['em'] = cartApiClientRepoEm(9);
    $di['loggedin_admin'] = cartApiLoggedInAdmin(4);
    $di['mod_service'] = $di->protect(fn (string $name) => match ($name) {
        'staff' => cartApiStaffServiceAllowingAll(),
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect(fn () => $adminApi->staff_basket_add_item(['client_id' => 9, 'id' => 7]))
        ->toThrow(FOSSBilling\InformationException::class, 'Addon products cannot be added separately.');
});

test('staff_basket_add_item rejects a negative price override', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $product = new Box\Mod\Product\Entity\Product();
    $product->setIsAddon(false);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('findProductById')->once()->with(5)->andReturn($product);

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStaffBasket')->andReturn(new Cart());
    $serviceMock->shouldReceive('addItem')->never();

    $di = container();
    $di['em'] = cartApiClientRepoEm(9);
    $di['loggedin_admin'] = cartApiLoggedInAdmin(4);
    $di['mod_service'] = $di->protect(fn (string $name) => match ($name) {
        'staff' => cartApiStaffServiceAllowingAll(),
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect(fn () => $adminApi->staff_basket_add_item(['client_id' => 9, 'id' => 5, 'price' => -2]))
        ->toThrow(FOSSBilling\InformationException::class, 'Price override must be a non-negative number');
});

test('staff_basket_apply_promo requires promo management permission', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('applyPromo')->never();

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('product', 'manage_promos', null, Mockery::any())
        ->andThrow(new FOSSBilling\InformationException('Denied', [], 403));

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'staff' => $staffServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect(fn () => $adminApi->staff_basket_apply_promo(['client_id' => 9, 'promocode' => 'SAVE']))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('staff_basket_checkout requires invoice permission for mark as paid', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('checkoutStaffBasket')->never();

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('invoice', null, null, Mockery::any())
        ->andThrow(new FOSSBilling\InformationException('Denied', [], 403));

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'staff' => $staffServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect(fn () => $adminApi->staff_basket_checkout(['client_id' => 9, 'mark_invoice_paid' => 1, 'gateway_id' => 5]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('batchExpire skips staff baskets', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $logStub = $this->createStub(FOSSBilling\Logger::class);

    $conn = Mockery::mock(Doctrine\DBAL\Connection::class);
    $conn->shouldReceive('fetchAllKeyValue')
        ->once()
        ->with(Mockery::on(fn (string $sql): bool => str_contains($sql, "session_id NOT LIKE 'staff:%'")), Mockery::any())
        ->andReturn([]);
    $conn->shouldNotReceive('executeStatement');

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->atLeast()->once()->andReturn($conn);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = $logStub;
    $adminApi->setDi($di);

    expect($adminApi->batch_expire([]))->toBeTrue();
});

test('staff_basket_add_item converts the override to the base currency', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $product = new Box\Mod\Product\Entity\Product();
    $product->setIsAddon(false);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('findProductById')->once()->with(5)->andReturn($product);

    $basket = new Cart();
    $basketReflection = new ReflectionProperty($basket, 'id');
    $basketReflection->setValue($basket, 3);
    $basket->setCurrencyId(2);

    $currency = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->makePartial();
    $currency->shouldReceive('getConversionRate')->andReturn(0.9);

    $currencyRepoMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepoMock->shouldReceive('find')->once()->with(2)->andReturn($currency);

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStaffBasket')->andReturn($basket);
    $serviceMock->shouldReceive('addItem')
        ->once()
        ->with($basket, $product, Mockery::type('array'), Mockery::on(fn (mixed $override): bool => is_float($override) && abs($override - 15.0 / 0.9) < 1e-9))
        ->andReturn(true);

    $di = container();
    $di['em'] = cartApiClientAndCurrencyRepoEm(9, $currencyRepoMock);
    $di['loggedin_admin'] = cartApiLoggedInAdmin(4);
    $di['mod_service'] = $di->protect(fn (string $name) => match ($name) {
        'staff' => cartApiStaffServiceAllowingAll(),
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect($adminApi->staff_basket_add_item(['client_id' => 9, 'id' => 5, 'price' => 15]))->toBeTrue();
});

test('staff_basket_add_item rejects an override without a usable currency rate', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $product = new Box\Mod\Product\Entity\Product();
    $product->setIsAddon(false);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('findProductById')->once()->with(5)->andReturn($product);

    $basket = new Cart();
    $basketReflection = new ReflectionProperty($basket, 'id');
    $basketReflection->setValue($basket, 3);
    $basket->setCurrencyId(2);

    $currencyRepoMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepoMock->shouldReceive('find')->once()->with(2)->andReturn(null);

    $serviceMock = Mockery::mock(Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStaffBasket')->andReturn($basket);
    $serviceMock->shouldReceive('addItem')->never();

    $di = container();
    $di['em'] = cartApiClientAndCurrencyRepoEm(9, $currencyRepoMock);
    $di['loggedin_admin'] = cartApiLoggedInAdmin(4);
    $di['mod_service'] = $di->protect(fn (string $name) => match ($name) {
        'staff' => cartApiStaffServiceAllowingAll(),
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect(fn () => $adminApi->staff_basket_add_item(['client_id' => 9, 'id' => 5, 'price' => 15]))
        ->toThrow(FOSSBilling\InformationException::class, 'Basket currency has no valid conversion rate');
});

function cartApiClientAndCurrencyRepoEm(int $clientId, Mockery\MockInterface $currencyRepoMock): Mockery\MockInterface
{
    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->with($clientId)->andReturn(new Box\Mod\Client\Entity\Client());

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Currency\Entity\Currency::class)->andReturn($currencyRepoMock);

    return $emMock;
}

$staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
$staffServiceMock->shouldReceive('hasPermission')->byDefault()->andReturn(true);
$staffServiceMock->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturn(true);

return $staffServiceMock;

function cartApiLoggedInAdmin(int $id): Box\Mod\Staff\Entity\Admin
{
    $admin = new Box\Mod\Staff\Entity\Admin();
    $adminReflection = new ReflectionProperty($admin, 'id');
    $adminReflection->setValue($admin, $id);

    return $admin;
}

function cartApiClientRepoEm(int $clientId): Mockery\MockInterface
{
    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->with($clientId)->andReturn(new Box\Mod\Client\Entity\Client());

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepoMock);

    return $emMock;
}

test('batchExpire returns true', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $logStub = $this->createStub(FOSSBilling\Logger::class);

    $conn = Mockery::mock(Doctrine\DBAL\Connection::class);
    $conn->shouldReceive('fetchAllKeyValue')->atLeast()->once()->andReturn([1 => date('Y-m-d H:i:s')]);
    $conn->shouldReceive('executeStatement')->atLeast()->once()->andReturn(1);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->atLeast()->once()->andReturn($conn);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = $logStub;
    $adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->batch_expire($data);

    expect($result)->toBeTrue();
});
