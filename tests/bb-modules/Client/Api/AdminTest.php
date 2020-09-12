<?php

namespace Box\Tests\Mod\Client\Api;

class AdminTest extends \BBTestCase
{

    public function testgetDi()
    {
        $di           = new \Box_Di();
        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $getDi = $admin_Client->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_list()
    {
        $simpleResultArr = array(
            'list' => array(
                array('id' => 1),
            ),
        );
        $pagerMock       = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($simpleResultArr));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getSearchQuery');
        $serviceMock->expects($this->atLeastOnce())->
        method('toApiArray')
            ->will($this->returnValue(array()));

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di              = new \Box_Di();
        $di['pager']     = $pagerMock;
        $di['db']        = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);
        $admin_Client->setDi($di);
        $data = array();

        $result = $admin_Client->get_list($data);
        $this->assertIsArray($result);

    }

    public function test_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getPairs')->will($this->returnValue(array()));


        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $data   = array('id' => 1);
        $result = $admin_Client->get_pairs($data);
        $this->assertIsArray($result);
    }

    public function testget()
    {
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('get')->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->get(array());
        $this->assertIsArray($result);
    }

    public function testlogin()
    {
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $sessionArray = array(
            'id'    => 1,
            'email' => 'email@example.com',
            'name'  => 'John Smith',
            'role'  => 'client',
        );
        $serviceMock  = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('toSessionArray')->will($this->returnValue($sessionArray));

        $sessionMock = $this->getMockBuilder('\Box_Session')->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())->
        method('set');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });
        $di['session']     = $sessionMock;
        $di['logger']      = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $data   = array('id' => 1);
        $result = $admin_Client->login($data);
        $this->assertIsArray($result);
    }

    public function testCreate()
    {
        $data = array(
            'email'      => 'email@example.com',
            'first_name' => 'John',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())->
        method('adminCreateClient')->will($this->returnValue(1));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->create($data);

        $this->assertIsInt($result, 'create() returned: ' . $result);
    }

    public function testCreateEmailRegisteredException()
    {
        $data = array(
            'email'      => 'email@example.com',
            'first_name' => 'John',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Email is already registered.');
        $admin_Client->create($data);
    }

    public function testdelete()
    {
        $data = array('id' => 1);

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $serviceMock = $this->getMockBuilder('\Box\Client\Service')
            ->setMethods(array('remove'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('remove')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger']         = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);
        $result = $admin_Client->delete($data);
        $this->assertTrue($result);
    }

    public function testupdate()
    {
        $data = array(
            'id'             => 1,
            'first_name'     => 'John',
            'last_name'      => 'Smith',
            'aid'            => '0',
            'gender'         => 'male',
            'birthday'       => '1999-01-01',
            'company'        => 'LTD Testing',
            'company_vat'    => 'VAT0007',
            'address_1'      => 'United States',
            'address_2'      => 'Utah',
            'phone_cc'       => '+1',
            'phone'          => '555-345-345',
            'document_type'  => 'doc',
            'document_nr'    => '1',
            'notes'          => 'none',
            'country'        => 'Moon',
            'postcode'       => 'IL-11123',
            'city'           => 'Chicaco',
            'state'          => 'IL',
            'currency'       => 'USD',
            'tax_exempt'     => 'n/a',
            'created_at'     => '2012-05-10',
            'email'          => 'test@example.com',
            'group_id'       => 1,
            'status'         => 'test status',
            'company_number' => '1234',
            'type'           => '',
            'lang'           => 'en',
            'custom_1'       => '',
            'custom_2'       => '',
            'custom_3'       => '',
            'custom_4'       => '',
            'custom_5'       => '',
            'custom_6'       => '',
            'custom_7'       => '',
            'custom_8'       => '',
            'custom_9'       => '',
            'custom_10'      => '',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())->
        method('canChangeCurrency')->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });;
        $di['events_manager'] = $eventMock;
        $di['validator']      = $validatorMock;
        $di['logger']         = new \Box_Log();
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $result = $admin_Client->update($data);
        $this->assertTrue($result);
    }

    public function testupdate_EmailALreadyRegistered()
    {
        $data = array(
            'id'             => 1,
            'first_name'     => 'John',
            'last_name'      => 'Smith',
            'aid'            => '0',
            'gender'         => 'male',
            'birthday'       => '1999-01-01',
            'company'        => 'LTD Testing',
            'company_vat'    => 'VAT0007',
            'address_1'      => 'United States',
            'address_2'      => 'Utah',
            'phone_cc'       => '+1',
            'phone'          => '555-345-345',
            'document_type'  => 'doc',
            'document_nr'    => '1',
            'notes'          => 'none',
            'country'        => 'Moon',
            'postcode'       => 'IL-11123',
            'city'           => 'Chicaco',
            'state'          => 'IL',
            'currency'       => 'USD',
            'tax_exempt'     => 'n/a',
            'created_at'     => '2012-05-10',
            'email'          => 'test@example.com',
            'group_id'       => 1,
            'status'         => 'test status',
            'company_number' => '1234',
            'type'           => '',
            'lang'           => 'en',
            'custom_1'       => '',
            'custom_2'       => '',
            'custom_3'       => '',
            'custom_4'       => '',
            'custom_5'       => '',
            'custom_6'       => '',
            'custom_7'       => '',
            'custom_8'       => '',
            'custom_9'       => '',
            'custom_10'      => '',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(true));
        $serviceMock->expects($this->never())->
        method('canChangeCurrency')->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->never())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });;
        $di['events_manager'] = $eventMock;
        $di['validator']      = $validatorMock;
        $di['logger']         = new \Box_Log();
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Can not change email. It is already registered.');
        $admin_Client->update($data);
    }

    public function testUpdateIdException()
    {
        $data         = array();
        $admin_Client = new \Box\Mod\Client\Api\Admin();

        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['validator'] = new \Box_Validate();
        $admin_Client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Id required');
        $admin_Client->update($data);
    }

    public function testchange_password()
    {
        $data = array(
            'id'               => 1,
            'password'         => 'strongPass',
            'password_confirm' => 'strongPass',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger']         = new \Box_Log();
        $di['password']       = $passwordMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;


        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->change_password($data);
        $this->assertTrue($result);
    }


    public function testchange_passwordPasswordMismatch()
    {
        $data         = array(
            'id'               => 1,
            'password'         => 'strongPass',
            'password_confirm' => 'NotIdentical',
        );
        $admin_Client = new \Box\Mod\Client\Api\Admin();

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $admin_Client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Passwords do not match');
        $admin_Client->change_password($data);
    }

    public function testbalance_get_list()
    {
        $simpleResultArr = array(
            'list' => array(
                array(
                    'id'          => 1,
                    'description' => 'Testing',
                    'amount'      => '1.00',
                    'currency'    => 'USD',
                    'created_at'  => date('Y:m:d H:i:s'),
                ),
            ),
        );

        $data      = array();
        $pagerMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($simpleResultArr));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getSearchQuery');

        $model = new \Model_ClientBalance();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });
        $di['pager']       = $pagerMock;
        $di['array_get']   = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->balance_get_list($data);
        $this->assertIsArray($result);
    }

    public function testbalance_delete()
    {
        $data = array(
            'id' => 1,
        );

        $model = new \Model_ClientBalance();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->balance_delete($data);
        $this->assertTrue($result);
    }

    public function testbalance_add_funds()
    {
        $data = array(
            'id'          => 1,
            'amount'      => '1.00',
            'description' => 'testDescription',
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('addFunds');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->balance_add_funds($data);
        $this->assertTrue($result);
    }

    public function testbatch_expire_password_reminders()
    {
        $expiredArr = array(
            new \Model_ClientPasswordReset(),
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getExpiredPasswordReminders')->will($this->returnValue($expiredArr));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });
        $di['logger']      = new \Box_Log();

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->batch_expire_password_reminders();
        $this->assertTrue($result);
    }

    public function testlogin_history_get_list()
    {
        $data = array();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getHistorySearchQuery')->will($this->returnValue(array('sql', 'params')));

        $pagerMock      = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $pagerResultSet = array(
            'list' => array(),
        );
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($pagerResultSet));

        $di              = new \Box_Di();
        $di['pager']     = $pagerMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->login_history_get_list($data);
        $this->assertIsArray($result);
    }

    public function testget_statuses()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('counter')->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->get_statuses(array());
        $this->assertIsArray($result);
    }

    public function testgroup_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getGroupPairs')->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($serviceMock) { return $serviceMock; });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_get_pairs(array());
        $this->assertIsArray($result);
    }

    public function testgroup_create()
    {
        $data['title'] = 'test Group';

        $newGroupId  = 1;
        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('createGroup')
            ->will($this->returnValue($newGroupId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);
        $admin_Client->setDi($di);
        $result = $admin_Client->group_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }


    public function testgroup_update()
    {
        $data['id']    = '2';
        $data['title'] = 'test Group updated';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));


        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_update($data);

        $this->assertTrue($result);
    }

    public function testgroup_delete()
    {
        $data['id'] = '2';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Client\Service')
            ->setMethods(array('deleteGroup'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteGroup')
            ->will($this->returnValue(true));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->group_delete($data);

        $this->assertTrue($result);
    }

    public function testgroup_get()
    {
        $data['id'] = '2';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($model));

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_get($data);

        $this->assertIsArray($result);
    }

    public function testlogin_history_delete()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ActivityClientHistory()));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $admin_Client = new \Box\Mod\Client\Api\Admin();

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $admin_Client->setDi($di);

        $data   = array('id' => 1);
        $result = $admin_Client->login_history_delete($data);
        $this->assertTrue($result);
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Client\Api\Admin')->setMethods(array('delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('delete')->will($this->returnValue(true));

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

    public function testBatch_delete_log()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Client\Api\Admin')->setMethods(array('login_history_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('login_history_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_log(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }


}