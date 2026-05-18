<?php

declare(strict_types=1);

namespace Box\Mod\Servicehosting\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
    }

    public function testFreeTlds(): void
    {
        $di = $this->getDi();

        $model = new \Box\Mod\Product\Entity\Product();
        $model->setType(\Box\Mod\Product\Service::HOSTING);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);

        $di['mod_service'] = $di->protect(function (string $service) use ($productService) {
            if ($service === 'product') {
                return $productService;
            }

            throw new \RuntimeException('Unexpected service request');
        });

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
        $di = $this->getDi();

        $model = new \Box\Mod\Product\Entity\Product();

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findProductById')
            ->with(1)
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(function (string $service) use ($productService) {
            if ($service === 'product') {
                return $productService;
            }

            throw new \RuntimeException('Unexpected service request');
        });

        $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
        $serviceMock->expects($this->never())->method('getFreeTlds');
        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product type is invalid');
        $this->api->free_tlds(['product_id' => 1]);
    }
}
