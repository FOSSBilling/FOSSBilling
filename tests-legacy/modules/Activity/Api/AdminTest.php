<?php

namespace Box\Tests\Mod\Activity\Api;

class AdminTest extends \BBTestCase
{
    public function testLogGetList(): void
    {
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

        $service = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list([]);
    }

    public function testLogGetListItemUserClient(): void
    {
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

        $service = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list([]);
    }

    public function testlogEmptyMParam(): void
    {
        $di = new \Pimple\Container();

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $result = $activity->log([]);
        $this->assertFalse($result, 'Empty array key m');
    }

    public function testlogEmail(): void
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->onlyMethods(['logEmail'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('logEmail')
            ->willReturn(true);

        $di = new \Pimple\Container();

        $adminApi = new \Box\Mod\Activity\Api\Admin();
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $result = $adminApi->log_email(['subject' => 'Proper subject']);

        $this->assertTrue($result, 'Log_email did not returned true');
    }

    public function testlogEmailWithoutSubject(): void
    {
        $activity = new \Box\Mod\Activity\Api\Admin();
        $result = $activity->log_email([]);
        $this->assertFalse($result);
    }

    public function testlogDelete(): void
    {
        $di = new \Pimple\Container();

        $databaseMock = $this->getMockBuilder('Box_Database')->getMock();
        $databaseMock->expects($this->atLeastOnce())->
            method('getExistingModelById')->
            willReturn(new \Model_ActivitySystem());

        $databaseMock->expects($this->atLeastOnce())->
            method('trash');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->onlyMethods(['hasPermission'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->willReturn(true);

        $di['db'] = $databaseMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);

        $result = $activity->log_delete(['id' => 1]);
        $this->assertTrue($result);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder('\\' . \Box\Mod\Activity\Api\Admin::class)->onlyMethods(['log_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('log_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }
}
