<?php

declare(strict_types=1);

namespace Box\Mod\Antispam;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testOnBeforeClientSignUp(): void
    {
        $antispamService = $this->createMock(Service::class);
        $antispamService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $antispamService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): Service => $antispamService);
        $boxEventMock = $this->getMockBuilder(\Box_Event::class)->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeClientSignUp($boxEventMock);
    }

    public function testOnBeforeGuestPublicTicketOpen(): void
    {
        $antispamService = $this->createMock(Service::class);
        $antispamService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $antispamService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): Service => $antispamService);
        $boxEventMock = $this->getMockBuilder(\Box_Event::class)->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeGuestPublicTicketOpen($boxEventMock);
    }

    public function testIsBlockedIpIpNotBlocked(): void
    {
        $clientIp = '214.1.4.99';
        $modConfig = [
            'block_ips' => true,
            'blocked_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
        ];

        $di = $this->getDi();
        $di['request'] = Request::createFromGlobals();
        $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
            if ($modName == 'security') {
                return $modConfig;
            }

            return [];
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();

        $this->service->setDi($di);
        $this->service->isBlockedIp($boxEventMock);
    }

    public function testIsBlockedIpBlockIpsNotEnabled(): void
    {
        $modConfig = [
            'block_ips' => false,
        ];

        $di = $this->getDi();
        $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
            if ($modName == 'security') {
                return $modConfig;
            }

            return [];
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();

        $this->service->setDi($di);
        $this->service->isBlockedIp($boxEventMock);
    }

    public function dataProviderSpamResponses(): array
    {
        return [
            [
                '{"success" : "true", "username" : {"appears" : "true" }}', 'Your username is blacklisted in the Stop Forum Spam database',
            ],
            [
                '{"success" : "true", "email" : {"appears" : "true" }}', 'Your e-mail is blacklisted in the Stop Forum Spam database',
            ],
            [
                '{"success" : "true", "ip" : {"appears" : "true" }}', 'Your IP address is blacklisted in the Stop Forum Spam database',
            ],
        ];
    }
}
