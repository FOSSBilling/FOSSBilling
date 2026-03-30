<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Email\Api;

use Box\Mod\Email\Entity\EmailLog;
use Box\Mod\Email\Entity\EmailTemplate;
use Box\Mod\Email\Repository\EmailLogRepository;
use Box\Mod\Email\Repository\EmailTemplateRepository;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_ClientTest extends \BBTestCase
{
    private function createEmMock(?object $templateRepositoryMock = null, ?object $emailLogRepositoryMock = null): object
    {
        if ($templateRepositoryMock === null) {
            $templateRepositoryMock = $this->getMockBuilder(EmailTemplateRepository::class)
                ->disableOriginalConstructor()
                ->getMock();
        }

        if ($emailLogRepositoryMock === null) {
            $emailLogRepositoryMock = $this->getMockBuilder(EmailLogRepository::class)
                ->disableOriginalConstructor()
                ->getMock();
        }

        $emMock = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->method('getRepository')
            ->willReturnCallback(static function (string $entityClass) use ($templateRepositoryMock, $emailLogRepositoryMock) {
                return match ($entityClass) {
                    EmailTemplate::class => $templateRepositoryMock,
                    EmailLog::class => $emailLogRepositoryMock,
                    default => throw new \RuntimeException("Unexpected repository request for {$entityClass}"),
                };
            });
        $emMock->method('flush');
        $emMock->method('persist');

        return $emMock;
    }

    private function createEmailLogEntity(): EmailLog
    {
        return new EmailLog();
    }

    public function testGetList(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();
        $emailService = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['getEmailLogList'])->getMock();
        $emailService->expects($this->once())
            ->method('getEmailLogList')
            ->with(['client_id' => 1])
            ->willReturn([
                'list' => [
                    ['id' => 1],
                ],
            ]);

        $di = $this->getDi();
        $di['em'] = $this->createEmMock();

        $clientApi->setDi($di);
        $clientApi->setService($emailService);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 1;
        $clientApi->setIdentity($client);

        $result = $clientApi->get_list([]);
        $this->assertIsArray($result);

        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);
    }

    public function testGet(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();

        $model = $this->createEmailLogEntity();
        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['findOneForClientById', 'toApiArray'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('findOneForClientById')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);
        $clientApi->setService($service);

        $di = $this->getDi();
        $clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 1;
        $clientApi->setIdentity($client);

        $result = $clientApi->get(['id' => 1]);
        $this->assertIsArray($result);
    }

    public function testGetNotFoundException(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();

        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['findOneForClientById'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('findOneForClientById')
            ->willReturn(null);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;
        $clientApi->setIdentity($client);

        $di = $this->getDi();
        $clientApi->setDi($di);

        $clientApi->setService($service);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $clientApi->get(['id' => 1]);
        $this->assertIsArray($result);
    }

    public function testResend(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();

        $model = $this->createEmailLogEntity();

        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['findOneForClientById', 'resend'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('findOneForClientById')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('resend')
            ->willReturn(true);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;
        $clientApi->setIdentity($client);

        $di = $this->getDi();
        $clientApi->setDi($di);

        $clientApi->setService($service);

        $result = $clientApi->resend(['id' => 1]);
        $this->assertTrue($result);
    }

    public function testResendNotFoundException(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();

        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['findOneForClientById'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('findOneForClientById')
            ->willReturn(null);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;

        $clientApi->setIdentity($client);

        $di = $this->getDi();
        $clientApi->setDi($di);

        $clientApi->setService($service);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $clientApi->resend(['id' => 1]);
        $this->assertIsArray($result);
    }

    public function testDelete(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();

        $di = $this->getDi();

        $model = $this->createEmailLogEntity();
        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['findOneForClientById', 'rm'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('findOneForClientById')
            ->willReturn($model);
        $service->expects($this->atLeastOnce())
            ->method('rm')
            ->willReturn(true);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;
        $clientApi->setIdentity($client);

        $clientApi->setDi($di);

        $clientApi->setService($service);

        $result = $clientApi->delete(['id' => 1]);
        $this->assertTrue($result);
    }

    public function testDeleteNotFoundException(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();

        $service = $this->getMockBuilder(\Box\Mod\Email\Service::class)->onlyMethods(['findOneForClientById'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('findOneForClientById')
            ->willReturn(null);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;

        $clientApi->setIdentity($client);

        $di = $this->getDi();
        $clientApi->setDi($di);

        $clientApi->setService($service);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $clientApi->delete(['id' => 1]);
        $this->assertIsArray($result);
    }
}
