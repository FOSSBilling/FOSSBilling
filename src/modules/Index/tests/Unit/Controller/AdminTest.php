<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */


declare(strict_types=1);

use function Tests\Helpers\container;

test('di returns the dependency injection container', function () {
    $controller = new \Box\Mod\Index\Controller\Admin();

    $di = container();

    $controller->setDi($di);
    $result = $controller->getDi();
    expect($result)->toBe($di);
});

test('register registers controller routes', function () {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')
        ->times(4);

    $controller = new \Box\Mod\Index\Controller\Admin();
    $controller->register($boxAppMock);
});

test('getIndex renders dashboard when admin is logged in', function () {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('render')
        ->atLeast()
        ->once()
        ->with('mod_index_dashboard');

    $authorizationMock = Mockery::mock('\Box_Authorization');
    $authorizationMock->shouldReceive('isAdminLoggedIn')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $di = container();
    $di['auth'] = $authorizationMock;

    $controller = new \Box\Mod\Index\Controller\Admin();
    $controller->setDi($di);
    $controller->get_index($boxAppMock);
});
