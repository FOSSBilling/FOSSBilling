<?php

namespace Box\Mod\Product\Api;

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

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetWithSetId(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_Product();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findOneActiveById')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testgetWithSetSlug(): void
    {
        $data = [
            'slug' => 'product/1',
        ];

        $model = new \Model_Product();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findOneActiveBySlug')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testgetProductNotFound(): void
    {
        $data = [
            'slug' => 'product/1',
        ];

        $model = null;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findOneActiveBySlug')
            ->willReturn($model);
        $di = new \Pimple\Container();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');
        $this->api->get($data);
    }

    public function testcategoryGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategorySearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toProductCategoryApiArray')
            ->willReturn([]);

        $pager = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($pager);

        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($modelProductCategory);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['pager'] = $pagerMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->category_get_list([]);
        $this->assertIsArray($result);
    }

    public function testcategoryGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategoryPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->category_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testgetSliderEmptyList(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $result = $this->api->get_slider([]);
        $this->assertIsArray($result);
        $this->assertEquals([], $result);
    }

    public function testgetSlider(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$productModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $arr = [
            'id' => 1,
            'slug' => '/',
            'title' => 'New Item',
            'pricing' => '1W',
            'config' => [],
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($arr);

        $this->api->setService($serviceMock);
        $result = $this->api->get_slider([]);
        $this->assertIsArray($result);
    }

    public function testgetSliderJsonFormat(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$productModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $arr = [
            'id' => 1,
            'slug' => '/',
            'title' => 'New Item',
            'pricing' => '1W',
            'config' => [],
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($arr);

        $this->api->setService($serviceMock);
        $result = $this->api->get_slider([]);
        $this->assertIsArray($result);

        $result = $this->api->get_slider(['format' => 'json']);
        $this->assertIsString($result);
        $this->assertIsArray(json_decode($result ?? '', true));
    }
}
