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

beforeEach(function (): void {
    $this->serviceStub = Mockery::mock(\Box\Mod\Activity\Service::class);

    $this->paginatorStub = Mockery::mock(\FOSSBilling\Pagination::class);

    $this->di = container();
    $this->di['pager'] = $this->paginatorStub;
    $this->di['mod_service'] = $this->di->protect(fn (): \Mockery\MockInterface => $this->serviceStub);

    $this->api = new \Api_Handler(new \Model_Admin());
    $this->api->setDi($this->di);
    $this->di['api_admin'] = $this->api;

    $this->activity = new \Box\Mod\Activity\Api\Admin();
    $this->activity->setDi($this->di);
    $this->activity->setService($this->serviceStub);
});

test('log get list with staff user', function (): void {
    $simpleResultArr = [
        'list' => [
            [
                'id' => 1,
                'staff_id' => 1,
                'staff_name' => 'Joe',
                'staff_email' => 'example@example.com',
            ],
        ],
    ];

    $serviceMock = Mockery::mock(\Box\Mod\Activity\Service::class);
    $serviceMock->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['String', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn($simpleResultArr);

    $model = new \Model_ActivitySystem();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->di['pager'] = $paginatorMock;
    $this->activity->setService($serviceMock);
    $this->activity->log_get_list([]);
});

test('log get list with client user', function (): void {
    $simpleResultArr = [
        'list' => [
            [
                'id' => 1,
                'client_id' => 1,
                'client_name' => 'Joe',
                'client_email' => 'example@example.com',
            ],
        ],
    ];

    $serviceMock = Mockery::mock(\Box\Mod\Activity\Service::class);
    $serviceMock->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['String', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn($simpleResultArr);

    $model = new \Model_ActivitySystem();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->di['pager'] = $paginatorMock;
    $this->activity->setService($serviceMock);
    $this->activity->log_get_list([]);
});

test('log with empty m parameter returns false', function (): void {
    $di = container();

    $activity = new \Box\Mod\Activity\Api\Admin();
    $activity->setDi($di);
    $result = $activity->log([]);

    expect($result)->toBeFalse('Empty array key m');
});

test('log email with subject', function (): void {
    $service = Mockery::mock(\Box\Mod\Activity\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('logEmail')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();

    $adminApi = new \Box\Mod\Activity\Api\Admin();
    $adminApi->setService($service);
    $adminApi->setDi($di);
    $result = $adminApi->log_email(['subject' => 'Proper subject']);

    expect($result)->toBeTrue('Log_email did not returned true');
});

test('log email without subject returns false', function (): void {
    $activity = new \Box\Mod\Activity\Api\Admin();
    $result = $activity->log_email([]);

    expect($result)->toBeFalse();
});
