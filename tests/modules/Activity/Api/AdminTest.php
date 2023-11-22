<?php

namespace Box\Tests\Mod\Activity\Api;

class AdminTest extends \BBTestCase {

    public function test_log_get_list()
    {
        $simpleResultArr = array(
            'list' => array(
                array(
                    'id' => 1,
                    'staff_id' => 1,
                    'staff_name' => 'Joe',
                    'staff_email' => 'example@example.com'
                ),
            ),
        );
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
                    ->method('getSimpleResultSet')
                    ->will($this->returnValue($simpleResultArr));

        $service = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
                ->method('getSearchQuery')
                ->will($this->returnValue('String'));

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn() => $service);

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list(array());
    }

    public function test_log_get_list_ItemUserClient()
    {
        $simpleResultArr = array(
            'list' => array(
                array(
                    'id' => 1,
                    'client_id' => 1,
                    'client_name' => 'Joe',
                    'client_email' => 'example@example.com'
                ),
            ),
        );
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
                    ->method('getSimpleResultSet')
                    ->will($this->returnValue($simpleResultArr));

        $service = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
                ->method('getSearchQuery')
                ->will($this->returnValue('String'));

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn() => $service);

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list(array());
    }

    public function testlogEmptyMParam()
    {
        $di = new \Pimple\Container();

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $result = $activity->log(array());
        $this->assertEquals(false, $result, 'Empty array key m');
    }

    public function testlog_email()
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->onlyMethods(array('logEmail'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('logEmail')
            ->will($this->returnValue(true));

        $di = new \Pimple\Container();

        $adminApi = new \Box\Mod\Activity\Api\Admin();
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $result = $adminApi->log_email(array('subject' => 'Proper subject'));

        $this->assertEquals(true, $result, 'Log_email did not returned true');
    }

    public function testlog_email_WithoutSubject()
    {
        $activity = new \Box\Mod\Activity\Api\Admin();
        $result = $activity->log_email(array());
        $this->assertEquals(false, $result);
    }

    public function testlog_delete()
    {
        $di = new \Pimple\Container();

        $databaseMock = $this->getMockBuilder('Box_Database')->getMock();
        $databaseMock->expects($this->atLeastOnce())->
            method('getExistingModelById')->
            will($this->returnValue(new \Model_ActivitySystem()));

        $databaseMock->expects($this->atLeastOnce())->
            method('trash');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')->onlyMethods(array('hasPermission'))->getMock();
            $serviceMock->expects($this->atLeastOnce())
                ->method('hasPermission')
                ->willReturn(true);

        $di['db'] = $databaseMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $di['mod_service'] = $di->protect(function () use ($serviceMock) {
            return $serviceMock;
        });

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);

        $result = $activity->log_delete(array('id' => 1));
        $this->assertEquals(true, $result);
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\\' . \Box\Mod\Activity\Api\Admin::class)->onlyMethods(array('log_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('log_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }
}
