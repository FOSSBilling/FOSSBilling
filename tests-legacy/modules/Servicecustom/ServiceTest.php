<?php

namespace Box\Tests\Mod\Servicecustom;

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicecustom\Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Servicecustom\Service();
    }

    public function testDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testValidateCustomForm(): void
    {
        $form = [
            'fields' => [
                0 => [
                    'required' => 1,
                    'readonly' => 1,
                    'name' => 'field_name',
                    'default_value' => 'FieldName',
                    'label' => 'label',
                ],
            ],
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Formbuilder\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getForm')
            ->willReturn($form);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $this->service->setDi($di);

        $product = [
            'form_id' => random_int(1, 100),
        ];
        $data = [
            'label' => 'label',
            'field_name' => 'FieldName',
        ];
        $result = $this->service->validateCustomForm($data, $product);
        $this->assertNull($result);
    }

    public function testValidateCustomFormFieldNameNotSetException(): void
    {
        $form = [
            'fields' => [
                0 => [
                    'required' => 1,
                    'readonly' => 1,
                    'name' => 'field_name',
                    'default_value' => 'default',
                    'label' => 'label',
                ],
            ],
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Formbuilder\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getForm')
            ->willReturn($form);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $this->service->setDi($di);

        $product = [
            'form_id' => random_int(1, 100),
        ];
        $data = [];
        $this->expectException(\Exception::class);
        $result = $this->service->validateCustomForm($data, $product);
        $this->assertNull($result);
    }

    public function testValidateCustomFormReadonlyFieldChangeException(): void
    {
        $form = [
            'fields' => [
                0 => [
                    'required' => 1,
                    'readonly' => 1,
                    'name' => 'field_name',
                    'default_value' => 'default',
                    'label' => 'label',
                ],
            ],
        ];

        $service = $this->getMockBuilder('\\' . \Box\Mod\Formbuilder\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getForm')
            ->willReturn($form);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $this->service->setDi($di);

        $product = [
            'form_id' => random_int(1, 100),
        ];
        $data = [
            'field_name' => 'field_name',
        ];

        $this->expectException(\Exception::class);
        $result = $this->service->validateCustomForm($data, $product);
        $this->assertNull($result);
    }

    public function testActionCreate(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->product_id = random_int(1, 100);
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->plugin = 'plugin';
        $product->plugin_config = 'plugin_config';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($product);
        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($serviceCustomModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->action_create($order);
        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testActionActivate(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_activate($order);
        $this->assertTrue($result);
    }

    public function testActionActivateOrderServiceNotCreatedException(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->service->action_activate($order);
    }

    public function testActionRenew(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_renew($order);
        $this->assertTrue($result);
    }

    public function testActiveServiceNotFoundException(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = random_int(1, 100);
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $result = $this->service->action_renew($order);
        $this->assertTrue($result);
    }

    public function testActionSuspend(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_suspend($order);
        $this->assertTrue($result);
    }

    public function testActionUnsuspend(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_unsuspend($order);
        $this->assertTrue($result);
    }

    public function testActionCancel(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_cancel($order);
        $this->assertTrue($result);
    }

    public function testActionUncancel(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_uncancel($order);
        $this->assertTrue($result);
    }

    public function testActionDelete(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = random_int(1, 100);
        $order->config = 'config';

        $serviceCustomModel = new \Model_ServiceCustom();
        $serviceCustomModel->loadBean(new \DummyBean());
        $serviceCustomModel->plugin = '';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceCustomModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->service->setDi($di);

        $result = $this->service->action_delete($order);
        $this->assertTrue($result);
    }

    public function testGetConfig(): void
    {
        $decoded = [
            'J' => 5,
            0 => 'N',
        ];

        $di = new \Pimple\Container();
        $this->service->setDi($di);

        $model = new \Model_ServiceCustom();
        $model->loadBean(new \DummyBean());
        $model->config = json_encode($decoded);

        $result = $this->service->getConfig($model);

        $this->assertEquals($result, $decoded);
    }

    public function testToApiArray(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);

        $model = new \Model_ServiceCustom();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);
        $model->client_id = random_int(1, 100);
        $model->plugin = 'plugin';
        $model->config = '{"config_param":"config_value"}';
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        $result = $this->service->toApiArray($model);

        $this->assertEquals($result['client_id'], $model->client_id);
        $this->assertEquals($result['plugin'], $model->plugin);
        $this->assertEquals($result['config_param'], 'config_value');
        $this->assertEquals($result['updated_at'], $model->updated_at);
        $this->assertEquals($result['created_at'], $model->created_at);
    }

    public function testCustomCallForbiddenMethodException(): void
    {
        $this->expectException(\Exception::class);
        $this->service->customCall(new \Model_ServiceCustom(), 'delete');
    }

    public function testGetServiceCustomByOrderId(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceCustom());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->service->setDi($di);

        $result = $this->service->getServiceCustomByOrderId(random_int(1, 100));

        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testGetServiceCustomByOrderIdOrderServiceNotFoundException(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->service->getServiceCustomByOrderId(random_int(1, 100));
    }

    public function testUpdateConfig(): void
    {
        $model = new \Model_ServiceCustom();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->onlyMethods(['getServiceCustomByOrderId'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $config = ['param1' => 'value1'];
        $result = $serviceMock->updateConfig(random_int(1, 100), $config);
        $this->assertNull($result);
    }

    public function testUpdateConfigNotArrayException(): void
    {
        $model = new \Model_ServiceCustom();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->onlyMethods(['getServiceCustomByOrderId'])->getMock();
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $config = '';
        $this->expectException(\Exception::class);
        $serviceMock->updateConfig(random_int(1, 100), $config);
    }
}
