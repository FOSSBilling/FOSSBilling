<?php

declare(strict_types=1);

namespace Box\Mod\Servicehosting\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
    }

    public function testFreeTlds(): void
    {
        $di = new \Pimple\Container();

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->type = \Model_Product::HOSTING;
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di['db'] = $dbMock;

        $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFreeTlds')
            ->with($model)
            ->willReturn([]);
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->free_tlds(['product_id' => 1]);
        $this->assertIsArray($result);
    }

    public function testFreeTldsProductTypeIsNotHosting(): void
    {
        $di = new \Pimple\Container();

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
        $serviceMock->expects($this->never())->method('getFreeTlds');
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product type is invalid');
        $this->api->free_tlds(['product_id' => 1]);
    }
}
