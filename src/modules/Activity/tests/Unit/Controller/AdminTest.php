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

test('getDi returns dependency injection container', function (): void {
    $controller = new \Box\Mod\Activity\Controller\Admin();
    $di = container();
    $dbMock = Mockery::mock('Box_Database');

    $di['db'] = $dbMock;
    $controller->setDi($di);
    $result = $controller->getDi();
    expect($result)->toEqual($di);
});

test('fetchNavigation returns array', function (): void {
    $controller = new \Box\Mod\Activity\Controller\Admin();
    $di = container();
    $link = 'activity';

    $urlMock = Mockery::mock('Box_Url');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $urlMock->shouldReceive('adminLink');
    $expectation->atLeast()->once();
    $expectation->andReturn('https://fossbilling.org/index.php?_url=/' . $link);
    $di['url'] = $urlMock;

    $controller->setDi($di);

    $result = $controller->fetchNavigation();
    expect($result)->toBeArray();
});

test('register configures routes', function (): void {
    $controller = new \Box\Mod\Activity\Controller\Admin();
    $boxAppMock = Mockery::mock('\Box_App');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $boxAppMock->shouldReceive('get');
    $expectation->atLeast()->once();
    $expectation->with('/activity', 'get_index', [], \Box\Mod\Activity\Controller\Admin::class);

    /** @var \Box_App $boxAppMock */
    $controller->register($boxAppMock);
});

test('getIndex renders activity index', function (): void {
    $controller = new \Box\Mod\Activity\Controller\Admin();
    $boxAppMock = Mockery::mock('\Box_App');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $boxAppMock->shouldReceive('render');
    $expectation->atLeast()->once();
    $expectation->with('mod_activity_index');

    $di = container();
    $di['is_admin_logged'] = true;

    $controller->setDi($di);

    /** @var \Box_App $boxAppMock */
    $controller->get_index($boxAppMock);
});
