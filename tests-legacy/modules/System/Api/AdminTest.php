<?php

namespace Box\Mod\System\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetParams(): void
    {
        $data = [
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParams')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_params($data);
        $this->assertIsArray($result);
    }

    public function testupdateParams(): void
    {
        $data = [
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateParams')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->update_params($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmessages(): void
    {
        $data = [
        ];

        $di = new \Pimple\Container();

        $this->api->setDi($di);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMessages')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->messages($data);
        $this->assertIsArray($result);
    }

    public function testtemplateExists(): void
    {
        $data = [
            'file' => 'testing.txt',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('templateExists')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->template_exists($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function teststringRender(): void
    {
        $data = [
            '_tpl' => 'default',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('renderString')
            ->willReturn('returnStringType');
        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->string_render($data);
        $this->assertIsString($result);
    }

    public function testenv(): void
    {
        $data = [];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getEnv')
            ->willReturn([]);

        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->env($data);
        $this->assertIsArray($result);
    }

    public function testisAllowed(): void
    {
        $data = [
            'mod' => 'extension',
        ];

        $staffServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });

        $di['validator'] = $validatorMock;

        $this->api->setDi($di);

        $result = $this->api->is_allowed($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
