<?php

namespace Box\Mod\Antispam;

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
        $antispamService = $this->getMockBuilder('\\' . Service::class)->getMock();
        $antispamService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $antispamService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $antispamService);
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeClientSignUp($boxEventMock);
    }

    public function testonBeforeGuestPublicTicketOpen(): void
    {
        $antispamService = $this->getMockBuilder('\\' . Service::class)->getMock();
        $antispamService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $antispamService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $antispamService);
        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->onBeforeGuestPublicTicketOpen($boxEventMock);
    }

    public function dataProviderSpamResponses()
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
