<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Controller\Client;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

test('password reset confirmation passes the validated hash to the template', function (): void {
    $hash = 'password';

    $service = Mockery::mock(Box\Mod\Client\Service::class);
    $service->shouldReceive('password_reset_valid')
        ->once()
        ->with(['hash' => $hash])
        ->andReturn(1);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $service]));

    $controller = new Client();
    $controller->setDi($di);

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('render')
        ->once()
        ->with('mod_client_set_new_password', ['hash' => $hash])
        ->andReturn('rendered');

    expect($controller->get_reset_password_confirm($boxAppMock, $hash))->toBe('rendered');
});

test('unusable password reset links return clients to recovery', function (bool $missing): void {
    $service = Mockery::mock(Box\Mod\Client\Service::class);
    $expectation = $service->shouldReceive('password_reset_valid')->once()->with(['hash' => 'expired']);
    if ($missing) {
        $expectation->andThrow(new FOSSBilling\InformationException('The link has expired or you have already reset your password.'));
    } else {
        $expectation->andReturn(false);
    }
    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $service]));
    $controller = new Client();
    $controller->setDi($di);
    $response = new Symfony\Component\HttpFoundation\RedirectResponse('/password-reset?expired=1');
    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('redirect')->once()->with('password-reset?expired=1')->andReturn($response);

    expect($controller->get_reset_password_confirm($app, 'expired'))->toBe($response);
})->with(['missing or used' => [true], 'expired' => [false]]);
