<?php

namespace Box\Mod\Product;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetPairs(): void
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($execArray);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getPairs($data);
        $this->assertIsArray($result);
        $this->assertEquals($expectArray, $result);
    }

    public function testtoApiArray(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnOnConsecutiveCalls($modelProductPayment, $modelProductCategory);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $model->setDi($di);
        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testgetTypes(): void
    {
        $modArray = [
            'servicecustomtest',
        ];

        $expectedArray = [
            'custom' => 'Custom',
            'license' => 'License',
            'downloadable' => 'Downloadable',
            'hosting' => 'Hosting',
            'domain' => 'Domain',
        ];

        $expectedArray['customtest'] = 'Customtest';

        $extensionServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getInstalledMods')
            ->willReturn($modArray);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extensionServiceMock);

        $this->service->setDi($di);
        $result = $this->service->getTypes();
        $this->assertIsArray($result);
        $this->assertEquals($expectedArray, $result);
    }

    public function testgetMainDomainProduct(): void
    {
        $model = new \Model_Product();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getMainDomainProduct();
        $this->assertInstanceOf('\Model_Product', $result);
    }

    public function testgetPaymentTypes(): void
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

    public function testcreateProduct(): void
    {
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $modelPayment = new \Model_ProductPayment();
        $modelPayment->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $newProductId = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturnOnConsecutiveCalls($modelPayment, $modelProduct);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newProductId);

        $toolMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolMock->expects($this->atLeastOnce())
            ->method('slug');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['db'] = $dbMock;
        $di['tools'] = $toolMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->createProduct('title', 'domain');
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testupdateProductMissngPricingType(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

    public function testupdateProduct(): void
    {
        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($modelProductPayment);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->updateProduct($modelProduct, $data);
        $this->assertTrue($result);
    }

    public function testupdatePriority(): void
    {
        $data = [
            'priority' => [
                1 => 10,
                5 => 1,
            ],
        ];

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($modelProduct);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updatePriority($data);
        $this->assertTrue($result);
    }

    public function testupdateConfig(): void
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateConfig($modelProduct, $data);
        $this->assertTrue($result);
    }

    public function testgetAddons(): void
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($addonsRows);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->getAddons();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testcreateAddon(): void
    {
        $newProductId = 1;

        $modelPayment = new \Model_ProductPayment();
        $modelPayment->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newProductId);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturnOnConsecutiveCalls($modelPayment, $modelProduct);

        $toolMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolMock->expects($this->atLeastOnce())
            ->method('slug');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolMock;

        $this->service->setDi($di);

        $result = $this->service->createAddon('title');
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testdeleteProductActivaOrderException(): void
    {
        $model = new \Model_Product();

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('productHasOrders')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove product which has active orders.');
        $this->service->deleteProduct($model);
    }

    public function testgetProductCategoryPairs(): void
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($execArray);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getProductCategoryPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expectArray, $result);
    }

    public function testupdateCategory(): void
    {
        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateCategory($model, 'title', 'decription', 'http://urltoimg.com/img.jpg');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcreateCategory(): void
    {
        $newCategoryId = 1;

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newCategoryId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->createCategory('title');

        $this->assertIsInt($result);
        $this->assertEquals($newCategoryId, $result);
    }

    public function testremoveProductCategoryCategoryHasProductsException(): void
    {
        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($modelProduct);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove product category with products');
        $this->service->removeProductCategory($modelProductCategory);
    }

    public function testremoveProductCategory(): void
    {
        $modelProductCategory = new \Model_ProductCategory();
        $modelProductCategory->loadBean(new \DummyBean());

        $modelProduct = null;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($modelProduct);

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->removeProductCategory($modelProductCategory);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetPromoSearchQuery(): void
    {
        $data = [
            'search' => 'keyword',
            'id' => 1,
            'status' => 'active',
        ];

        $di = new \Pimple\Container();

        $this->service->setDi($di);

        [$sql, $params] = $this->service->getPromoSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
    }

    public function testcreatePromo(): void
    {
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $newPromoId = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newPromoId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->createPromo('code', 'percentage', 50, [], [], [], []);
        $this->assertIsInt($result);
        $this->assertEquals($newPromoId, $result);
    }

    public function testtoPromoApiArray(): void
    {
        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());
        $model->products = '{}';
        $model->periods = '{}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['tools'] = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();

        $this->service->setDi($di);

        $result = $this->service->toPromoApiArray($model);
        $this->assertIsArray($result);
    }

    public function testupdatePromo(): void
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->updatePromo($model, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdeletePromo(): void
    {
        $model = new \Model_Promo();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->deletePromo($model);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetProductSearchQuery(): void
    {
        $data = [
            'search' => 'keyword',
            'type' => 'domain',
            'status' => 'active',
            'show_hidden' => true,
        ];

        $di = new \Pimple\Container();

        $this->service->setDi($di);

        [$sql, $params] = $this->service->getProductSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
    }

    public function testtoProductCategoryApiArray(): void
    {
        $model = new \Model_ProductCategory();
        $model->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->type = 'custom';
        $categoryProductsArr = [
            $modelProduct,
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->toProductCategoryApiArray($model);
        $this->assertIsArray($result);
    }

    public function testtoProductCategoryApiArrayStartingFromValueNotZero(): void
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

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->toProductCategoryApiArray($model);
        $this->assertIsArray($result);
        $this->assertEquals($min, $result['price_starting_from']);
    }

    public function testfindOneActiveById(): void
    {
        $model = new \Model_Product();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findOneActiveById(1);
        $this->assertInstanceOf('\Model_Product', $result);
    }

    public function testfindOneActiveBySlug(): void
    {
        $model = new \Model_Product();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findOneActiveBySlug('product/1');
        $this->assertInstanceOf('\Model_Product', $result);
    }

    public function testgetProductCategorySearchQuery(): void
    {
        [$sql, $params] = $this->service->getProductCategorySearchQuery([]);

        $this->assertIsString($sql);
        $this->assertIsArray($params);
        $this->assertEquals([], $params);
    }

    public function testgetStartingFromPriceTypeFree(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->product_payment_id = 1;

        $productPaymentModel = new \Model_ProductPayment();
        $productPaymentModel->loadBean(new \DummyBean());
        $productPaymentModel->type = 'free';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($productPaymentModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getStartingFromPrice($productModel);

        $this->assertIsInt($result);
        $this->assertEquals('0', $result);
    }

    public function testgetStartingFromPricePaymentNotDefined(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());

        $result = $this->service->getStartingFromPrice($productModel);

        $this->assertNull($result);
    }

    public function testgetStartingFromPriceDomainType(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = Service::DOMAIN;
        $productModel->product_payment_id = 1;

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

    public function testgetUpgradablePairs(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->upgrades = '{}';

        $expected = [];

        $result = $this->service->getUpgradablePairs($productModel);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetProductTitlesByIds(): void
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getProductTitlesByIds($ids);
        $this->assertIsArray($result);
    }

    public function testgetCategoryProducts(): void
    {
        $productCategoryModel = new \Model_ProductCategory();
        $productCategoryModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$productModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getCategoryProducts($productCategoryModel);
        $this->assertIsArray($result);
    }

    public function testtoProductPaymentApiArray(): void
    {
        $productPaymentModel = new \Model_ProductPayment();
        $productPaymentModel->loadBean(new \DummyBean());

        $result = $this->service->toProductPaymentApiArray($productPaymentModel);
        $this->assertIsArray($result);
    }

    public function testgetStartingPrice(): void
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

    public function testcanUpgradeToReturnsTrue(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

    public function testcanUpgradeToUpgradeIsImposible(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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

    public function testcanUpgradeToSameProducts(): void
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
