<?php

declare(strict_types=1);

namespace Box\Mod\Product;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetPairs(): void
    {
        $data = [
            'type' => 'domain',
            'products_only' => true,
            'active_only' => true,
        ];

        $execArray = [
            [
                'id' => 1,
                'title' => 'title4test',
            ],
        ];

        $expectArray = [
            '1' => 'title4test',
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($execArray);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getPairs($data);
        $this->assertIsArray($result);
        $this->assertEquals($expectArray, $result);
    }

    public function testToApiArray(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods([
                'getStartingFromPrice',
                'getUpgradablePairs',
                'toProductPaymentApiArray', ])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getStartingFromPrice');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getUpgradablePairs');
        $productPaymentArray = [
            'type' => 'free',
            \Model_ProductPayment::FREE => ['price' => 0, 'setup' => 0],
            \Model_ProductPayment::ONCE => ['price' => 1, 'setup' => 10],
            \Model_ProductPayment::RECURRENT => [],
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('toProductPaymentApiArray')
            ->willReturn($productPaymentArray);

        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->product_category_id = 1;
        $model->product_payment_id = 2;
        $model->config = '{}';

        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());
        $modelProductCategory->type = 'free';

        $modelProductPayment = new \Model_ProductPayment();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnOnConsecutiveCalls($modelProductPayment, $modelProductCategory);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $model->setDi($di);
        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testGetTypes(): void
    {
        $expectedArray = [
            'custom' => 'Custom',
            'license' => 'License',
            'download' => 'Download',
            'hosting' => 'Hosting',
            'domain' => 'Domain',
        ];

        $di = $this->getDi();

        $registryMock = $this->createMock(\FOSSBilling\ProductTypeRegistry::class);
        $registryMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn($expectedArray);
        $di['product_type_registry'] = $registryMock;

        $this->service->setDi($di);
        $result = $this->service->getTypes();
        $this->assertIsArray($result);
        $this->assertEquals($expectedArray, $result);
    }

    public function testGetMainDomainProduct(): void
    {
        $model = new \Model_Product();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getMainDomainProduct();
        $this->assertInstanceOf('\Model_Product', $result);
    }

    public function testGetPaymentTypes(): void
    {
        $expected = [
            'free' => 'Free',
            'once' => 'One time',
            'recurrent' => 'Recurrent',
        ];

        $result = $this->service->getPaymentTypes();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testCreateProduct(): void
    {
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $modelPayment = new \Model_ProductPayment();
        $modelPayment->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $newProductId = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturnOnConsecutiveCalls($modelPayment, $modelProduct);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newProductId);

        $toolMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolMock->expects($this->atLeastOnce())
            ->method('slug');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['db'] = $dbMock;
        $di['tools'] = $toolMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->createProduct('title', 'domain');
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testUpdateProductMissngPricingType(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getPaymentTypes'])
            ->getMock();

        $typesArr = [
            'free' => 'Free',
            'once' => 'One time',
            'recurrent' => 'Recurrent',
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPaymentTypes')
            ->willReturn($typesArr);

        $data = [
            'pricing' => [],
        ];

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Pricing type is required');
        $serviceMock->updateProduct($modelProduct, $data);
    }

    public function testUpdateProduct(): void
    {
        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getPaymentTypes'])
            ->getMock();

        $typesArr = [
            'free' => 'Free',
            'once' => 'One time',
            'recurrent' => 'Recurrent',
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPaymentTypes')
            ->willReturn($typesArr);

        $data = [
            'pricing' => [
                'type' => \Model_ProductPayment::RECURRENT,
                \Model_ProductPayment::RECURRENT => [
                    [
                        '1W' => [
                            'setup' => '',
                            'price' => '',
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
            'config' => [],
            'product_category_id' => 1,
            'form_id' => 10,
            'icon_url' => 'http://www.google.com',
            'status' => false,
            'hidden' => 0,
            'slug' => 'product/0',
            'setup' => 'test',
            'upgrades' => [],
            'addons' => [],
            'title' => 'new Title',
            'stock_control' => false,
            'allow_quantity_select' => false,
            'quantity_in_stock' => 0,
            'description' => 'Product description',
            'plugin' => 'plug in',
        ];

        $modelProductPayment = new \Model_ProductPayment();
        $modelProductPayment->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($modelProductPayment);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->updateProduct($modelProduct, $data);
        $this->assertTrue($result);
    }

    public function testUpdatePriority(): void
    {
        $data = [
            'priority' => [
                1 => 10,
                5 => 1,
            ],
        ];

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($modelProduct);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updatePriority($data);
        $this->assertTrue($result);
    }

    public function testUpdateConfig(): void
    {
        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->config = '{"settings":5,"max":"10"}';

        $data = [
            'config' => [
                'settings' => 3,
                'max' => '',
            ],
            'new_config_name' => 'newParam',
            'new_config_value' => 'newValue',
        ];

        $dbMock = $this->createMock('\Box_Database');

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateConfig($modelProduct, $data);
        $this->assertTrue($result);
    }

    public function testGetAddons(): void
    {
        $addonsRows = [
            [
                'id' => 1,
                'title' => 'testTitle',
            ],
        ];

        $expected = [
            1 => 'testTitle',
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($addonsRows);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->getAddons();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testCreateAddon(): void
    {
        $newProductId = 1;

        $modelPayment = new \Model_ProductPayment();
        $modelPayment->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newProductId);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturnOnConsecutiveCalls($modelPayment, $modelProduct);

        $toolMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolMock->expects($this->atLeastOnce())
            ->method('slug');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolMock;

        $this->service->setDi($di);

        $result = $this->service->createAddon('title');
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testDeleteProductActivaOrderException(): void
    {
        $model = new \Model_Product();

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('productHasOrders')
            ->willReturn(true);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove product which has active orders.');
        $this->service->deleteProduct($model);
    }

    public function testGetProductCategoryPairs(): void
    {
        $execArray = [
            [
                'id' => 1,
                'title' => 'title4test',
            ],
        ];

        $expectArray = [
            '1' => 'title4test',
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($execArray);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getProductCategoryPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expectArray, $result);
    }

    public function testUpdateCategory(): void
    {
        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateCategory($model, 'title', 'decription', 'http://urltoimg.com/img.jpg');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCreateCategory(): void
    {
        $newCategoryId = 1;

        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newCategoryId);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->createCategory('title');

        $this->assertIsInt($result);
        $this->assertEquals($newCategoryId, $result);
    }

    public function testRemoveProductCategoryCategoryHasProductsException(): void
    {
        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($modelProduct);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove product category with products');
        $this->service->removeProductCategory($modelProductCategory);
    }

    public function testRemoveProductCategory(): void
    {
        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());

        $modelProduct = null;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($modelProduct);

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->removeProductCategory($modelProductCategory);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetPromoSearchQuery(): void
    {
        $data = [
            'search' => 'keyword',
            'id' => 1,
            'status' => 'active',
        ];

        $di = $this->getDi();

        $this->service->setDi($di);

        [$sql, $params] = $this->service->getPromoSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
    }

    public function testCreatePromo(): void
    {
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $newPromoId = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newPromoId);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->createPromo('code', 'percentage', 50, [], [], [], []);
        $this->assertIsInt($result);
        $this->assertEquals($newPromoId, $result);
    }

    public function testToPromoApiArray(): void
    {
        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());
        $model->products = '{}';
        $model->periods = '{}';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['tools'] = $this->createMock(\FOSSBilling\Tools::class);

        $this->service->setDi($di);

        $result = $this->service->toPromoApiArray($model);
        $this->assertIsArray($result);
    }

    public function testUpdatePromo(): void
    {
        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $data = [
            'code' => 'GO',
            'type' => 'absolute',
            'value' => 10,
            'active' => true,
            'freesetup' => true,
            'once_per_client' => true,
            'recurring' => false,
            'maxuses' => '1',
            'used' => '0',
            'start_at' => '2012-01-01',
            'end_at' => '2012-01-02',
            'products' => 'domain',
            'periods' => [],
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->updatePromo($model, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDeletePromo(): void
    {
        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->deletePromo($model);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetProductSearchQuery(): void
    {
        $data = [
            'search' => 'keyword',
            'type' => 'domain',
            'status' => 'active',
            'show_hidden' => true,
        ];

        $di = $this->getDi();

        $this->service->setDi($di);

        [$sql, $params] = $this->service->getProductSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
    }

    public function testToProductCategoryApiArray(): void
    {
        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->type = 'custom';
        $categoryProductsArr = [
            $modelProduct,
        ];

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getCategoryProducts', 'toApiArray'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCategoryProducts')
            ->willReturn($categoryProductsArr);

        $apiArrayResult = [
            'price_starting_from' => 1,
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn($apiArrayResult);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->toProductCategoryApiArray($model);
        $this->assertIsArray($result);
    }

    public function testToProductCategoryApiArrayStartingFromValueNotZero(): void
    {
        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->type = 'custom';
        $categoryProductsArr = [
            $modelProduct,
            $modelProduct,
            $modelProduct,
            $modelProduct,
        ];

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getCategoryProducts', 'toApiArray'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCategoryProducts')
            ->willReturn($categoryProductsArr);

        $min = 1;

        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturnOnConsecutiveCalls(
                [
                    'price_starting_from' => 4,
                ],
                [
                    'price_starting_from' => 5,
                ],
                [
                    'price_starting_from' => 2,
                ],
                [
                    'price_starting_from' => $min,
                ]
            );

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->toProductCategoryApiArray($model);
        $this->assertIsArray($result);
        $this->assertEquals($min, $result['price_starting_from']);
    }

    public function testFindOneActiveById(): void
    {
        $model = new \Model_Product();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findOneActiveById(1);
        $this->assertInstanceOf('\Model_Product', $result);
    }

    public function testFindOneActiveBySlug(): void
    {
        $model = new \Model_Product();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findOneActiveBySlug('product/1');
        $this->assertInstanceOf('\Model_Product', $result);
    }

    public function testGetProductCategorySearchQuery(): void
    {
        [$sql, $params] = $this->service->getProductCategorySearchQuery([]);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
        $this->assertSame([], $params);
    }

    public function testGetStartingFromPriceTypeFree(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->product_payment_id = 1;

        $productPaymentModel = new \Model_ProductPayment();
        $productPaymentModel->loadBean(new \DummyBean());
        $productPaymentModel->type = 'free';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($productPaymentModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getStartingFromPrice($productModel);

        $this->assertIsInt($result);
        $this->assertEquals('0', $result);
    }

    public function testGetStartingFromPricePaymentNotDefined(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());

        $result = $this->service->getStartingFromPrice($productModel);

        $this->assertNull($result);
    }

    public function testGetStartingFromPriceDomainType(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = Service::DOMAIN;
        $productModel->product_payment_id = 1;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getStartingDomainPrice', 'getStartingPrice'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getStartingDomainPrice')
            ->willReturn(10.00);
        $serviceMock->expects($this->never())
            ->method('getStartingPrice');

        $result = $serviceMock->getStartingFromPrice($productModel);
        $this->assertNotNull($result);
    }

    public function testGetUpgradablePairs(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->upgrades = '{}';

        $expected = [];

        $result = $this->service->getUpgradablePairs($productModel);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetProductTitlesByIds(): void
    {
        $ids = ['1', '2'];

        $queryArr = [
            [
                'id' => '1',
                'titile' => 'test',
            ],
            [
                'id' => '2',
                'titile' => 'Another',
            ],
        ];

        $expected = [
            '1' => 'test',
            '2' => 'Another',
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getProductTitlesByIds($ids);
        $this->assertIsArray($result);
    }

    public function testGetCategoryProducts(): void
    {
        $productCategoryModel = new \Model_ProductCategory();
        $productCategoryModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$productModel]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getCategoryProducts($productCategoryModel);
        $this->assertIsArray($result);
    }

    public function testToProductPaymentApiArray(): void
    {
        $productPaymentModel = new \Model_ProductPayment();
        $productPaymentModel->loadBean(new \DummyBean());

        $result = $this->service->toProductPaymentApiArray($productPaymentModel);
        $this->assertIsArray($result);
    }

    public function testGetStartingPrice(): void
    {
        $productPaymentModel = new \Model_ProductPayment();
        $productPaymentModel->loadBean(new \DummyBean());
        $productPaymentModel->type = 'recurrent';

        $minPrice = 1;

        $productPaymentModel->w_enabled = true;
        $productPaymentModel->w_price = 2;
        $productPaymentModel->m_enabled = true;
        $productPaymentModel->m_price = 4;
        $productPaymentModel->q_enabled = true;
        $productPaymentModel->q_price = 8;
        $productPaymentModel->b_enabled = true;
        $productPaymentModel->b_price = $minPrice;
        $productPaymentModel->a_enabled = true;
        $productPaymentModel->a_price = 10;
        $productPaymentModel->bia_enabled = true;
        $productPaymentModel->bia_price = 12;
        $productPaymentModel->tria_enabled = true;
        $productPaymentModel->tria_price = 14;

        $result = $this->service->getStartingPrice($productPaymentModel);
        $this->assertIsInt($result);
        $this->assertEquals($minPrice, $result);
    }

    public function testCanUpgradeToReturnsTrue(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getUpgradablePairs'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getUpgradablePairs')
            ->willReturn(['2' => 'Hossting']);

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->id = 1;

        $newProductModel = new \Model_Product();
        $newProductModel->loadBean(new \DummyBean());
        $newProductModel->id = 2;

        $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
        $this->assertTrue($result);
    }

    public function testCanUpgradeToUpgradeIsImposible(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getUpgradablePairs'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getUpgradablePairs')
            ->willReturn(['4' => 'Domain']);

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->id = 1;

        $newProductModel = new \Model_Product();
        $newProductModel->loadBean(new \DummyBean());
        $newProductModel->id = 2;

        $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
        $this->assertFalse($result);
    }

    public function testCanUpgradeToSameProducts(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->id = 1;

        $newProductModel = new \Model_Product();
        $newProductModel->loadBean(new \DummyBean());
        $newProductModel->id = 1;

        $result = $this->service->canUpgradeTo($productModel, $newProductModel);
        $this->assertFalse($result);
    }
}
