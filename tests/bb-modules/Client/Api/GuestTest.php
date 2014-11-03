<?php


namespace Box\Mod\Client\Api;


class GuestTest extends \PHPUnit_Framework_TestCase {

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

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $result = $client->create($data);

        $this->assertInternalType('int', $result);
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

        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $di['validator'] = $validatorMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $this->setExpectedException('\Box_Exception', 'Email is already registered. You may want to login instead of registering.');
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

        $this->setExpectedException('\Box_Exception', 'New registrations are temporary disabled');
        $client->create($data);
    }

    public function requiredFieldsProvider()
    {
        return array(
            array('email', 'Email required'),
            array('first_name', 'First name required'),
            array('password', 'Password required'),
            array('password_confirm', 'Password confirmation required'),
        );

    }

    /**
     * @dataProvider requiredFieldsProvider
     */
    public function testCreateRequiredFields($field, $ExceptionMessage)
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
        unset($data[ $field ]);
        $client = new \Box\Mod\Client\Api\Guest();
        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });
        $client->setDi($di);

        $this->setExpectedException('\Box_Exception', $ExceptionMessage);
        $client->create($data);
    }

    public function testCreateConfigRequiredFields()
    {
        $fieldName = 'city';
        $configArr = array(
            'allow_signup' => true,
            'required' => array($fieldName),
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

        $exceptionMessage = sprintf('It is required that you provide details for field "%s"', ucwords(str_replace('_', ' ', $fieldName)));
        $this->setExpectedException('\Box_Exception', $exceptionMessage);
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
            ->method('getByLoginDetails')
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

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $results = $client->login($data);

        $this->assertInternalType('array', $results);
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

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->reset_password($data);
        $this->assertTrue($result);
    }

    public function testreset_passwordEmailMissing()
    {
        $data = array();

        $client = new \Box\Mod\Client\Api\Guest();

        $this->setExpectedException('\Box_Exception', 'Email required');
        $client->reset_password($data);
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

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $this->setExpectedException('\Box_Exception', 'Email not found in our database');
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
            ->method('load')->will($this->returnValue($modelClient));

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue(1));

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $emailServiceMock =  $serviceMock = $this->getMockBuilder('\Box\Mod\Email\Service')->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->
            method('sendTemplate');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] =  $di->protect(function ($name) use($emailServiceMock) {return $emailServiceMock;});

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->confirm_reset($data);
        $this->assertTrue($result);
    }

    public function testconfirm_resetMissingHash()
    {
        $data = array();

        $client = new \Box\Mod\Client\Api\Guest();

        $this->setExpectedException('\Box_Exception', 'Hash required');
        $client->confirm_reset($data);
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

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $this->setExpectedException('\Box_Exception', 'The link have expired or you have already confirmed password reset.');
        $client->confirm_reset($data);
    }

    public function testis_vat()
    {
        $data = array(
            'country' => 'DE',
            'vat' => 'VATnumber',
        );

        $guzzleMock = $this->getMockBuilder('\Guzzle\Http\Client')->disableOriginalConstructor()->getMock();
        $guzzleMock->expects($this->atLeastOnce())->method('get')->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['guzzle_client'] = $guzzleMock;

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->is_vat($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function vatProvider()
    {
        return array(
            array('country', 'Country code is required'),
            array('vat', 'Country code is required'),
        );
    }

    /**
     * @dataProvider vatProvider
     */
    public function testis_vatExceptions($field, $exception)
    {
        $data = array(
            'country' => 'DE',
            'vat' => 'VATnumber',
        );
        unset($data[ $field ]);
        $client = new \Box\Mod\Client\Api\Guest();

        $this->setExpectedException('\Box_Exception', $exception);
        $client->is_vat($data);

    }

    public function testrequired()
    {
        $configArr = array();

        $di = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) use($configArr) { return $configArr;  });

        $client = new \Box\Mod\Client\Api\Guest();
        $client->setDi($di);

        $result = $client->required();
        $this->assertInternalType('array', $result);
    }
}
 