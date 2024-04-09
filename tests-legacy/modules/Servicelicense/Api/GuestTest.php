<?php

namespace Box\Mod\Servicelicense\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var Guest
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Guest();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcheckLicenseDetails(): void
    {
        $data = [
            'license' => 'license1234',
            'host' => 'fossbilling.org',
            'version' => 1,
        ];

        $licenseResult = [
            'licensed_to' => 'fossbilling.org',
            'created_at' => '2011-12-31',
            'expires_at' => '2020-01+01',
            'valid' => true,
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicelicense\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkLicenseDetails')
            ->willReturn($licenseResult);

        $this->api->setService($serviceMock);
        $result = $this->api->check($data);

        $this->assertIsArray($result);
    }
}
