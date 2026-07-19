<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Serviceapikey\Service;
use RedBeanPHP\OODBBean;

use function Tests\Helpers\container;

function serviceApiKeyBuildModel(int $id): OODBBean
{
    $model = new Tests\Helpers\DummyBean();
    $properties = new ReflectionProperty(OODBBean::class, 'properties');
    $properties->setValue($model, ['id' => $id]);

    return $model;
}

test('isActive returns true for active order with future expires_at', function (): void {
    $service = new Service();

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = 'active';
    $order->expires_at = date('Y-m-d H:i:s', time() + 86400);
    $model = serviceApiKeyBuildModel(7);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->once()
        ->with('ClientOrder', 'service_id = :id AND service_type = "apikey"', [':id' => 7])
        ->andReturn($order);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $model))->toBeTrue();
});

test('isActive returns false for expired order', function (): void {
    $service = new Service();

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = 'active';
    $order->expires_at = date('Y-m-d H:i:s', time() - 3600);
    $model = serviceApiKeyBuildModel(7);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->once()
        ->with('ClientOrder', 'service_id = :id AND service_type = "apikey"', [':id' => 7])
        ->andReturn($order);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $model))->toBeFalse();
});

test('isActive returns false for inactive order status', function (): void {
    $service = new Service();

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_SUSPENDED;
    $model = serviceApiKeyBuildModel(9);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->once()->andReturn($order);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $model))->toBeFalse();
});

test('isActive returns true for active order with null expires_at', function (): void {
    $service = new Service();

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = 'active';
    $order->expires_at = null;
    $model = serviceApiKeyBuildModel(11);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->once()->andReturn($order);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $model))->toBeTrue();
});
