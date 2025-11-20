<?php

namespace Box\Mod\Servicedownloadable;

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

    public function testattachOrderConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $data = [];

        $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);
        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testactionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $model = new \Model_ServiceDownloadable();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);
        $result = $this->service->action_create($clientOrderModel);
        $this->assertInstanceOf('\Model_ServiceDownloadable', $result);
    }

    public function testactionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDownloadable());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->action_delete($clientOrderModel);
    }
}
