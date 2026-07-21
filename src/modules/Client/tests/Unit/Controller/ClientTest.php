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
