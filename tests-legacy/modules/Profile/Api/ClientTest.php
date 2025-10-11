<?php

namespace Box\Tests\Mod\Profile\Api;

class ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Profile\Api\Client
     */
    protected $clientApi;

    public function setUp(): void
    {
        $this->clientApi = new \Box\Mod\Profile\Api\Client();
    }

    public function testGet(): void
    {
        $clientService = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientService);
        $this->clientApi->setDi($di);
        $this->clientApi->setIdentity(new \Model_Client());

        $result = $this->clientApi->get();
        $this->assertIsArray($result);
    }

    public function testUpdate(): void
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('updateClient')
            ->willReturn(true);

        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $data = [];

        $result = $this->clientApi->update($data);
        $this->assertTrue($result);
    }

    public function testApiKeyGet(): void
    {
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->api_token = '16047a3e69f5245756d73b419348f0c7';
        $this->clientApi->setIdentity($client);

        $result = $this->clientApi->api_key_get([]);
        $this->assertEquals($result, $client->api_token);
    }

    public function testApiKeyReset(): void
    {
        $apiKey = '16047a3e69f5245756d73b419348f0c7';
        $service = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('resetApiKey')
            ->willReturn($apiKey);

        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $result = $this->clientApi->api_key_reset([]);
        $this->assertEquals($result, $apiKey);
    }

    public function testChangePassword(): void
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('changeClientPassword')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['password'] = new \FOSSBilling\PasswordManager();

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->pass = $di['password']->hashIt('oldpw');

        $this->clientApi->setDi($di);
        $this->clientApi->setService($service);
        $this->clientApi->setIdentity($model);

        $data = [
            'current_password' => 'oldpw',
            'new_password' => '16047a3e69f5245756d73b419348f0c7',
            'confirm_password' => '16047a3e69f5245756d73b419348f0c7',
        ];
        $result = $this->clientApi->change_password($data);
        $this->assertTrue($result);
    }

    public function testChangePasswordPasswordsDoNotMatchException(): void
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();
        $service->expects($this->never())
            ->method('changeClientPassword')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);
        $this->clientApi->setService($service);
        $this->clientApi->setIdentity(new \Model_Client());

        $data = [
            'current_password' => '1234',
            'new_password' => '16047a3e69f5245756d73b419348f0c7',
            'confirm_password' => '7c0f843914b37d6575425f96e3a74061', // passwords do not match
        ];

        $this->expectException(\Exception::class);
        $result = $this->clientApi->change_password($data);
        $this->assertTrue($result);
    }

    public function testLogout(): void
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('logoutClient')
            ->willReturn(true);
        $this->clientApi->setService($service);

        $result = $this->clientApi->logout();
        $this->assertTrue($result);
    }
}
