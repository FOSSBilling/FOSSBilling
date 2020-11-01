<?php


namespace Box\Mod\Client\Api;


class GuestTest extends \BBTestCase {

    public function testgetDi()
    {
        $di = new \Box_Di();
        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $getDi = $client->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcreate()
    {
        $configArr = array(
            'allow_signup' => true,
            'required' => array(),
        );
        $data = array(
            'email' => 'test@email.com',
            'first_name' => 'John',
            'password' => 'testpaswword',
            'password_confirm' => 'testpaswword',

        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('clientAlreadyExists')
            ->will($this->returnValue(false));

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;

        $serviceMock->expects($this->atLeastOnce())
            ->method('guestCreateClient')
            ->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkExtraRequiredFields');
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkCustomFields');


        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');


        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $di['validator'] = $validatorMock;

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $result = $client->create($data);

        $this->assertIsInt($result);
        $this->assertEquals($model->id, $result);
    }

    public function testcreateExceptionClientExists()
    {
        $configArr = array(
            'allow_signup' => true,
        );
        $data = array(
            'email' => 'test@email.com',
            'first_name' => 'John',
            'password' => 'testpaswword',
            'password_confirm' => 'testpaswword',

        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('clientAlreadyExists')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkExtraRequiredFields');
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkCustomFields');

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $di['validator'] = $validatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Email is already registered. You may want to login instead of registering.');
        $client->create($data);
    }

    public function testCreateSignupDoNotAllowed()
    {
        $configArr = array(
            'allow_signup' => false,
        );
        $data = array(
            'email' => 'test@email.com',
            'first_name' => 'John',
            'password' => 'testpaswword',
            'password_confirm' => 'testpaswword',

        );

        $client = new \Box\Mod\Client\Api\Guest();
        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('New registrations are temporary disabled');
        $client->create($data);
    }

    public function testCreatePasswordsDoNotMatchException()
    {
        $configArr = array(
            'allow_signup' => true,
        );
        $data = array(
            'email' => 'test@email.com',
            'first_name' => 'John',
            'password' => 'testpaswword',
            'password_confirm' => 'wrongpaswword',
        );

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');

        $client = new \Box\Mod\Client\Api\Guest();
        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $di['validator'] = $validatorMock;
        $client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Passwords do not match.');
        $client->create($data);
    }

    public function testlogin()
    {
        $data = array(
            'email' => 'test@example.com',
            'password' => 'sezam',
            'remember' => true,
        );

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('authorizeClient')
            ->with($data['email'], $data['password'])
            ->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('toSessionArray')
            ->will($this->returnValue(array()));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $sessionMock = $this->getMockBuilder('\Box_Session')
            ->disableOriginalConstructor()
            ->getMock();

        $sessionMock->expects($this->atLeastOnce())
            ->method("set");

        $cookieMock = $this->getMockBuilder('\Box_Cookie')->getMock();
        $cookieMock->expects($this->atLeastOnce())
            ->method('set');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();
        $di['cookie'] = $cookieMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $results = $client->login($data);

        $this->assertIsArray($results);
    }

    public function testreset_password()
    {
        $data['email'] = 'Joghn@exmapl.com';

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());

        $modelPasswordReset = new \Model_ClientPasswordReset();
        $modelPasswordReset->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->will($this->returnValue($modelClient));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')->will($this->returnValue($modelPasswordReset));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));

        $emailServiceMock =  $serviceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->
            method('sendTemplate');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use($emailServiceMock) {return $emailServiceMock;});
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->reset_password($data);
        $this->assertTrue($result);
    }

    public function testreset_passwordEmailNotFound()
    {
        $data['email'] = 'joghn@example.eu';

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Email not found in our database');
        $client->reset_password($data);
    }

    public function testconfirm_reset()
    {
        $data = array(
            'hash' => 'hashedString'
        );

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());

        $modelPasswordReset = new \Model_ClientPasswordReset();
        $modelPasswordReset->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->will($this->returnValue($modelPasswordReset));

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->will($this->returnValue($modelClient));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $emailServiceMock =  $serviceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->
            method('sendTemplate');

        $passwordMock = $this->getMockBuilder('\Box_Password')->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt');
        
        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] =  $di->protect(function ($name) use($emailServiceMock) {return $emailServiceMock;});
        $di['password'] = $passwordMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->confirm_reset($data);
        $this->assertTrue($result);
    }

    public function testconfirm_resetResetNotFound()
    {
        $data = array(
            'hash' => 'hashedString'
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('The link have expired or you have already confirmed password reset.');
        $client->confirm_reset($data);
    }

    public function testrequired()
    {
        $configArr = array();

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->required();
        $this->assertIsArray($result);
    }
}
 