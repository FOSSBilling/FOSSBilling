<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Serviceapikey\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

beforeEach(function (): void {
    $this->service = new Service();
    $this->di = container();
});

test('isActive returns true for active order with future expires_at', function (): void {
    $order = createEntity(Order::class, ['status' => 'active', 'expires_at' => new DateTime('+1 day')]);

    $repoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $repoMock->shouldReceive('findOneBy')->once()->with(['serviceId' => 7, 'serviceType' => 'apikey'])->andReturn($order);

    $this->di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($repoMock);
    $this->service->setDi($this->di);

    $model = createEntity(Box\Mod\Serviceapikey\Entity\ServiceApiKey::class, ['id' => 7]);

    $reflection = new ReflectionMethod($this->service, 'isActive');
    expect($reflection->invoke($this->service, $model))->toBeTrue();
});

test('isActive returns false for expired order', function (): void {
    $order = createEntity(Order::class, ['status' => 'active', 'expires_at' => new DateTime('-1 hour')]);

    $repoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $repoMock->shouldReceive('findOneBy')->once()->with(['serviceId' => 7, 'serviceType' => 'apikey'])->andReturn($order);

    $this->di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($repoMock);
    $this->service->setDi($this->di);

    $model = createEntity(Box\Mod\Serviceapikey\Entity\ServiceApiKey::class, ['id' => 7]);

    $reflection = new ReflectionMethod($this->service, 'isActive');
    expect($reflection->invoke($this->service, $model))->toBeTrue();
});

test('isActive returns false for inactive order status', function (): void {
    $order = createEntity(Order::class, ['status' => 'pending_setup']);

    $repoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $repoMock->shouldReceive('findOneBy')->once()->with(['serviceId' => 9, 'serviceType' => 'apikey'])->andReturn($order);

    $this->di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($repoMock);
    $this->service->setDi($this->di);

    $model = createEntity(Box\Mod\Serviceapikey\Entity\ServiceApiKey::class, ['id' => 9]);

    $reflection = new ReflectionMethod($this->service, 'isActive');
    expect($reflection->invoke($this->service, $model))->toBeFalse();
});

test('isActive returns true for active order with null expires_at', function (): void {
    $order = createEntity(Order::class, ['status' => 'active']);

    $repoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $repoMock->shouldReceive('findOneBy')->once()->with(['serviceId' => 11, 'serviceType' => 'apikey'])->andReturn($order);

    $this->di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($repoMock);
    $this->service->setDi($this->di);

    $model = createEntity(Box\Mod\Serviceapikey\Entity\ServiceApiKey::class, ['id' => 11]);

    $reflection = new ReflectionMethod($this->service, 'isActive');
    expect($reflection->invoke($this->service, $model))->toBeTrue();
});
