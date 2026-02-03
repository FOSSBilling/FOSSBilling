<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Hosting\Tests;

use FOSSBilling\ProductType\Domain\Entity\Tld;
use FOSSBilling\ProductType\Hosting\Entity\Hosting;
use FOSSBilling\ProductType\Hosting\Entity\HostingPlan;
use FOSSBilling\ProductType\Hosting\Entity\HostingServer;
use FOSSBilling\ProductType\Hosting\HostingHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?HostingHandler $service;

    public function setUp(): void
    {
        $this->service = new HostingHandler();
    }

    public static function validateOrdertDataProvider(): array
    {
        return [
            ['server_id', 'Hosting product is not configured completely. Configure server for hosting product.', 701],
            ['hosting_plan_id', 'Hosting product is not configured completely. Configure hosting plan for hosting product.', 702],
            ['sld', 'Domain name is invalid.', 703],
            ['tld', 'Domain extension is invalid.', 704],
        ];
    }

    #[DataProvider('validateOrdertDataProvider')]
    public function testValidateOrderData(string $field, string $exceptionMessage, int $excCode): void
    {
        $data = [
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com',
        ];

        unset($data[$field]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->service->validateOrderData($data);
    }

    public function testActionCreate(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->client_id = 1;
        $confArr = [
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com',
        ];
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn($confArr);

        $hostingServerModel = new HostingServer();
        $reflId = new \ReflectionProperty($hostingServerModel, 'id');
        $reflId->setAccessible(true);
        $reflId->setValue($hostingServerModel, 1);
        $hostingServerModel->setIp('1.1.1.1');

        $hostingPlansModel = new HostingPlan();
        $reflPlanId = new \ReflectionProperty($hostingPlansModel, 'id');
        $reflPlanId->setAccessible(true);
        $reflPlanId->setValue($hostingPlansModel, 2);

        $serverRepoStub = $this->createStub(\FOSSBilling\ProductType\Hosting\Repository\HostingServerRepository::class);
        $serverRepoStub->method('find')
            ->willReturn($hostingServerModel);

        $planRepoStub = $this->createStub(\FOSSBilling\ProductType\Hosting\Repository\HostingPlanRepository::class);
        $planRepoStub->method('find')
            ->willReturn($hostingPlansModel);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($serverRepoStub, $planRepoStub) {
                if ($class === HostingServer::class) {
                    return $serverRepoStub;
                }
                if ($class === HostingPlan::class) {
                    return $planRepoStub;
                }
                return null;
            });
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->create($orderModel);
    }

    public function testActionRenew(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->service_id = 1;

        $model = new Hosting(1);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDiWithMockEntityManager();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $result = $this->service->renew($orderModel);
        $this->assertTrue($result);
    }

    public function testActionRenewOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->renew($orderModel);
    }

    public function testActionSuspend(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDiWithMockEntityManager();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('suspendAccount');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $serviceMock->setDi($di);
        $result = $serviceMock->suspend($orderModel);
        $this->assertTrue($result);
    }

    public function testActionSuspendOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->suspend($orderModel);
    }

    public function testActionUnsuspend(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDiWithMockEntityManager();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('unsuspendAccount');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $serviceMock->setDi($di);
        $result = $serviceMock->unsuspend($orderModel);
        $this->assertTrue($result);
    }

    public function testActionUnsuspendOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->unsuspend($orderModel);
    }

    public function testActionCancel(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDiWithMockEntityManager();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('cancelAccount');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $serviceMock->setDi($di);
        $result = $serviceMock->cancel($orderModel);
        $this->assertTrue($result);
    }

    public function testActionCancelOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->cancel($orderModel);
    }

    public function testActionDelete(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->status = 'active';

        $model = new Hosting(1);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDiWithMockEntityManagerForDelete();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['cancel'])
            ->getMock();

        $serviceMock->setDi($di);
        $serviceMock->delete($orderModel);
    }

    public function testChangeAccountPlan(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $modelHp = new HostingPlan();

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM', 'getServerPackage'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountPackage');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerPackage')
            ->willReturn(new \Server_Package());

        $serviceMock->setDi($di);
        $result = $serviceMock->changeAccountPlan($orderModel, $model, $modelHp);
        $this->assertTrue($result);
    }

    public function testChangeAccountUsername(): void
    {
        $data = [
            'username' => 'u123456',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testChangeAccountUsernameMissingUsername(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Account username is missing or is invalid');
        $this->service->changeAccountUsername($orderModel, $model, $data);
    }

    public function testChangeAccountIp(): void
    {
        $data = [
            'ip' => '1.1.1.1',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountIp');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testChangeAccountIpMissingIp(): void
    {
        $data = [];
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Account IP address is missing or is invalid');
        $this->service->changeAccountIp($orderModel, $model, $data);
    }

    public function testChangeAccountDomain(): void
    {
        $data = [
            'tld' => 'com',
            'sld' => 'testingSld',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountDomain');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testChangeAccountDomainMissingParams(): void
    {
        $data = [];
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Domain SLD or TLD is missing');
        $this->service->changeAccountDomain($orderModel, $model, $data);
    }

    public function testChangeAccountPassword(): void
    {
        $data = [
            'password' => 'topsecret',
            'password_confirm' => 'topsecret',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountPassword');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testChangeAccountPasswordMissingParams(): void
    {
        $data = [];
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Account password is missing or is invalid');
        $this->service->changeAccountPassword($orderModel, $model, $data);
    }

    public function testSync(): void
    {
        $data = [
            'password' => 'topsecret',
            'password_confirm' => 'topsecret',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new Hosting(1);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $accountObj = new \Server_Account();
        $accountObj->setUsername('testUser1');
        $accountObj->setIp('1.1.1.1');

        $accountObj2 = new \Server_Account();
        $accountObj2->setUsername('testUser2');
        $accountObj2->setIp('2.2.2.2');

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('synchronizeAccount')
            ->willReturn($accountObj2);

        $AMresultArray = [$serverManagerMock, $accountObj];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->sync($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testToApiArray(): void
    {
        $hostingServer = new HostingServer();
        $hostingServer->setManager('Custom');
        $reflId = new \ReflectionProperty($hostingServer, 'id');
        $reflId->setAccessible(true);
        $reflId->setValue($hostingServer, 1);

        $hostingHp = new HostingPlan();
        $reflHpId = new \ReflectionProperty($hostingHp, 'id');
        $reflHpId->setAccessible(true);
        $reflHpId->setValue($hostingHp, 1);

        $model = new Hosting(1);
        $model->setServer($hostingServer);
        $model->setPlan($hostingHp);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder');

        $serverManagerCustomMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['server_manager'] = $di->protect(fn ($manager, $config): \PHPUnit\Framework\MockObject\MockObject => $serverManagerCustomMock);

        $this->service->setDi($di);

        $result = $this->service->toApiArray($model, false, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testUpdate(): void
    {
        $data = [
            'username' => 'testUser',
            'ip' => '1.1.1.1',
        ];
        $model = new Hosting(1);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Hosting) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->update($model, $data);
        $this->assertTrue($result);
    }

    public function testGetServerManagers(): void
    {
        $result = $this->service->getServerManagers();
        $this->assertIsArray($result);
    }

    public function testGetServerManagerConfig(): void
    {
        $manager = 'Custom';

        $expected = [
            'label' => 'Custom Server Manager',
        ];

        $result = $this->service->getServerManagerConfig($manager);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetServerPairs(): void
    {
        $expected = [
            '1' => 'name',
            '2' => 'ding',
        ];

        $serverRepoStub = $this->createStub(\FOSSBilling\ProductType\Hosting\Repository\HostingServerRepository::class);
        $serverRepoStub->method('getPairs')
            ->willReturn($expected);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(\FOSSBilling\ProductType\Hosting\Entity\HostingServer::class)
            ->willReturn($serverRepoStub);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->getServerPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetServerSearchQuery(): void
    {
        $serverRepoStub = $this->createStub(\FOSSBilling\ProductType\Hosting\Repository\HostingServerRepository::class);
        $qbStub = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qbStub->method('getDQL')
            ->willReturn('SELECT hs FROM HostingServer hs WHERE 1=1');
        $qbStub->method('getParameters')
            ->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $serverRepoStub->method('getSearchQueryBuilder')
            ->willReturn($qbStub);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(\FOSSBilling\ProductType\Hosting\Entity\HostingServer::class)
            ->willReturn($serverRepoStub);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->getServersSearchQuery([]);
        $this->assertIsString($result[0]);
        $this->assertInstanceOf(\Doctrine\Common\Collections\Collection::class, $result[1]);
    }

    public function testCreateServer(): void
    {
        $hostingServerModel = new HostingServer();

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$hostingServerModel) {
                $hostingServerModel = $entity;
                $reflId = new \ReflectionProperty($entity, 'id');
                $reflId->setAccessible(true);
                $reflId->setValue($entity, 1);
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $name = 'newSuperFastServer';
        $ip = '1.1.1.1';
        $manager = 'Custom';
        $data = [];
        $result = $this->service->createServer($name, $ip, $manager, $data);
        $this->assertIsInt($result);
        $this->assertEquals(1, $result);
    }

    public function testDeleteServer(): void
    {
        $hostingServerModel = new HostingServer();
        $reflId = new \ReflectionProperty($hostingServerModel, 'id');
        $reflId->setAccessible(true);
        $reflId->setValue($hostingServerModel, 1);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('remove')
            ->with($hostingServerModel);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->deleteServer($hostingServerModel);
        $this->assertTrue($result);
    }

    public function testUpdateServer(): void
    {
        $data = [
            'name' => 'newName',
            'ip' => '1.1.1.1',
            'hostname' => 'unknownStar',
            'active' => true,
            'status_url' => 'na',
            'ns1' => 'ns1.testserver.eu',
            'ns2' => 'ns2.testserver.eu',
            'ns3' => 'ns3.testserver.eu',
            'ns4' => 'ns4.testserver.eu',
            'manager' => 'Custom',
            'username' => 'testingJohn',
            'password' => 'hardToGuess',
            'accesshash' => 'secret',
            'port' => '23',
            'secure' => false,
        ];

        $hostingServerModel = new HostingServer();

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof HostingServer) {
                    $reflId = new \ReflectionProperty($entity, 'id');
                    $reflId->setAccessible(true);
                    $reflId->setValue($entity, 1);
                }
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateServer($hostingServerModel, $data);
        $this->assertTrue($result);
    }

    public function testGetServerManager(): void
    {
        $hostingServerModel = new HostingServer();
        $hostingServerModel->setManager('Custom');

        $serverManagerCustom = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['server_manager'] = $di->protect(fn ($manager, $config): \PHPUnit\Framework\MockObject\MockObject => $serverManagerCustom);
        $this->service->setDi($di);

        $result = $this->service->getServerManager($hostingServerModel);
        $this->assertInstanceOf('\Server_Manager_Custom', $result);
    }

    public function testGetServerManagerManagerNotDefined(): void
    {
        $hostingServerModel = new HostingServer();

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(654);
        $this->expectExceptionMessage('Invalid server manager. Server was not configured properly');
        $this->service->getServerManager($hostingServerModel);
    }

    public function testGetServerManagerServerManagerInvalid(): void
    {
        $hostingServerModel = new HostingServer();
        $hostingServerModel->setManager('Custom');

        $di = $this->getDi();
        $di['server_manager'] = $di->protect(fn ($manager, $config): null => null);
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage("Server manager {$hostingServerModel->getManager()} is invalid.");
        $this->service->getServerManager($hostingServerModel);
    }

    public function testTestConnection(): void
    {
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('testConnection')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->willReturn($serverManagerMock);

        $hostingServerModel = new HostingServer();
        $result = $serviceMock->testConnection($hostingServerModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetHpPairs(): void
    {
        $expected = [
            '1' => 'free',
            '2' => 'paid',
        ];

        $planRepoStub = $this->createStub(\FOSSBilling\ProductType\Hosting\Repository\HostingPlanRepository::class);
        $planRepoStub->method('getPairs')
            ->willReturn($expected);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(\FOSSBilling\ProductType\Hosting\Entity\HostingPlan::class)
            ->willReturn($planRepoStub);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->getHpPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetHpSearchQuery(): void
    {
        $planRepoStub = $this->createStub(\FOSSBilling\ProductType\Hosting\Repository\HostingPlanRepository::class);
        $qbStub = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $qbStub->method('getDQL')
            ->willReturn('SELECT hp FROM HostingPlan hp WHERE 1=1');
        $qbStub->method('getParameters')
            ->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
        $planRepoStub->method('getSearchQueryBuilder')
            ->willReturn($qbStub);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(\FOSSBilling\ProductType\Hosting\Entity\HostingPlan::class)
            ->willReturn($planRepoStub);

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->getHpSearchQuery([]);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]->toArray());
        $this->assertSame([], $result[1]->toArray());
    }

    public function testDeleteHp(): void
    {
        $model = new HostingPlan();
        $reflId = new \ReflectionProperty($model, 'id');
        $reflId->setAccessible(true);
        $reflId->setValue($model, 1);

        $hostingRepoMock = $this->createMock(\FOSSBilling\ProductType\Hosting\Repository\HostingRepository::class);
        $hostingRepoMock->expects($this->atLeastOnce())
            ->method('findBy')
            ->willReturn([]);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(\FOSSBilling\ProductType\Hosting\Entity\Hosting::class)
            ->willReturn($hostingRepoMock);
        $emMock->expects($this->atLeastOnce())
            ->method('remove')
            ->with($model);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->deleteHp($model);
        $this->assertTrue($result);
    }

    public function testToHostingHpApiArray(): void
    {
        $model = new HostingPlan();
        $reflId = new \ReflectionProperty($model, 'id');
        $reflId->setAccessible(true);
        $reflId->setValue($model, 1);

        $result = $this->service->toHostingHpApiArray($model);
        $this->assertIsArray($result);
    }

    public function testUpdateHp(): void
    {
        $data = [
            'name' => 'firstPlan',
            'bandwidth' => '100000',
            'quota' => '1000',
            'max_addon' => '0',
            'max_ft' => '1',
            'max_sql' => '2',
            'max_pop' => '1',
            'max_sub' => '2',
            'max_park' => '1',
        ];

        $model = new HostingPlan();
        $reflId = new \ReflectionProperty($model, 'id');
        $reflId->setAccessible(true);
        $reflId->setValue($model, 1);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->with($model);
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateHp($model, $data);
        $this->assertTrue($result);
    }

    public function testCreateHp(): void
    {
        $newId = 1;
        $capturedPlan = null;

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $emMock->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($plan) use (&$capturedPlan, $newId) {
                $capturedPlan = $plan;
                // Simulate Doctrine assigning an ID after persist
                $reflId = new \ReflectionProperty($plan, 'id');
                $reflId->setAccessible(true);
                $reflId->setValue($plan, $newId);

                return null;
            });
        $emMock->expects($this->atLeastOnce())
            ->method('flush');

        $di = $this->getDi();
        $di['em'] = $emMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        // Pass string values to override the integer defaults in the service method
        $data = [
            'max_addon' => '1',
            'max_park' => '1',
            'max_sub' => '1',
            'max_pop' => '1',
            'max_sql' => '1',
            'max_ftp' => '1',
        ];

        $result = $this->service->createHp('Free Plan', $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testGetServerPackage(): void
    {
        $model = new HostingPlan();
        $model->setConfig('{}');

        $di = $this->getDi();

        $this->service->setDi($di);
        $result = $this->service->getServerPackage($model);
        $this->assertInstanceOf('\Server_Package', $result);
    }

    public function testGetServerManagerWithLog(): void
    {
        $hostingServerModel = new HostingServer();
        $hostingServerModel->setManager('Custom');

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->willReturn($serverManagerMock);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getLogger')
            ->willReturn(new \Box_Log());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock->setDi($di);
        $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
        $this->assertInstanceOf('\Server_Manager_Custom', $result);
    }

    public function testGetManagerUrls(): void
    {
        $hostingServerModel = new HostingServer();
        $hostingServerModel->setManager('Custom');

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('getLoginUrl')
            ->willReturn('/login');
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('getResellerLoginUrl')
            ->willReturn('/admin/login');

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->willReturn($serverManagerMock);

        $result = $serviceMock->getManagerUrls($hostingServerModel);
        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertIsString($result[1]);
    }

    public function testGetManagerUrlsException(): void
    {
        $hostingServerModel = new HostingServer();
        $hostingServerModel->setManager('Custom');

        $serviceMock = $this->getMockBuilder(HostingHandler::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->will($this->throwException(new \Exception('Controlled unit test exception')));

        $result = $serviceMock->getManagerUrls($hostingServerModel);
        $this->assertIsArray($result);
        $this->assertFalse($result[0]);
        $this->assertFalse($result[1]);
    }

    public function testGetFreeTldsFreeTldsAreNotSet(): void
    {
        $di = $this->getDi();

        $tldArray = ['tld' => '.com'];
        $domainHandlerMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $domainHandlerMock->expects($this->atLeastOnce())
            ->method('tldToApiArray')
            ->willReturn($tldArray);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $domainHandlerMock);

        $registryMock = $this->createMock(\FOSSBilling\ProductTypeRegistry::class);
        $registryMock->expects($this->atLeastOnce())
            ->method('getHandler')
            ->with('domain')
            ->willReturn($domainHandlerMock);
        $di['product_type_registry'] = $registryMock;

        $tldModel = new Tld();
        $tldModel->setTld('.com');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$tldModel]);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $result = $this->service->getFreeTlds($model);
        $this->assertIsArray($result);
    }

    public function testGetFreeTlds(): void
    {
        $config = [
            'free_tlds' => ['.com'],
        ];
        $di = $this->getDi();

        $this->service->setDi($di);
        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->config = json_encode($config);

        $result = $this->service->getFreeTlds($model);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
