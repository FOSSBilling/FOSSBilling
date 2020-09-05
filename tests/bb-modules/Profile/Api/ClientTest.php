<?php
namespace Box\Tests\Mod\Profile\Api;


class ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Profile\Api\Client
     */
    protected $clientApi = null;

    public function setUp(): void
    {
        $this->clientApi = new \Box\Mod\Profile\Api\Client();
    }

    public function testGet()
    {
        $clientService = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($clientService) {
            return $clientService;
        });
        $this->clientApi->setDi($di);
        $this->clientApi->setIdentity(new \Model_Client());

        $result = $this->clientApi->get();
        $this->assertIsArray($result);
    }

    public function testUpdate()
    {
        $service = $this->getMockBuilder('\Box\Mod\Profile\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('updateClient')
            ->will($this->returnValue(true));

        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $data = array();

        $result = $this->clientApi->update($data);
        $this->assertTrue($result);
    }

    public function testApi_key_get()
    {
        $client            = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->api_token = '16047a3e69f5245756d73b419348f0c7';
        $this->clientApi->setIdentity($client);

        $result = $this->clientApi->api_key_get(array());
        $this->assertEquals($result, $client->api_token);
    }

    public function testApi_key_reset()
    {
        $apiKey  = '16047a3e69f5245756d73b419348f0c7';
        $service = $this->getMockBuilder('\Box\Mod\Profile\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('resetApiKey')
            ->will($this->returnValue($apiKey));

        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $result = $this->clientApi->api_key_reset(array());
        $this->assertEquals($result, $apiKey);
    }

    public function testChange_password()
    {
        $service = $this->getMockBuilder('\Box\Mod\Profile\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('changeClientPassword')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);

        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $data   = array(
            'password'         => '16047a3e69f5245756d73b419348f0c7',
            'password_confirm' => '16047a3e69f5245756d73b419348f0c7'
        );
        $result = $this->clientApi->change_password($data);
        $this->assertTrue($result);
    }

    public function testChange_passwordPasswordsDoNotMatchException()
    {
        $service = $this->getMockBuilder('\Box\Mod\Profile\Service')->getMock();
        $service->expects($this->never())
            ->method('changeClientPassword')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);
        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $data   = array(
            'password_confirm' => '16047a3e69f5245756d73b419348f0c7',
            'password'         => '7c0f843914b37d6575425f96e3a74061' //passwords do not match
        );

        $this->expectException(\Box_Exception::class);
        $result = $this->clientApi->change_password($data);
        $this->assertTrue($result);
    }

    public function testLogout()
    {
        $service = $this->getMockBuilder('\Box\Mod\Profile\Service')->getMock();
        $service->expects($this->atLeastOnce())
            ->method('logoutClient')
            ->will($this->returnValue(true));
        $this->clientApi->setService($service);

        $result = $this->clientApi->logout();
        $this->assertTrue($result);
    }
}
 