<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('log get list with staff user', function (): void {
    $serviceStub = Mockery::mock(Box\Mod\Activity\Service::class);
    $paginatorStub = Mockery::mock(FOSSBilling\Pagination::class);
    $di = container();
    $di['pager'] = $paginatorStub;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceStub);

    $api = new Api_Handler(new Model_Admin());
    $api->setDi($di);
    $di['api_admin'] = $api;

    $activity = new Box\Mod\Activity\Api\Admin();
    $activity->setDi($di);
    $activity->setService($serviceStub);

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

    $serviceMock = Mockery::mock(Box\Mod\Activity\Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getSearchQuery');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(['String', []]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $paginatorMock->shouldReceive('getDefaultPerPage');
    $expectation2->atLeast()->once();
    $expectation2->andReturn(25);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $paginatorMock->shouldReceive('getPaginatedResultSet');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($simpleResultArr);

    $model = new Model_ActivitySystem();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $di['pager'] = $paginatorMock;
    $activity->setService($serviceMock);
    $activity->log_get_list([]);
});

test('log get list with client user', function (): void {
    $serviceStub = Mockery::mock(Box\Mod\Activity\Service::class);
    $paginatorStub = Mockery::mock(FOSSBilling\Pagination::class);
    $di = container();
    $di['pager'] = $paginatorStub;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceStub);

    $api = new Api_Handler(new Model_Admin());
    $api->setDi($di);
    $di['api_admin'] = $api;

    $activity = new Box\Mod\Activity\Api\Admin();
    $activity->setDi($di);
    $activity->setService($serviceStub);

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

    $serviceMock = Mockery::mock(Box\Mod\Activity\Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getSearchQuery');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(['String', []]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $paginatorMock->shouldReceive('getDefaultPerPage');
    $expectation2->atLeast()->once();
    $expectation2->andReturn(25);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $paginatorMock->shouldReceive('getPaginatedResultSet');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($simpleResultArr);

    $model = new Model_ActivitySystem();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $di['pager'] = $paginatorMock;
    $activity->setService($serviceMock);
    $activity->log_get_list([]);
});

test('log with empty m parameter returns false', function (): void {
    $di = container();

    $activity = new Box\Mod\Activity\Api\Admin();
    $activity->setDi($di);
    $result = $activity->log([]);

    expect($result)->toBeFalse('Empty array key m');
});

test('log email with subject', function (): void {
    $service = Mockery::mock(Box\Mod\Activity\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    /** @var Mockery\Expectation $expectation */
    $expectation = $service->shouldReceive('logEmail');
    $expectation->atLeast()->once();
    $expectation->andReturn(true);

    $di = container();

    $adminApi = new Box\Mod\Activity\Api\Admin();
    $adminApi->setService($service);
    $adminApi->setDi($di);
    $result = $adminApi->log_email(['subject' => 'Proper subject']);

    expect($result)->toBeTrue('Log_email did not returned true');
});

test('log email without subject returns false', function (): void {
    $activity = new Box\Mod\Activity\Api\Admin();
    $result = $activity->log_email([]);

    expect($result)->toBeFalse();
});
