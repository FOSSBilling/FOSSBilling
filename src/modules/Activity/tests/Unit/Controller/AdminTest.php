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

beforeEach(function () {
    $this->controller = new \Box\Mod\Activity\Controller\Admin();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $dbMock = Mockery::mock('Box_Database');

    $di['db'] = $dbMock;
    $this->controller->setDi($di);
    $result = $this->controller->getDi();
    expect($result)->toEqual($di);
});

test('fetchNavigation returns array', function () {
    $di = container();
    $link = 'activity';

    $urlMock = Mockery::mock('Box_Url');
    $urlMock->shouldReceive('adminLink')
        ->atLeast()->once()
        ->andReturn('https://fossbilling.org/index.php?_url=/' . $link);
    $di['url'] = $urlMock;

    $this->controller->setDi($di);

    $result = $this->controller->fetchNavigation();
    expect($result)->toBeArray();
});

test('register configures routes', function () {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')
        ->atLeast()->once()
        ->with('/activity', 'get_index', [], \Box\Mod\Activity\Controller\Admin::class);

    $this->controller->register($boxAppMock);
});

test('getIndex renders activity index', function () {
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('render')
        ->atLeast()->once()
        ->with('mod_activity_index');

    $di = container();
    $di['is_admin_logged'] = true;

    $this->controller->setDi($di);

    $this->controller->get_index($boxAppMock);
});
