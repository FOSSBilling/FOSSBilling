<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\ApiKey\Tests;

use FOSSBilling\ProductType\ApiKey\ApiKeyHandler;
use FOSSBilling\ProductType\ApiKey\Entity\ApiKey;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?ApiKeyHandler $service;

    public function setUp(): void
    {
        $this->service = new ApiKeyHandler();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testAttachOrderConfigEmptyProductConig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{}';
        $data = [];

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testAttachOrderConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '["hello", "world"]';
        $data = ['testing' => 'phase'];
        $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testActionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->client_id = 1;
        $clientOrderModel->service_id = 1;
        $clientOrderModel->config = '{}';

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);
        $this->setPrivateProperty($serviceApiKeyModel, 'apiKey', 'CURRENT-API-KEY');
        $this->setPrivateProperty($serviceApiKeyModel, 'config', '{}');

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);

        $result = $this->service->activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testActionActivateOrderNotExistException(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $di = $this->getDi();
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order does not exist');
        $this->service->activate($clientOrderModel);
    }

    public function testActionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManagerForDelete();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);
        $this->service->delete($clientOrderModel);
    }

    public function testRenew(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);
        $result = $this->service->renew($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testSuspend(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);
        $result = $this->service->suspend($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testUnsuspend(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);
        $result = $this->service->unsuspend($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testCancel(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);
        $result = $this->service->cancel($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testUncancel(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);
        $result = $this->service->uncancel($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testToApiArray(): void
    {
        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);
        $this->setPrivateProperty($serviceApiKeyModel, 'apiKey', 'test-api-key-12345');
        $this->setPrivateProperty($serviceApiKeyModel, 'config', '{}');

        $expected = [
            'id' => 1,
            'created_at' => '',
            'updated_at' => '',
            'api_key' => 'test-api-key-12345',
            'config' => [],
        ];

        $result = $this->service->toApiArray($serviceApiKeyModel, false, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testIsValidWithValidKey(): void
    {
        $data = ['key' => 'VALID-API-KEY-12345'];

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = 'active';

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByApiKey')
            ->willReturn($serviceApiKeyModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['em'] = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);

        $result = $this->service->isValid($data);
        $this->assertTrue($result);
    }

    public function testIsValidWithEmptyKey(): void
    {
        $data = ['key' => ''];

        $di = $this->getDi();
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage("You must provide an API key to check it's validity.");
        $this->service->isValid($data);
    }

    public function testIsValidWithInvalidKey(): void
    {
        $data = ['key' => 'INVALID-KEY'];

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByApiKey')
            ->willReturn(null);

        $di = $this->getDi();
        $di['em'] = $this->createMock(\Doctrine\ORM\EntityManager::class);
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('API key does not exist');
        $this->service->isValid($data);
    }

    public function testResetApiKey(): void
    {
        $data = ['key' => 'OLD-API-KEY-12345'];

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);
        $this->setPrivateProperty($serviceApiKeyModel, 'apiKey', 'OLD-API-KEY-12345');
        $this->setPrivateProperty($serviceApiKeyModel, 'config', '{}');

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('findOneByApiKey')
            ->willReturnOnConsecutiveCalls($serviceApiKeyModel, null, $serviceApiKeyModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);

        $this->service->setDi($di);

        $result = $this->service->resetApiKey($data);
        $this->assertTrue($result);
    }

    public function testUpdateApiKey(): void
    {
        $data = [
            'order_id' => 1,
            'config' => ['new_setting' => 'value'],
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $serviceApiKeyModel = new ApiKey(1);
        $this->setPrivateProperty($serviceApiKeyModel, 'id', 1);
        $this->setPrivateProperty($serviceApiKeyModel, 'apiKey', 'CURRENT-API-KEY');
        $this->setPrivateProperty($serviceApiKeyModel, 'config', '{}');

        $repositoryMock = $this->createMock(\FOSSBilling\ProductType\ApiKey\Repository\ApiKeyRepository::class);
        $repositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($serviceApiKeyModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $di = $this->getDiWithMockEntityManager();
        $di['em']->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturn($repositoryMock);
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->updateApiKey($data);
        $this->assertTrue($result);
    }

    public function testUpdateApiKeyWithoutOrderId(): void
    {
        $data = ['config' => ['new_setting' => 'value']];

        $di = $this->getDi();
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('You must provide the API key order ID in order to update it.');
        $this->service->updateApiKey($data);
    }

    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $propertyName);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }
}
