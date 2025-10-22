<?php

class Api_AdminTest extends BBTestCase
{
    /**
     * @var Box\Mod\Order\Api\Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Box\Mod\Order\Api\Admin();
    }

    public function testgetDi(): void
    {
        $di = new Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGet(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $apiMock->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $apiMock->get($data);

        $this->assertIsArray($result);
    }

    public function testGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['getSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->willReturn(['query', []]);

        $paginatorMock = $this->getMockBuilder('\\' . FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn(['show_addons' => 0]);

        $di = new Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod'] = $di->protect(fn (): PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->get_list([]);

        $this->assertIsArray($result);
    }

    public function testCreate(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['createOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('createOrder')
            ->willReturn(random_int(1, 100));

        $validatorMock = $this->getMockBuilder('\\' . FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $client = new Model_Client();
        $client->loadBean(new DummyBean());

        $product = new Model_Product();
        $product->loadBean(new DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($client, $product);

        $di = new Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'client_id' => random_int(1, 100),
            'product_id' => random_int(1, 100),
        ];
        $result = $this->api->create($data);

        $this->assertIsInt($result);
    }

    public function testUpdate(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['updateOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [
            'client_id' => random_int(1, 100),
            'product_id' => random_int(1, 100),
        ];
        $result = $apiMock->update($data);

        $this->assertTrue($result);
    }

    public function testActivate(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['activateOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('activateOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [
            'client_id' => random_int(1, 100),
            'product_id' => random_int(1, 100),
        ];
        $result = $apiMock->activate($data);

        $this->assertTrue($result);
    }

    public function testRenew(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['renewOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('renewOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $result = $apiMock->renew($data);

        $this->assertTrue($result);
    }

    public function testRenewPendingSetup(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());
        $order->status = Model_ClientOrder::STATUS_PENDING_SETUP;

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder', 'activate'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);
        $apiMock->expects($this->atLeastOnce())
            ->method('activate')
            ->willReturn(true);

        $data = [];
        $result = $apiMock->renew($data);

        $this->assertTrue($result);
    }

    public function testSuspend(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['suspendFromOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('suspendFromOrder')
            ->willReturn(true);

        $di = new Pimple\Container();

        $apiMock->setDi($di);

        $apiMock->setService($serviceMock);

        $data = [];
        $result = $apiMock->suspend($data);

        $this->assertTrue($result);
    }

    public function testUnsuspend(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());
        $order->status = Model_ClientOrder::STATUS_SUSPENDED;

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['unsuspendFromOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unsuspendFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $result = $apiMock->unsuspend($data);

        $this->assertTrue($result);
    }

    public function testUnsuspendNotSuspendedException(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());
        $order->status = Model_ClientOrder::STATUS_ACTIVE;

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['unsuspendFromOrder'])->getMock();
        $serviceMock->expects($this->never())->method('unsuspendFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $this->expectException(FOSSBilling\Exception::class);
        $result = $apiMock->unsuspend($data);

        $this->assertTrue($result);
    }

    public function testCancel(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['cancelFromOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cancelFromOrder')
            ->willReturn(true);

        $di = new Pimple\Container();

        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $data = [];
        $result = $apiMock->cancel($data);

        $this->assertTrue($result);
    }

    public function testUncancel(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());
        $order->status = Model_ClientOrder::STATUS_CANCELED;

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['uncancelFromOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('uncancelFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $result = $apiMock->uncancel($data);

        $this->assertTrue($result);
    }

    public function testUncancelNotCanceledException(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());
        $order->status = Model_ClientOrder::STATUS_ACTIVE;

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['uncancelFromOrder'])->getMock();
        $serviceMock->expects($this->never())->method('uncancelFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $this->expectException(FOSSBilling\Exception::class);
        $result = $apiMock->uncancel($data);

        $this->assertTrue($result);
    }

    public function testDelete(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['deleteFromOrder'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('deleteFromOrder')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testDeleteWithAddons(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['deleteFromOrder', 'getOrderAddonsList'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('deleteFromOrder')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('getOrderAddonsList')
            ->willReturn([$order]);

        $apiMock->setService($serviceMock);

        $data = [
            'delete_addons' => true,
        ];
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testBatchSuspendExpired(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['batchSuspendExpired'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('batchSuspendExpired')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->batch_suspend_expired($data);

        $this->assertTrue($result);
    }

    public function testBatchCancelSuspended(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['batchCancelSuspended'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('batchCancelSuspended')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->batch_cancel_suspended($data);

        $this->assertTrue($result);
    }

    public function testUpdateConfig(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['updateOrderConfig'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateOrderConfig')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [
            'config' => [],
        ];
        $result = $apiMock->update_config($data);

        $this->assertTrue($result);
    }

    public function testUpdateConfigNotSetConfigException(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['updateOrderConfig'])->getMock();
        $serviceMock->expects($this->never())->method('updateOrderConfig')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $data = [];
        $this->expectException(FOSSBilling\Exception::class);
        $result = $apiMock->update_config($data);

        $this->assertTrue($result);
    }

    public function testService(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderServiceData'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);

        $admin = new Model_Admin();
        $admin->loadBean(new RedBeanPHP\OODBBean());

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity($admin);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $apiMock->service($data);

        $this->assertIsArray($result);
    }

    public function testStatusHistoryGetList(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderStatusSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderStatusSearchQuery')
            ->willReturn(['query', []]);

        $paginatorMock = $this->getMockBuilder('\\' . FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $di = new Pimple\Container();

        $di['pager'] = $paginatorMock;

        $apiMock->setDi($di);

        $apiMock->setService($serviceMock);

        $result = $apiMock->status_history_get_list([]);

        $this->assertIsArray($result);
    }

    public function testStatusHistoryAdd(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['orderStatusAdd'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('orderStatusAdd')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $di = new Pimple\Container();
        $di['validator'] = $validatorMock;

        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $data = [
            'status' => Model_ClientOrder::STATUS_ACTIVE,
        ];
        $result = $apiMock->status_history_add($data);

        $this->assertTrue($result);
    }

    public function testStatusHistoryDelete(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['orderStatusRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('orderStatusRm')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $di = new Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $this->api->status_history_delete($data);

        $this->assertTrue($result);
    }

    public function testGetStatuses(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['counter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('counter')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_statuses();
        $this->assertIsArray($result);
    }

    public function testGetInvoiceOptions(): void
    {
        $result = $this->api->get_invoice_options([]);
        $this->assertIsArray($result);
    }

    public function testGetStatusPairs(): void
    {
        $result = $this->api->get_status_pairs([]);
        $this->assertIsArray($result);
    }

    public function testAddons(): void
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderAddonsList', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderAddonsList')
            ->willReturn([$order]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $apiMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['_getOrder'])->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->willReturn($order);

        $apiMock->setService($serviceMock);

        $data = [
            'status' => Model_ClientOrder::STATUS_ACTIVE,
        ];
        $result = $apiMock->addons($data);

        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testGetOrder(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder('\\' . Box\Mod\Order\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $order = new Model_ClientOrder();
        $order->loadBean(new DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $di = new Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $this->api->get($data);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder('\\' . Box\Mod\Order\Api\Admin::class)->onlyMethods(['delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->
        method('delete')->
        willReturn(true);
        $validatorMock = $this->getMockBuilder('\\' . FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }
}
