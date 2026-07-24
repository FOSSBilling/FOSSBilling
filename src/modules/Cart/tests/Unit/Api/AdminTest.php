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

test('batchExpire returns true', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Cart\Api\Admin());

    $logStub = $this->createStub('\Box_Log');

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
