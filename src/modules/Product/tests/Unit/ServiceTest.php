<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Service;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets product pairs', function (): void {
    $service = new Service();
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
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn($execArray);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getPairs($data);
    expect($result)->toBeArray();
    expect($result)->toBe($expectArray);
});

test('converts product to api array', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getStartingFromPrice');
    $expectation1->atLeast()->once();
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('getUpgradablePairs');
    $expectation2->atLeast()->once();

    $productPaymentArray = [
        'type' => 'free',
        Model_ProductPayment::FREE => ['price' => 0, 'setup' => 0],
        Model_ProductPayment::ONCE => ['price' => 1, 'setup' => 10],
        Model_ProductPayment::RECURRENT => [],
    ];
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $serviceMock->shouldReceive('toProductPaymentApiArray');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($productPaymentArray);

    $model = new Model_Product();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->product_category_id = 1;
    $model->product_payment_id = 2;
    $model->config = '{}';

    $modelProductCategory = new Model_ProductCategory();
    $modelProductCategory->loadBean(new Tests\Helpers\DummyBean());
    $modelProductCategory->type = 'free';

    $modelProductPayment = new Model_ProductPayment();

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $dbMock->shouldReceive('load');
    $expectation4->atLeast()->once();
    $expectation4->andReturnUsing(function ($type) use ($modelProductPayment, $modelProductCategory) {
        return match ($type) {
            'ProductPayment' => $modelProductPayment,
            'ProductCategory' => $modelProductCategory,
        };
    });

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);

    $model->setDi($di);
    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($model, true, new Model_Admin());
    expect($result)->toBeArray();
});

test('gets product types', function (): void {
    $service = new Service();
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

    $extensionServiceMock = Mockery::mock(Box\Mod\Extension\Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $extensionServiceMock->shouldReceive('getInstalledMods');
    $expectation->atLeast()->once();
    $expectation->andReturn($modArray);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $extensionServiceMock);

    $service->setDi($di);
    $result = $service->getTypes();
    expect($result)->toBeArray();
    expect($result)->toBe($expectedArray);
});

test('gets main domain product', function (): void {
    $service = new Service();
    $model = new Model_Product();

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('findOne');
    $expectation->atLeast()->once();
    $expectation->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getMainDomainProduct();
    expect($result)->toBeInstanceOf('\Model_Product');
});

test('gets payment types', function (): void {
    $service = new Service();
    $expected = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];

    $result = $service->getPaymentTypes();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('creates a product', function (): void {
    $service = new Service();
    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    /** @var Mockery\Expectation $expectation0 */
    $expectation0 = $systemServiceMock->shouldReceive('checkLimits');

    $modelPayment = new Model_ProductPayment();
    $modelPayment->loadBean(new Tests\Helpers\DummyBean());

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());

    $newProductId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('getCell');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(0);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('dispense');
    $expectation2->atLeast()->once();
    $expectation2->andReturnUsing(function ($type) use ($modelPayment, $modelProduct) {
        return match ($type) {
            'ProductPayment' => $modelPayment,
            'Product' => $modelProduct,
        };
    });

    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('store');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($newProductId);

    $toolMock = Mockery::mock(FOSSBilling\Tools::class);
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $toolMock->shouldReceive('slug');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemServiceMock);
    $di['db'] = $dbMock;
    $di['tools'] = $toolMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->createProduct('title', 'domain');
    expect($result)->toBeInt();
    expect($result)->toBe($newProductId);
});

test('throws exception when updating product with missing pricing type', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $typesArr = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getPaymentTypes');
    $expectation->atLeast()->once();
    $expectation->andReturn($typesArr);

    $data = ['pricing' => []];

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());

    expect(fn () => $serviceMock->updateProduct($modelProduct, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Pricing type is required');
});

test('updates a product', function (): void {
    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $typesArr = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getPaymentTypes');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($typesArr);

    $data = [
        'pricing' => [
            'type' => Model_ProductPayment::RECURRENT,
            Model_ProductPayment::RECURRENT => [
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

    $modelProductPayment = new Model_ProductPayment();
    $modelProductPayment->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('getExistingModelById');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($modelProductPayment);

    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('store');
    $expectation3->atLeast()->once();
    $expectation3->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->updateProduct($modelProduct, $data);
    expect($result)->toBeTrue();
});

test('updates priority', function (): void {
    $service = new Service();
    $data = [
        'priority' => [
            1 => 10,
            5 => 1,
        ],
    ];

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('load');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($modelProduct);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->updatePriority($data);
    expect($result)->toBeTrue();
});

test('updates config', function (): void {
    $service = new Service();
    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());
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
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('store');
    $expectation->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->updateConfig($modelProduct, $data);
    expect($result)->toBeTrue();
});

test('gets addons', function (): void {
    $service = new Service();
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
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn($addonsRows);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->getAddons();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('creates an addon', function (): void {
    $service = new Service();
    $newProductId = 1;

    $modelPayment = new Model_ProductPayment();
    $modelPayment->loadBean(new Tests\Helpers\DummyBean());

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('store');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($newProductId);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('dispense');
    $expectation2->atLeast()->once();
    $expectation2->andReturnUsing(function ($type) use ($modelPayment, $modelProduct) {
        return match ($type) {
            'ProductPayment' => $modelPayment,
            'Product' => $modelProduct,
        };
    });

    $toolMock = Mockery::mock(FOSSBilling\Tools::class);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $toolMock->shouldReceive('slug');
    $expectation3->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['tools'] = $toolMock;

    $service->setDi($di);

    $result = $service->createAddon('title');
    expect($result)->toBeInt();
    expect($result)->toBe($newProductId);
});

test('throws exception when deleting product with active orders', function (): void {
    $service = new Service();
    $model = new Model_Product();

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $orderServiceMock->shouldReceive('productHasOrders');
    $expectation->atLeast()->once();
    $expectation->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn () => $service->deleteProduct($model))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove product which has active orders.');
});

test('gets product category pairs', function (): void {
    $service = new Service();
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
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn($execArray);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getProductCategoryPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expectArray);
});

test('updates a category', function (): void {
    $service = new Service();
    $model = new Model_ProductCategory();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('store');
    $expectation->atLeast()->once();
    $expectation->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->updateCategory($model, 'title', 'decription', 'http://urltoimg.com/img.jpg');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('creates a category', function (): void {
    $service = new Service();
    $newCategoryId = 1;

    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    /** @var Mockery\Expectation $expectation0 */
    $expectation0 = $systemServiceMock->shouldReceive('checkLimits');

    $model = new Model_ProductCategory();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('dispense');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('store');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($newCategoryId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->createCategory('title');
    expect($result)->toBeInt();
    expect($result)->toBe($newCategoryId);
});

test('throws exception when removing category with products', function (): void {
    $service = new Service();
    $modelProductCategory = new Model_ProductCategory();
    $modelProductCategory->loadBean(new Tests\Helpers\DummyBean());

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('findOne');
    $expectation->atLeast()->once();
    $expectation->andReturn($modelProduct);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    expect(fn () => $service->removeProductCategory($modelProductCategory))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove product category with products');
});

test('removes a product category', function (): void {
    $service = new Service();
    $modelProductCategory = new Model_ProductCategory();
    $modelProductCategory->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('findOne');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(null);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('trash');
    $expectation2->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->removeProductCategory($modelProductCategory);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets promo search query', function (): void {
    $service = new Service();
    $data = [
        'search' => 'keyword',
        'id' => 1,
        'status' => 'active',
    ];

    $di = container();
    $service->setDi($di);

    [$sql, $params] = $service->getPromoSearchQuery($data);

    expect($sql)->toBeString();
    expect($params)->toBeArray();
});

test('creates a promo', function (): void {
    $service = new Service();
    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    /** @var Mockery\Expectation $expectation0 */
    $expectation0 = $systemServiceMock->shouldReceive('checkLimits');

    $model = new Model_Promo();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $newPromoId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('findOne');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(null);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('dispense');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($model);

    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('store');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($newPromoId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->createPromo('code', 'percentage', 50, [], [], [], []);
    expect($result)->toBeInt();
    expect($result)->toBe($newPromoId);
});

test('converts promo to api array', function (): void {
    $service = new Service();
    $model = new Model_Promo();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->products = '{}';
    $model->periods = '{}';

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('toArray');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class);

    $service->setDi($di);

    $result = $service->toPromoApiArray($model);
    expect($result)->toBeArray();
});

test('updates a promo', function (): void {
    $service = new Service();
    $model = new Model_Promo();
    $model->loadBean(new Tests\Helpers\DummyBean());

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
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('store');
    $expectation->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->updatePromo($model, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletes a promo', function (): void {
    $service = new Service();
    $model = new Model_Promo();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('exec');
    $expectation1->atLeast()->once();

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('trash');
    $expectation2->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);
    $result = $service->deletePromo($model);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets product search query', function (): void {
    $service = new Service();
    $data = [
        'search' => 'keyword',
        'type' => 'domain',
        'status' => 'active',
        'show_hidden' => true,
    ];

    $di = container();
    $service->setDi($di);

    [$sql, $params] = $service->getProductSearchQuery($data);

    expect($sql)->toBeString();
    expect($params)->toBeArray();
});

test('converts product category to api array', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $model = new Model_ProductCategory();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());
    $modelProduct->type = 'custom';
    $categoryProductsArr = [$modelProduct];

    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getCategoryProducts');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($categoryProductsArr);

    $apiArrayResult = ['price_starting_from' => 1];
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('toApiArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($apiArrayResult);

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('toArray');
    $expectation3->atLeast()->once();
    $expectation3->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->toProductCategoryApiArray($model);
    expect($result)->toBeArray();
});

test('converts product category to api array with minimum price', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $model = new Model_ProductCategory();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $modelProduct = new Model_Product();
    $modelProduct->loadBean(new Tests\Helpers\DummyBean());
    $modelProduct->type = 'custom';
    $categoryProductsArr = [$modelProduct, $modelProduct, $modelProduct, $modelProduct];

    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getCategoryProducts');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($categoryProductsArr);

    $min = 1;

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('toApiArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturnUsing(function () {
        static $count = 0;
        ++$count;

        return match ($count) {
            1 => ['price_starting_from' => 4],
            2 => ['price_starting_from' => 5],
            3 => ['price_starting_from' => 2],
            4 => ['price_starting_from' => 1],
        };
    });

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('toArray');
    $expectation3->atLeast()->once();
    $expectation3->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->toProductCategoryApiArray($model);
    expect($result)->toBeArray();
    expect($result['price_starting_from'])->toBe($min);
});

test('finds one active product by id', function (): void {
    $service = new Service();
    $model = new Model_Product();

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('findOne');
    $expectation->atLeast()->once();
    $expectation->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->findOneActiveById(1);
    expect($result)->toBeInstanceOf('\Model_Product');
});

test('finds one active product by slug', function (): void {
    $service = new Service();
    $model = new Model_Product();

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('findOne');
    $expectation->atLeast()->once();
    $expectation->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->findOneActiveBySlug('product/1');
    expect($result)->toBeInstanceOf('\Model_Product');
});

test('gets product category search query', function (): void {
    $service = new Service();
    [$sql, $params] = $service->getProductCategorySearchQuery([]);

    expect($sql)->toBeString();
    expect($params)->toBeArray();
    expect($params)->toBe([]);
});

test('gets starting from price for free product', function (): void {
    $service = new Service();
    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());
    $productModel->product_payment_id = 1;

    $productPaymentModel = new Model_ProductPayment();
    $productPaymentModel->loadBean(new Tests\Helpers\DummyBean());
    $productPaymentModel->type = 'free';

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('load');
    $expectation->atLeast()->once();
    $expectation->andReturn($productPaymentModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getStartingFromPrice($productModel);

    expect($result)->toBeInt();
    expect($result)->toBe(0);
});

test('returns null for starting price when payment not defined', function (): void {
    $service = new Service();
    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->getStartingFromPrice($productModel);

    expect($result)->toBeNull();
});

test('gets starting from price for domain type', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());
    $productModel->type = Service::DOMAIN;
    $productModel->product_payment_id = 1;

    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getStartingDomainPrice');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(10.00);

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('getStartingPrice');
    $expectation2->never();

    $result = $serviceMock->getStartingFromPrice($productModel);
    expect($result)->not()->toBeNull();
});

test('gets upgradable pairs', function (): void {
    $service = new Service();
    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());
    $productModel->upgrades = '{}';

    $expected = [];

    $result = $service->getUpgradablePairs($productModel);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets product titles by ids', function (): void {
    $service = new Service();
    $ids = ['1', '2'];

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getProductTitlesByIds($ids);
    expect($result)->toBeArray();
});

test('gets category products', function (): void {
    $service = new Service();
    $productCategoryModel = new Model_ProductCategory();
    $productCategoryModel->loadBean(new Tests\Helpers\DummyBean());

    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('find');
    $expectation->atLeast()->once();
    $expectation->andReturn([$productModel]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getCategoryProducts($productCategoryModel);
    expect($result)->toBeArray();
});

test('converts product payment to api array', function (): void {
    $service = new Service();
    $productPaymentModel = new Model_ProductPayment();
    $productPaymentModel->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->toProductPaymentApiArray($productPaymentModel);
    expect($result)->toBeArray();
});

test('gets starting price', function (): void {
    $service = new Service();
    $productPaymentModel = new Model_ProductPayment();
    $productPaymentModel->loadBean(new Tests\Helpers\DummyBean());
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

    $result = $service->getStartingPrice($productPaymentModel);
    expect($result)->toBeInt();
    expect($result)->toBe($minPrice);
});

test('checks if product can upgrade to another product', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getUpgradablePairs');
    $expectation->atLeast()->once();
    $expectation->andReturn(['2' => 'Hossting']);

    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());
    $productModel->id = 1;

    $newProductModel = new Model_Product();
    $newProductModel->loadBean(new Tests\Helpers\DummyBean());
    $newProductModel->id = 2;

    $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeTrue();
});

test('returns false when upgrade is impossible', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getUpgradablePairs');
    $expectation->atLeast()->once();
    $expectation->andReturn(['4' => 'Domain']);

    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());
    $productModel->id = 1;

    $newProductModel = new Model_Product();
    $newProductModel->loadBean(new Tests\Helpers\DummyBean());
    $newProductModel->id = 2;

    $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeFalse();
});

test('returns false when trying to upgrade to same product', function (): void {
    $service = new Service();
    $productModel = new Model_Product();
    $productModel->loadBean(new Tests\Helpers\DummyBean());
    $productModel->id = 1;

    $newProductModel = new Model_Product();
    $newProductModel->loadBean(new Tests\Helpers\DummyBean());
    $newProductModel->id = 1;

    $result = $service->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeFalse();
});
