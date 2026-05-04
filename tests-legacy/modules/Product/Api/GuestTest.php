<?php

declare(strict_types=1);

namespace Box\Mod\Product\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetWithSetId(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->config = json_encode(['server_id' => 1, 'allow_domain_register' => true]);

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findOneActiveById')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($this->getApiProductArray());

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testGetWithSetSlug(): void
    {
        $data = [
            'slug' => 'product/1',
        ];

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->config = json_encode(['server_id' => 1, 'allow_domain_register' => true]);

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findOneActiveBySlug')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($this->getApiProductArray());

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testGetProductNotFound(): void
    {
        $data = [
            'slug' => 'product/1',
        ];

        $model = null;

        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findOneActiveBySlug')
            ->willReturn($model);
        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');
        $this->api->get($data);
    }

    public function testCategoryGetList(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategorySearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toProductCategoryApiArray')
            ->willReturn(['products' => [$this->getApiProductArray()]]);

        $pager = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($pager);

        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($modelProductCategory);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['pager'] = $pagerMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);
        $result = $this->api->category_get_list([]);
        $this->assertIsArray($result);
    }

    public function testCategoryGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductCategoryPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->category_get_pairs([]);
        $this->assertIsArray($result);
    }

    private function getApiProductArray(): array
    {
        return [
            'id' => 1,
            'product_category_id' => 1,
            'type' => 'hosting',
            'title' => 'New Item',
            'form_id' => 1,
            'slug' => '/',
            'description' => 'Product description',
            'unit' => 'unit',
            'priority' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 00:00:00',
            'pricing' => [
                'type' => 'free',
                'registrar' => ['id' => 1, 'title' => 'Registrar'],
            ],
            'config' => [
                'server_id' => 1,
                'hosting_plan_id' => 2,
                'allow_domain_register' => true,
            ],
            'addons' => [['id' => 2, 'title' => 'Addon']],
            'price_starting_from' => 0,
            'icon_url' => null,
            'allow_quantity_select' => true,
            'quantity_in_stock' => 10,
            'stock_control' => true,
        ];
    }
}
