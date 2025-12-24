<?php

declare(strict_types=1);

namespace Box\Mod\Servicedownloadable\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testUpdateOrderNotActivated(): void
    {
        $data['order_id'] = 1;
        $model = new \Model_ClientOrder();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $orderServiceMock);
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->update($data);
    }

    public function testUpdate(): void
    {
        $data['order_id'] = 1;
        $model = new \Model_ClientOrder();

        $modelDownloadableModel = new \Model_ServiceDownloadable();

        $serviceMock = $this->createMock(\Box\Mod\Servicedownloadable\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateProductFile')
            ->willReturn(true);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($modelDownloadableModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $orderServiceMock);
        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
