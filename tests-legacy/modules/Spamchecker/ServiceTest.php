<?php

namespace Box\Mod\Spamchecker;

use Symfony\Component\HttpFoundation\Request;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testonBeforeClientSignUp(): void
    {
        $spamCheckerService = $this->getMockBuilder('\\' . Service::class)->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $spamCheckerService);
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeClientSignUp($boxEventMock);
    }

    public function testonBeforeGuestPublicTicketOpen(): void
    {
        $spamCheckerService = $this->getMockBuilder('\\' . Service::class)->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $spamCheckerService);
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeGuestPublicTicketOpen($boxEventMock);
    }

    public function testisBlockedIpIpNotBlocked(): void
    {
        $clientIp = '214.1.4.99';
        $modConfig = [
            'block_ips' => true,
            'blocked_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
        ];

        $di = new \Pimple\Container();
        $di['request'] = Request::createFromGlobals();
        $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
            if ($modName == 'Spamchecker') {
                return $modConfig;
            }
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->isBlockedIp($boxEventMock);
    }

    public function testisBlockedIpBlockIpsNotEnabled(): void
    {
        $modConfig = [
            'block_ips' => false,
        ];

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
            if ($modName == 'Spamchecker') {
                return $modConfig;
            }
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

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
