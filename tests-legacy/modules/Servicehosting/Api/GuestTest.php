<?php

namespace Box\Mod\Servicehosting\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var Guest
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Guest();
    }

    public function testfreeTlds(): void
    {
        $di = new \Pimple\Container();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di['validator'] = $validatorMock;

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->type = \Model_Product::HOSTING;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFreeTlds')
            ->with($model)
            ->willReturn([]);
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->free_tlds(['product_id' => 1]);
        $this->assertIsArray($result);
    }

    public function testfreeTldsProductTypeIsNotHosting(): void
    {
        $di = new \Pimple\Container();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di['validator'] = $validatorMock;

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('getFreeTlds');
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product type is invalid');
        $this->api->free_tlds(['product_id' => 1]);
    }
}
