<?php

declare(strict_types=1);

namespace Box\Mod\Spamchecker;

use Box\Mod\Client\Event\BeforeClientSignUpEvent;
use Box\Mod\Support\Event\BeforeGuestPublicTicketOpenEvent;
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

    public function testRunSignupChecks(): void
    {
        $spamCheckerService = $this->getMockBuilder(Service::class)
            ->onlyMethods(['isBlockedIp', 'isSpam', 'isTemp'])
            ->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $spamCheckerService);

        $event = new BeforeClientSignUpEvent(
            email: 'test@example.com',
            ip: '127.0.0.1',
        );

        $spamCheckerService->setDi($di);
        $spamCheckerService->runSignupChecks($event);
    }

    public function testRunGuestTicketChecks(): void
    {
        $spamCheckerService = $this->getMockBuilder(Service::class)
            ->onlyMethods(['isBlockedIp', 'isSpam', 'isTemp'])
            ->getMock();
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isBlockedIp');
        $spamCheckerService->expects($this->atLeastOnce())
            ->method('isSpam');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $spamCheckerService);

        $event = new BeforeGuestPublicTicketOpenEvent(
            email: 'test@example.com',
            ip: '127.0.0.1',
            subject: 'Test',
        );

        $spamCheckerService->setDi($di);
        $spamCheckerService->runGuestTicketChecks($event);
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
            if ($modName == 'Spamchecker') {
                return $modConfig;
            }
        });

        $this->service->setDi($di);

        $event = new BeforeClientSignUpEvent(
            email: 'test@example.com',
            ip: $clientIp,
        );

        // Should not throw exception since IP is not blocked
        $this->service->isBlockedIp($event);
        $this->assertTrue(true); // Test passes if no exception was thrown
    }

    public function testIsBlockedIpBlockIpsNotEnabled(): void
    {
        $modConfig = [
            'block_ips' => false,
        ];

        $di = $this->getDi();
        $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
            if ($modName == 'Spamchecker') {
                return $modConfig;
            }
        });

        $this->service->setDi($di);

        $event = new BeforeClientSignUpEvent(
            email: 'test@example.com',
            ip: '127.0.0.1',
        );

        // Should not throw exception since blocking is disabled
        $this->service->isBlockedIp($event);
        $this->assertTrue(true); // Test passes if no exception was thrown
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
