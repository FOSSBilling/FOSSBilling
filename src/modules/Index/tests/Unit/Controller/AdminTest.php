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

test('di returns the dependency injection container', function (): void {
    $controller = new \Box\Mod\Index\Controller\Admin();

    $di = container();

    $controller->setDi($di);
    $result = $controller->getDi();
    expect($result)->toBe($di);
});

test('register registers controller routes', function (): void {
    $boxAppMock = Mockery::mock('\Box_App');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $boxAppMock->shouldReceive('get');
    $expectation->times(4);

    $controller = new \Box\Mod\Index\Controller\Admin();
    $controller->register($boxAppMock);
});

test('getIndex renders dashboard when admin is logged in', function (): void {
    $boxAppMock = Mockery::mock('\Box_App');
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $boxAppMock->shouldReceive('render');
    $expectation1->atLeast()->once();
    $expectation1->with('mod_index_dashboard');

    $authorizationMock = Mockery::mock('\Box_Authorization');
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $authorizationMock->shouldReceive('isAdminLoggedIn');
    $expectation2->atLeast()->once();
    $expectation2->andReturn(true);

    $di = container();
    $di['auth'] = $authorizationMock;

    $controller = new \Box\Mod\Index\Controller\Admin();
    $controller->setDi($di);
    $controller->get_index($boxAppMock);
});
