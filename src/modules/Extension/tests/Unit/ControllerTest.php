<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Controller\Admin;
use Box\Mod\Extension\Service;
use FOSSBilling\InformationException;
use Symfony\Component\HttpFoundation\Response;

use function Tests\Helpers\container;

test('get_settings returns a 403 response when the module settings cannot be managed', function (): void {
    $controller = new Admin();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('hasManagePermission')
        ->with('sample')
        ->once()
        ->andThrow(new InformationException('You do not have permission to perform this action', [], 403));

    $di = container();
    $di['is_admin_logged'] = true;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);

    $controller->setDi($di);

    $expectedResponse = new Response('denied', 403);

    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('errorResponse')
        ->once()
        ->with(Mockery::type(InformationException::class), 403)
        ->andReturn($expectedResponse);

    $result = $controller->get_settings($app, 'sample');

    expect($result)->toBe($expectedResponse)
        ->and($result->getStatusCode())->toBe(403);
});

test('get_settings renders the settings page for modules the staff member may manage', function (): void {
    $controller = new Admin();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('hasManagePermission')
        ->with('sample')
        ->once();

    $di = container();
    $di['is_admin_logged'] = true;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);

    $controller->setDi($di);

    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('render')
        ->once()
        ->with('mod_sample_settings')
        ->andReturn('settings-html');

    $result = $controller->get_settings($app, 'sample');

    expect($result)->toBe('settings-html');
});
