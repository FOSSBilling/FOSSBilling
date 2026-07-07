<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Controller\Admin;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

test('register configures routes', function (): void {
    $controller = new Admin();
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')->atLeast()->once();
    $boxAppMock->shouldReceive('post')->atLeast()->once();

    $controller->register($boxAppMock);
});

test('get new forwards product and client ids from request body', function (): void {
    $controller = new Admin();
    $di = container();

    $product = ['id' => 7, 'title' => 'Shared Hosting'];
    $client = ['id' => 9, 'email' => 'client@example.com'];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('product_get')
        ->once()
        ->with(['id' => '7'])
        ->andReturn($product);
    $apiAdmin->shouldReceive('client_get')
        ->once()
        ->with(['id' => '9'])
        ->andReturn($client);
    $di['api_admin'] = $apiAdmin;

    $controller->setDi($di);

    $request = Request::create('/order/new', 'POST', [
        'product_id' => '7',
        'client_id' => '9',
    ]);

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_order_new', ['product' => $product, 'client' => $client])
        ->andReturn('rendered');

    $result = $controller->get_new($boxAppMock);
    expect($result)->toBe('rendered');
});

test('get new forwards null ids when request body is empty', function (): void {
    $controller = new Admin();
    $di = container();

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('product_get')->once()->with(['id' => null])->andReturn(null);
    $apiAdmin->shouldReceive('client_get')->once()->with(['id' => null])->andReturn(null);
    $di['api_admin'] = $apiAdmin;

    $controller->setDi($di);

    $request = Request::create('/order/new', 'POST');

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_order_new', ['product' => null, 'client' => null])
        ->andReturn('rendered');

    $result = $controller->get_new($boxAppMock);
    expect($result)->toBe('rendered');
});
