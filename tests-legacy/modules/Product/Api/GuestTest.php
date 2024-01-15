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

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSearchQuery')
            ->willReturn(['sqlString', []]);

        $pagerMock = $this->getMockBuilder('\Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testgetPairs()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testgetMissingRequiredParams()
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product ID or slug is missing');
        $this->api->get($data);
    }

    public function testgetWithSetId()
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

    public function testgetWithSetSlug()
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

    public function testgetProductNotFound()
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

    public function testcategoryGetList()
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
        $pagerMock = $this->getMockBuilder('\Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
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

    public function testcategoryGetPairs()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategoryPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->category_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testgetSliderEmptyList()
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

    public function testgetSlider()
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

    public function testgetSliderJsonFormat()
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
        $this->assertIsArray(json_decode($result, 1));
    }
}
