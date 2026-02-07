<?php

declare(strict_types=1);

namespace Box\Mod\Servicelicense\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
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
        $serviceMock = $this->createMock(\Box\Mod\Servicelicense\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkLicenseDetails')
            ->willReturn($licenseResult);

        $this->api->setService($serviceMock);
        $result = $this->api->check($data);

        $this->assertIsArray($result);
    }
}
