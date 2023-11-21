<?php


namespace Box\Mod\Servicelicense\Api;


class GuestTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Servicelicense\Api\Guest
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Servicelicense\Api\Guest();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcheckLicenseDetails()
    {
        $data = array(
            'license' => 'license1234',
            'host' => 'fossbilling.org',
            'version' => 1,
        );

        $licenseResult =  array(
            'licensed_to' => 'fossbilling.org',
            'created_at' => '2011-12-31',
            'expires_at' => '2020-01+01',
            'valid' => true,
        );
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicelicense\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkLicenseDetails')
            ->will($this->returnValue($licenseResult));

        $this->api->setService($serviceMock);
        $result = $this->api->check($data);

        $this->assertIsArray($result);
    }
}
 