<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Hosting\Tests\Api;

use FOSSBilling\ProductType\Hosting\Api;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
    }

    public function testFreeTlds(): void
    {
        $di = $this->getDi();

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->type = \Model_Product::HOSTING;
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di['db'] = $dbMock;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFreeTlds')
            ->with($model)
            ->willReturn([]);
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->guest_free_tlds(['product_id' => 1]);
        $this->assertIsArray($result);
    }

    public function testFreeTldsProductTypeIsNotHosting(): void
    {
        $di = $this->getDi();

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->never())->method('getFreeTlds');
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product type is invalid');
        $this->api->guest_free_tlds(['product_id' => 1]);
    }
}
