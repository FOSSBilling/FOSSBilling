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

        $service = $this->getMockBuilder('\Box\Mod\Activity\Service')->getMock();
        $service->expects($this->atLeastOnce())
                ->method('getSearchQuery')
                ->will($this->returnValue('String'));

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(function() use ($service) {return $service;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

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

        $service = $this->getMockBuilder('\Box\Mod\Activity\Service')->getMock();
        $service->expects($this->atLeastOnce())
                ->method('getSearchQuery')
                ->will($this->returnValue('String'));

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(function() use ($service) {return $service;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list(array());
    }

    public function testlog()
    {
        $databaseMock = $this->getMockBuilder('Box_Database')->getMock();
        $databaseMock->expects($this->atLeastOnce())->
            method('dispense')->
            will($this->returnValue(new \stdClass()));

        $databaseMock->expects($this->atLeastOnce())->
            method('store')->
            will($this->returnValue(1));

        $di = new \Box_Di();
        $di['db'] = $databaseMock;
        $di['request'] = new \Box_Request($di);
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $result = $activity->log(array('m' => 'hello message'));

        $this->assertEquals(true, $result, 'Log did not returned true');
    }

    public function testlogEmptyMParam()
    {
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $result = $activity->log(array());
        $this->assertEquals(false, $result, 'Empty array key m');
    }

    public function testlog_email()
    {
        $service = $this->getMockBuilder('\Box\Mod\Activity\Service')->setMethods(array('logEmail'))->getMock();
        $service->expects($this->atLeastOnce())
            ->method('logEmail')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

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
        $di = new \Box_Di();

        $databaseMock = $this->getMockBuilder('Box_Database')->getMock();
        $databaseMock->expects($this->atLeastOnce())->
            method('getExistingModelById')->
            will($this->returnValue(new \Model_ActivitySystem()));

        $databaseMock->expects($this->atLeastOnce())->
            method('trash');

        $di['db'] = $databaseMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);

        $result = $activity->log_delete(array('id' => 1));
        $this->assertEquals(true, $result);
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Activity\Api\Admin')->setMethods(array('log_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('log_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }
}
 