<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\License\Tests\Api;

use FOSSBilling\ProductType\License\Api;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
        $this->api->setIdentity(new \Model_Guest());
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCheckLicenseDetails(): void
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
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkLicenseDetails')
            ->willReturn($licenseResult);

        $this->api->setService($serviceMock);
        $result = $this->api->guest_check($data);

        $this->assertIsArray($result);
    }
}
