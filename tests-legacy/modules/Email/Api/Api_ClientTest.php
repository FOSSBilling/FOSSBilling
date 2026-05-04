<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Email\Api;

use Box\Mod\Email\Repository\EmailTemplateRepository;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_ClientTest extends \BBTestCase
{
    private function createEmMock(?object $repositoryMock = null): object
    {
        if ($repositoryMock === null) {
            $repositoryMock = $this->getMockBuilder(EmailTemplateRepository::class)
                ->disableOriginalConstructor()
                ->getMock();
        }
        $emMock = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $emMock->method('getRepository')
            ->willReturn($repositoryMock);
        $emMock->method('flush');
        $emMock->method('persist');

        return $emMock;
    }

    public function testGetList(): void
    {
        $clientApi = new \Box\Mod\Email\Api\Client();
        $emailService = new \Box\Mod\Email\Service();

        $willReturn = [
            'list' => [
                'id' => 1,
            ],
        ];
        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['pager'] = $pager;
        $di['em'] = $this->createEmMock();

        $clientApi->setDi($di);
        $emailService->setDi($di);

        $service = $emailService;
        $clientApi->setService($service);

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

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
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
            ->willReturn(false);

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

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());

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
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
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
            ->willReturn(false);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;

        $clientApi->setIdentity($client);

        $di = $this->getDi();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
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

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());
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
            ->willReturn(false);

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

    private function getAllowedRateLimiter(): object
    {
        return new class {
            public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                return new \FOSSBilling\Security\RateLimitResult($policy, false, 10, 9);
            }
        };
    }
}
