<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Controller\Admin;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

test('register configures routes', function (): void {
    $controller = new Admin();
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')->atLeast()->once();

    $controller->register($boxAppMock);
});

test('get login redirects to default url when r parameter is absent', function (): void {
    $controller = new Admin();
    $di = container();

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('client_login')->once()->with(['id' => '5'])->andReturn(true);
    $di['api_admin'] = $apiAdmin;

    $controller->setDi($di);

    $capturedUrl = null;
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn(Request::create('/client/login/5'));
    $boxAppMock->shouldReceive('redirectUrl')
        ->once()
        ->andReturnUsing(function (string $url, int $status) use (&$capturedUrl): RedirectResponse {
            $capturedUrl = $url;

            return new RedirectResponse($url, $status);
        });

    $response = $controller->get_login($boxAppMock, '5');

    expect($capturedUrl)->toBe(rtrim(SYSTEM_URL, '/') . '/')
        ->and($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(301);
});

test('get login redirects to r target when r parameter is present', function (): void {
    $controller = new Admin();
    $di = container();

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('client_login')->once()->with(['id' => '5'])->andReturn(true);
    $di['api_admin'] = $apiAdmin;

    $controller->setDi($di);

    $capturedUrl = null;
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn(Request::create('/client/login/5?r=/client/manage/5'));
    $boxAppMock->shouldReceive('redirectUrl')
        ->once()
        ->andReturnUsing(function (string $url, int $status) use (&$capturedUrl): RedirectResponse {
            $capturedUrl = $url;

            return new RedirectResponse($url, $status);
        });

    $response = $controller->get_login($boxAppMock, '5');

    expect($capturedUrl)->toEndWith('/client/manage/5')
        ->and($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(301);
});
