<?php

declare(strict_types=1);

namespace Box\Mod\Product\Api;

use Box\Mod\Product\Entity\Product;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
    }

    public function testGetWithSetId(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new Product();

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

        $model = new Product();

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
        $serviceMock->expects($this->once())
            ->method('getPaginatedProductCategories')
            ->with(['status' => 'enabled'])
            ->willReturn([
                'list' => [
                    ['products' => [$this->getApiProductArray()]],
                ],
            ]);

        $this->api->setService($serviceMock);
        $this->api->setDi($this->getDi());
        $result = $this->api->category_get_list([]);
        $this->assertIsArray($result);
    }

    public function testGetList(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $serviceMock->expects($this->once())
            ->method('getPaginatedProducts')
            ->with(['status' => 'enabled', 'show_hidden' => false])
            ->willReturn(['list' => []]);

        $this->api->setService($serviceMock);
        $this->api->setDi($this->getDi());
        $result = $this->api->get_list([]);
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
