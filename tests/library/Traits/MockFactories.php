<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Traits;

trait MockFactories
{
    protected function createDatabaseMock(): \Box_Database
    {
        return $this->createMock(\Box_Database::class);
    }

    protected function createDatabaseMockWithMethods(array $methods): \Box_Database
    {
        return $this->getMockBuilder(\Box_Database::class)
            ->onlyMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function createEventMock(array $params = []): \Box_Event
    {
        $mock = $this->createMock(\Box_Event::class);
        $mock->method('getParameters')->willReturn($params);
        $mock->method('getDi')->willReturn($this->getMinimalDi());

        return $mock;
    }

    protected function createLoggerMock(): \Box_Log
    {
        return $this->createMock(\Box_Log::class);
    }

    protected function createToolsMock(): \FOSSBilling\Tools
    {
        return $this->createMock(\FOSSBilling\Tools::class);
    }

    protected function createValidatorMock(): \FOSSBilling\Validate
    {
        return $this->createMock(\FOSSBilling\Validate::class);
    }

    protected function createEventManagerMock(): \Box_EventManager
    {
        return $this->createMock(\Box_EventManager::class);
    }

    protected function createSessionMock(): \Symfony\Component\HttpFoundation\Session\Session
    {
        return $this->createMock(\Symfony\Component\HttpFoundation\Session\Session::class);
    }

    protected function createPdoMock(): \PDO
    {
        return $this->createMock(\PDO::class);
    }

    protected function createPdoStatementMock(): \PDOStatement
    {
        return $this->createMock(\PDOStatement::class);
    }

    protected function createModuleServiceMock(string $serviceClass, array $methods = []): object
    {
        if (empty($methods)) {
            return $this->createMock($serviceClass);
        }

        return $this->getMockBuilder($serviceClass)
            ->onlyMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function createRepositoryMock(string $repositoryClass): object
    {
        return $this->createMock($repositoryClass);
    }

    protected function createEntityMock(string $entityClass): object
    {
        return $this->createMock($entityClass);
    }

    protected function createConfigMock(array $config = []): array
    {
        return array_merge([
            'salt' => 'test_salt_' . uniqid(),
            'url' => 'http://localhost/',
        ], $config);
    }

    protected function createDiWithMocks(array $mocks = []): \Pimple\Container
    {
        $di = $this->getMinimalDi();

        foreach ($mocks as $key => $mock) {
            $di[$key] = $mock;
        }

        return $di;
    }

    protected function createOrderModel(int $id = 1): \Model_ClientOrder
    {
        $order = new \Model_ClientOrder();
        $order->id = $id;
        $order->client_id = 1;
        $order->product_id = 1;
        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $order->price = 10.00;
        $order->quantity = 1;
        $order->config = '{}';

        return $order;
    }

    protected function createClientModel(int $id = 1): \Model_Client
    {
        $client = new \Model_Client();
        $client->id = $id;
        $client->email = 'client@example.com';
        $client->name = 'Test Client';
        $client->currency = 'USD';

        return $client;
    }

    protected function createProductModel(int $id = 1): \Model_Product
    {
        $product = new \Model_Product();
        $product->id = $id;
        $product->title = 'Test Product';
        $product->type = \Model_Product::CUSTOM;
        $product->status = 'active';
        $product->price = 10.00;

        return $product;
    }
}
