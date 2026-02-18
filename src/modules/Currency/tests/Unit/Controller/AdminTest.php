<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Currency\Controller\Admin;

use function Tests\Helpers\container;

test('dependency injection container is properly set and retrieved', function (): void {
    $controller = new Admin();

    $di = container();
    $dbMock = Mockery::mock('Box_Database');

    $di['db'] = $dbMock;
    $controller->setDi($di);
    $result = $controller->getDi();
    expect($result)->toEqual($di);
});

test('register method registers routes correctly', function (): void {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')
        ->once()
        ->with('/currency/manage/:code', 'get_manage', ['code' => '[a-zA-Z]+'], Admin::class);

    $controllerAdmin = new Admin();
    $controllerAdmin->register($boxAppMock);
});
