<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Product\Service;

beforeEach(function () {
    $this->service = new Service();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets product pairs', function () {
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($execArray);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getPairs($data);
    expect($result)->toBeArray();
    expect($result)->toBe($expectArray);
});

test('converts product to api array', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getStartingFromPrice')
        ->atLeast()->once();
    $serviceMock->shouldReceive('getUpgradablePairs')
        ->atLeast()->once();

    $productPaymentArray = [
        'type' => 'free',
        \Model_ProductPayment::FREE => ['price' => 0, 'setup' => 0],
        \Model_ProductPayment::ONCE => ['price' => 1, 'setup' => 10],
        \Model_ProductPayment::RECURRENT => [],
    ];
    $serviceMock->shouldReceive('toProductPaymentApiArray')
        ->atLeast()->once()
        ->andReturn($productPaymentArray);

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->product_category_id = 1;
    $model->product_payment_id = 2;
    $model->config = '{}';

    $modelProductCategory = new \Model_ProductCategory();
    $modelProductCategory->loadBean(new \Tests\Helpers\DummyBean());
    $modelProductCategory->type = 'free';

    $modelProductPayment = new \Model_ProductPayment();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturnUsing(function ($type) use ($modelProductPayment, $modelProductCategory) {
            return match ($type) {
                'ProductPayment' => $modelProductPayment,
                'ProductCategory' => $modelProductCategory,
            };
        });

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceMock);

    $model->setDi($di);
    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
    expect($result)->toBeArray();
});

test('gets product types', function () {
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

    $extensionServiceMock = Mockery::mock(\Box\Mod\Extension\Service::class);
    $extensionServiceMock->shouldReceive('getInstalledMods')
        ->atLeast()->once()
        ->andReturn($modArray);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extensionServiceMock);

    $this->service->setDi($di);
    $result = $this->service->getTypes();
    expect($result)->toBeArray();
    expect($result)->toBe($expectedArray);
});

test('gets main domain product', function () {
    $model = new \Model_Product();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getMainDomainProduct();
    expect($result)->toBeInstanceOf('\Model_Product');
});

test('gets payment types', function () {
    $expected = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];

    $result = $this->service->getPaymentTypes();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('creates a product', function () {
    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('checkLimits');

    $modelPayment = new \Model_ProductPayment();
    $modelPayment->loadBean(new \Tests\Helpers\DummyBean());

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    $newProductId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(0);

    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturnUsing(function ($type) use ($modelPayment, $modelProduct) {
            return match ($type) {
                'ProductPayment' => $modelPayment,
                'Product' => $modelProduct,
            };
        });

    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newProductId);

    $toolMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolMock->shouldReceive('slug');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);
    $di['db'] = $dbMock;
    $di['tools'] = $toolMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->createProduct('title', 'domain');
    expect($result)->toBeInt();
    expect($result)->toBe($newProductId);
});

test('throws exception when updating product with missing pricing type', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $typesArr = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];
    $serviceMock->shouldReceive('getPaymentTypes')
        ->atLeast()->once()
        ->andReturn($typesArr);

    $data = ['pricing' => []];

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    expect(fn () => $serviceMock->updateProduct($modelProduct, $data))
        ->toThrow(\FOSSBilling\Exception::class, 'Pricing type is required');
});

test('updates a product', function () {
    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $typesArr = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];
    $serviceMock->shouldReceive('getPaymentTypes')
        ->atLeast()->once()
        ->andReturn($typesArr);

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
    $modelProductPayment->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($modelProductPayment);

    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->updateProduct($modelProduct, $data);
    expect($result)->toBeTrue();
});

test('updates priority', function () {
    $data = [
        'priority' => [
            1 => 10,
            5 => 1,
        ],
    ];

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($modelProduct);

    $dbMock->shouldReceive('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->updatePriority($data);
    expect($result)->toBeTrue();
});

test('updates config', function () {
    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->config = '{"settings":5,"max":"10"}';

    $data = [
        'config' => [
            'settings' => 3,
            'max' => '',
        ],
        'new_config_name' => 'newParam',
        'new_config_value' => 'newValue',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->updateConfig($modelProduct, $data);
    expect($result)->toBeTrue();
});

test('gets addons', function () {
    $addonsRows = [
        [
            'id' => 1,
            'title' => 'testTitle',
        ],
    ];

    $expected = [
        1 => 'testTitle',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($addonsRows);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->getAddons();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('creates an addon', function () {
    $newProductId = 1;

    $modelPayment = new \Model_ProductPayment();
    $modelPayment->loadBean(new \Tests\Helpers\DummyBean());

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newProductId);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturnUsing(function ($type) use ($modelPayment, $modelProduct) {
            return match ($type) {
                'ProductPayment' => $modelPayment,
                'Product' => $modelProduct,
            };
        });

    $toolMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolMock->shouldReceive('slug')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['tools'] = $toolMock;

    $this->service->setDi($di);

    $result = $this->service->createAddon('title');
    expect($result)->toBeInt();
    expect($result)->toBe($newProductId);
});

test('throws exception when deleting product with active orders', function () {
    $model = new \Model_Product();

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('productHasOrders')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $this->service->setDi($di);

    expect(fn () => $this->service->deleteProduct($model))
        ->toThrow(\FOSSBilling\Exception::class, 'Cannot remove product which has active orders.');
});

test('gets product category pairs', function () {
    $execArray = [
        [
            'id' => 1,
            'title' => 'title4test',
        ],
    ];

    $expectArray = [
        '1' => 'title4test',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($execArray);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getProductCategoryPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expectArray);
});

test('updates a category', function () {
    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->updateCategory($model, 'title', 'decription', 'http://urltoimg.com/img.jpg');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('creates a category', function () {
    $newCategoryId = 1;

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('checkLimits');

    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);

    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newCategoryId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->createCategory('title');
    expect($result)->toBeInt();
    expect($result)->toBe($newCategoryId);
});

test('throws exception when removing category with products', function () {
    $modelProductCategory = new \Model_ProductCategory();
    $modelProductCategory->loadBean(new \Tests\Helpers\DummyBean());

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($modelProduct);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    expect(fn () => $this->service->removeProductCategory($modelProductCategory))
        ->toThrow(\FOSSBilling\Exception::class, 'Cannot remove product category with products');
});

test('removes a product category', function () {
    $modelProductCategory = new \Model_ProductCategory();
    $modelProductCategory->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->removeProductCategory($modelProductCategory);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets promo search query', function () {
    $data = [
        'search' => 'keyword',
        'id' => 1,
        'status' => 'active',
    ];

    $di = container();
    $this->service->setDi($di);

    [$sql, $params] = $this->service->getPromoSearchQuery($data);

    expect($sql)->toBeString();
    expect($params)->toBeArray();
});

test('creates a promo', function () {
    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('checkLimits');

    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $newPromoId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newPromoId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->createPromo('code', 'percentage', 50, [], [], [], []);
    expect($result)->toBeInt();
    expect($result)->toBe($newPromoId);
});

test('converts promo to api array', function () {
    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->products = '{}';
    $model->periods = '{}';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = Mockery::mock(\FOSSBilling\Tools::class);

    $this->service->setDi($di);

    $result = $this->service->toPromoApiArray($model);
    expect($result)->toBeArray();
});

test('updates a promo', function () {
    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->updatePromo($model, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletes a promo', function () {
    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->atLeast()->once();
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->deletePromo($model);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets product search query', function () {
    $data = [
        'search' => 'keyword',
        'type' => 'domain',
        'status' => 'active',
        'show_hidden' => true,
    ];

    $di = container();
    $this->service->setDi($di);

    [$sql, $params] = $this->service->getProductSearchQuery($data);

    expect($sql)->toBeString();
    expect($params)->toBeArray();
});

test('converts product category to api array', function () {
    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->type = 'custom';
    $categoryProductsArr = [$modelProduct];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCategoryProducts')
        ->atLeast()->once()
        ->andReturn($categoryProductsArr);

    $apiArrayResult = ['price_starting_from' => 1];
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($apiArrayResult);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->toProductCategoryApiArray($model);
    expect($result)->toBeArray();
});

test('converts product category to api array with minimum price', function () {
    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->type = 'custom';
    $categoryProductsArr = [$modelProduct, $modelProduct, $modelProduct, $modelProduct];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCategoryProducts')
        ->atLeast()->once()
        ->andReturn($categoryProductsArr);

    $min = 1;

    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturnUsing(function () {
            static $count = 0;
            $count++;
            return match ($count) {
                1 => ['price_starting_from' => 4],
                2 => ['price_starting_from' => 5],
                3 => ['price_starting_from' => 2],
                4 => ['price_starting_from' => 1],
            };
        });

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->toProductCategoryApiArray($model);
    expect($result)->toBeArray();
    expect($result['price_starting_from'])->toBe($min);
});

test('finds one active product by id', function () {
    $model = new \Model_Product();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->findOneActiveById(1);
    expect($result)->toBeInstanceOf('\Model_Product');
});

test('finds one active product by slug', function () {
    $model = new \Model_Product();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->findOneActiveBySlug('product/1');
    expect($result)->toBeInstanceOf('\Model_Product');
});

test('gets product category search query', function () {
    [$sql, $params] = $this->service->getProductCategorySearchQuery([]);

    expect($sql)->toBeString();
    expect($params)->toBeArray();
    expect($params)->toBe([]);
});

test('gets starting from price for free product', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->product_payment_id = 1;

    $productPaymentModel = new \Model_ProductPayment();
    $productPaymentModel->loadBean(new \Tests\Helpers\DummyBean());
    $productPaymentModel->type = 'free';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($productPaymentModel);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getStartingFromPrice($productModel);

    expect($result)->toBeInt();
    expect($result)->toBe(0);
});

test('returns null for starting price when payment not defined', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->getStartingFromPrice($productModel);

    expect($result)->toBeNull();
});

test('gets starting from price for domain type', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->type = Service::DOMAIN;
    $productModel->product_payment_id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getStartingDomainPrice')
        ->atLeast()->once()
        ->andReturn(10.00);
    $serviceMock->shouldReceive('getStartingPrice')
        ->never();

    $result = $serviceMock->getStartingFromPrice($productModel);
    expect($result)->not()->toBeNull();
});

test('gets upgradable pairs', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->upgrades = '{}';

    $expected = [];

    $result = $this->service->getUpgradablePairs($productModel);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets product titles by ids', function () {
    $ids = ['1', '2'];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getProductTitlesByIds($ids);
    expect($result)->toBeArray();
});

test('gets category products', function () {
    $productCategoryModel = new \Model_ProductCategory();
    $productCategoryModel->loadBean(new \Tests\Helpers\DummyBean());

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$productModel]);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getCategoryProducts($productCategoryModel);
    expect($result)->toBeArray();
});

test('converts product payment to api array', function () {
    $productPaymentModel = new \Model_ProductPayment();
    $productPaymentModel->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->toProductPaymentApiArray($productPaymentModel);
    expect($result)->toBeArray();
});

test('gets starting price', function () {
    $productPaymentModel = new \Model_ProductPayment();
    $productPaymentModel->loadBean(new \Tests\Helpers\DummyBean());
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
    expect($result)->toBeInt();
    expect($result)->toBe($minPrice);
});

test('checks if product can upgrade to another product', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getUpgradablePairs')
        ->atLeast()->once()
        ->andReturn(['2' => 'Hossting']);

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->id = 1;

    $newProductModel = new \Model_Product();
    $newProductModel->loadBean(new \Tests\Helpers\DummyBean());
    $newProductModel->id = 2;

    $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeTrue();
});

test('returns false when upgrade is impossible', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getUpgradablePairs')
        ->atLeast()->once()
        ->andReturn(['4' => 'Domain']);

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->id = 1;

    $newProductModel = new \Model_Product();
    $newProductModel->loadBean(new \Tests\Helpers\DummyBean());
    $newProductModel->id = 2;

    $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeFalse();
});

test('returns false when trying to upgrade to same product', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->id = 1;

    $newProductModel = new \Model_Product();
    $newProductModel->loadBean(new \Tests\Helpers\DummyBean());
    $newProductModel->id = 1;

    $result = $this->service->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeFalse();
});
