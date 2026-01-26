<?php

declare(strict_types=1);

namespace Box\Mod\System\Api;

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

    public function testVersionAdmin(): void
    {
        $authorizationMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authorizationMock->expects($this->atLeastOnce())
            ->method('isAdminLoggedIn')
            ->willReturn(true);

        $di = $this->getDi();
        $di['auth'] = $authorizationMock;

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getVersion')
            ->willReturn(\FOSSBilling\Version::VERSION);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->version();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testVersionShowPublicOn(): void
    {
        $authorizationMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authorizationMock->expects($this->atLeastOnce())
            ->method('isAdminLoggedIn')
            ->willReturn(false);

        $di = $this->getDi();
        $di['auth'] = $authorizationMock;
        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getVersion')
            ->willReturn(\FOSSBilling\Version::VERSION);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('hide_version_public')
            ->willReturn(0);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->version();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testVersionShowPublicOff(): void
    {
        $authorizationMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authorizationMock->expects($this->atLeastOnce())
            ->method('isAdminLoggedIn')
            ->willReturn(false);

        $di = $this->getDi();
        $di['auth'] = $authorizationMock;

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('hide_version_public')
            ->willReturn(1);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->version();

        $this->assertIsString($result);
        $this->assertEmpty($result);
    }

    public function testCompanyShowPublicOn(): void
    {
        $companyData = ['companyName' => 'TestCo'];

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->method('isAdminLoggedIn')->willReturn(false);
        $authMock->method('isClientLoggedIn')->willReturn(false);

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCompany')
            ->willReturn($companyData);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('hide_company_public')
            ->willReturn(0);

        $di = $this->getDi();
        $di['auth'] = $authMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->company();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testCompanyShowPublicOff(): void
    {
        $companyData = [
            'companyName' => 'TestCo',
            'vat_number' => 'Test VAT',
            'email' => 'test@email.com',
            'tel' => '123456789',
            'account_number' => '987654321',
            'number' => '123456',
            'address_1' => 'Test Address 1',
            'address_2' => 'Test Address 2',
            'address_3' => 'Test Address 3',
        ];

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->method('isAdminLoggedIn')->willReturn(false);
        $authMock->method('isClientLoggedIn')->willReturn(false);

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCompany')
            ->willReturn($companyData);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->with('hide_company_public')
            ->willReturn(1);

        $di = $this->getDi();
        $di['auth'] = $authMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->company();

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('vat_number', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('tel', $result);
        $this->assertArrayNotHasKey('account_number', $result);
        $this->assertArrayNotHasKey('number', $result);
        $this->assertArrayNotHasKey('address_1', $result);
        $this->assertArrayNotHasKey('address_2', $result);
        $this->assertArrayNotHasKey('address_3', $result);
    }

    public function testPeriodTitle(): void
    {
        $data = ['code' => 'periodCode'];

        $servuceMock = $this->createMock(\Box\Mod\System\Service::class);
        $servuceMock->expects($this->atLeastOnce())
            ->method('getPeriod')
            ->willReturn('periodTtitleValue');
        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($servuceMock);

        $result = $this->api->period_title($data);
        $this->assertIsString($result);
    }

    public function testPeriodTitleMissingCode(): void
    {
        $data = [];
        $expected = '-';
        $di = $this->getDi();

        $this->api->setDi($di);
        $result = $this->api->period_title($data);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetPendingMessages(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $messageArr = ['Important message to user'];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPendingMessages')
            ->willReturn($messageArr);

        $serviceMock->expects($this->atLeastOnce())
            ->method('clearPendingMessages');

        $this->api->setService($serviceMock);
        $result = $this->api->get_pending_messages();
        $this->assertIsArray($result);
        $this->assertEquals($messageArr, $result);
    }
}
