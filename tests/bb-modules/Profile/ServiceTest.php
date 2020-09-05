<?php

namespace Box\Tests\Mod\Profile;

use Box\Mod\Profile\Service;

class ServiceTest extends \BBTestCase
{
    public function testDi()
    {
        $service = new Service();
        $di      = new \Box_Di();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetAdminIdentityArray()
    {
        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $service = new Service();
        $result  = $service->getAdminIdentityArray($model);
        $this->assertIsArray($result);
    }

    public function testUpdateAdmin()
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'signature' => 'new signature',
            'email'     => 'example@gmail.com',
            'name'      => 'Admin'
        );

        $service = new Service();
        $service->setDi($di);
        $result = $service->updateAdmin($model, $data);
        $this->assertTrue($result);
    }

    public function testGenerateNewApiKey()
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['tools']          = new \Box_Tools();

        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $service = new Service();
        $service->setDi($di);

        $result = $service->generateNewApiKey($model);
        $this->assertTrue($result);
    }

    public function testChangeAdminPassword()
    {
        $password = 'new_pass';
        $emMock   = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));


        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($password);

        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['password']       = $passwordMock;

        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $service = new Service();
        $service->setDi($di);

        $result = $service->changeAdminPassword($model, $password);
        $this->assertTrue($result);
    }

    public function testUpdateClient()
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array(
                                          'allow_change_email' => 1
                                      )));

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(false));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['validator']      = $validatorMock;
        $di['mod_service']    = $di->protect(function ($name) use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['mod']            = $di->protect(function () use ($modMock) {
            return $modMock;
        });

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $data            = array(
            'email'          => 'email@example.com',
            'first_name'     => 'string',
            'last_name'      => 'string',
            'gender'         => 'string',
            'birthday'       => '1981-01-01',
            'company'        => 'string',
            'company_vat'    => 'string',
            'company_number' => 'string',
            'type'           => 'string',
            'address_1'      => 'string',
            'address_2'      => 'string',
            'phone_cc'       => rand(10, 300),
            'phone'          => rand(10000, 90000),
            'country'        => 'string',
            'postcode'       => 'string',
            'city'           => 'string',
            'state'          => 'string',
            'document_type'  => 'string',
            'document_nr'    => rand(100000, 900000),
            'lang'           => 'string',
            'notes'          => 'string',
            'custom_1'       => 'string',
            'custom_2'       => 'string',
            'custom_3'       => 'string',
            'custom_4'       => 'string',
            'custom_5'       => 'string',
            'custom_6'       => 'string',
            'custom_7'       => 'string',
            'custom_8'       => 'string',
            'custom_9'       => 'string',
            'custom_10'      => 'string',
        );
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new Service();
        $service->setDi($di);
        $result = $service->updateClient($model, $data);
        $this->assertTrue($result);
    }

    public function testUpdateClientEmailChangeNotAllowedException()
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue(true));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array(
                                          'allow_change_email' => 0
                                      )));

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientServiceMock->expects($this->never())->
        method('emailAreadyRegistered')->will($this->returnValue(false));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->never())->method('isEmailValid');

        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['validator']      = $validatorMock;
        $di['mod_service']    = $di->protect(function ($name) use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['mod']            = $di->protect(function () use ($modMock) {
            return $modMock;
        });

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $data            = array(
            'email' => 'email@example.com',
        );
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new Service();
        $service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $result = $service->updateClient($model, $data);
        $this->assertTrue($result);
    }

    public function testUpdateClientEmailAlreadyRegisteredException()
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue(true));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array(
                                          'allow_change_email' => 1
                                      )));

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->
        method('emailAreadyRegistered')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['validator']      = $validatorMock;
        $di['mod_service']    = $di->protect(function ($name) use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['mod']            = $di->protect(function () use ($modMock) {
            return $modMock;
        });

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $data            = array(
            'email' => 'email@example.com',
        );
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $service = new Service();
        $service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $result = $service->updateClient($model, $data);
        $this->assertTrue($result);
    }

    public function testResetApiKey()
    {

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));

        $di           = new \Box_Di();
        $di['logger'] = new \Box_Log();
        $di['db']     = $dbMock;
        $di['tools']  = new \Box_Tools();
        $model        = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $service = new Service();
        $service->setDi($di);
        $result = $service->resetApiKey($model);
        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 32);
    }

    public function testChangeClientPassword()
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');

        $password = 'new password';

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($password);


        $di                   = new \Box_Di();
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db']             = $dbMock;
        $di['validator']      = $validatorMock;
        $di['password']       = $passwordMock;

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $service = new Service();
        $service->setDi($di);
        $result = $service->changeClientPassword($model, $password);
        $this->assertTrue($result);
    }

    public function testLogoutClient()
    {
        $sessionMock = $this->getMockBuilder("Box_Session")
            ->disableOriginalConstructor()
            ->getMock();

        $sessionMock->expects($this->atLeastOnce())
            ->method("delete");
        $cookieMock = $this->getMockBuilder('Box_Cookie')->getMock();

        $cookieMock->expects($this->any())
            ->method('set');

        $di            = new \Box_Di();
        $di['logger']  = new \Box_Log();
        $di['session'] = $sessionMock;
        $di['cookie']  = $cookieMock;

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $service = new Service();
        $service->setDi($di);
        $result = $service->logoutClient($model, 'new password');
        $this->assertTrue($result);
    }
}