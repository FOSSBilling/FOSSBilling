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
use Box\Mod\Email\Controller\Admin;

test('dependency injection container is properly set and retrieved', function (): void {
    $controller = new Admin();

    $di = container();
    $dbMock = Mockery::mock('Box_Database');

    $di['db'] = $dbMock;
    $controller->setDi($di);
    $result = $controller->getDi();
    expect($result)->toEqual($di);
});

test('register method registers email routes', function (): void {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')
        ->times(5);

    $controllerAdmin = new Admin();
    $controllerAdmin->register($boxAppMock);
});

test('getHistory renders email history template', function (): void {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('render')
        ->atLeast()->once()
        ->with('mod_email_history');

    $controllerAdmin = new Admin();
    $di = container();
    $di['is_admin_logged'] = true;

    $controllerAdmin->setDi($di);

    $controllerAdmin->get_history($boxAppMock);
});
