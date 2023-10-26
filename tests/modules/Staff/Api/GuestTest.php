<?php

namespace Box\Tests\Mod\Staff\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Staff\Api\Guest
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Staff\Api\Guest();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCreate()
    {
        $adminId = 1;

        $apiMock = $this->getMockBuilder('\Box\Mod\Staff\Api\Guest')
            ->setMethods(array('login'))
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('login');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createAdmin')
            ->will($this->returnValue($adminId));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(array()));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $toolsMock = $this->getMockBuilder('\FOSSBilling\Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $data = array(
            'email' => 'example@fossbilling.org',
            'password' => 'EasyToGuess',
        );
        $result = $apiMock->create($data);
        $this->assertTrue($result);
    }

    public function testCreate_Exception()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(array(array())));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $data = array(
            'email' => 'example@fossbilling.org',
            'password' => 'EasyToGuess',
        );

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(55);
        $this->expectExceptionMessage('Administrator account already exists');
        $this->api->create($data);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testLoginWithoutEmail()
    {
        $guestApi = new \Box\Mod\Staff\Api\Guest();

        $di = new \Pimple\Container();
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $guestApi->login(array());
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testLoginWithoutPassword()
    {
        $guestApi = new \Box\Mod\Staff\Api\Guest();

        $di = new \Pimple\Container();
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $guestApi->login(array('email'=>'email@domain.com'));
    }

    public function testSuccessfulLogin()
    {
        $modMock = $this->getMockBuilder('Box_Mod')
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('login')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $toolsMock = $this->getMockBuilder('\FOSSBilling\Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setService($serviceMock);
        $guestApi->setDi($di);
        $result = $guestApi->login(array('email'=>'email@domain.com', 'password'=>'pass'));
        $this->assertIsArray($result);
    }

    public function testLoginCheckIpException()
    {
        $modMock = $this->getMockBuilder('\Box_Mod')
            ->disableOriginalConstructor()
            ->getMock();
        $configArr = array(
            'allowed_ips' => '1.1.1.1'.PHP_EOL.'2.2.2.2',
            'check_ip' => true,
        );
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue($configArr));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $toolsMock = $this->getMockBuilder('\FOSSBilling\Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);
        $ip = '192.168.0.1';
        $guestApi->setIp($ip);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage(sprintf('You are not allowed to login to admin area from %s address', $ip));

        $data = array(
            'email'    => 'email@domain.com',
            'password' => 'pass',
        );
        $guestApi->login($data);
    }
}
