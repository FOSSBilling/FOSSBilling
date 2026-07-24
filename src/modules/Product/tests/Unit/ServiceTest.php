<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Entity\ProductPayment;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\PromoRedemption;
use Box\Mod\Product\Repository\ProductCategoryRepository;
use Box\Mod\Product\Repository\ProductPaymentRepository;
use Box\Mod\Product\Repository\ProductRepository;
use Box\Mod\Product\Repository\PromoRedemptionRepository;
use Box\Mod\Product\Repository\PromoRepository;
use Box\Mod\Product\Service;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function productTestCreateProductEntity(int $id): Product
{
    $product = new Product();
    $reflection = new ReflectionProperty($product, 'id');
    $reflection->setValue($product, $id);

    return $product;
}

function productTestCreatePromoEntity(int $id): Promo
{
    $promo = new Promo();
    $reflection = new ReflectionProperty($promo, 'id');
    $reflection->setValue($promo, $id);

    return $promo;
}

function productTestCreateProductCategoryEntity(int $id): ProductCategory
{
    $category = new ProductCategory();
    $reflection = new ReflectionProperty($category, 'id');
    $reflection->setValue($category, $id);

    return $category;
}

function productTestCreateProductPaymentEntity(int $id): ProductPayment
{
    $productPayment = new ProductPayment();
    $reflection = new ReflectionProperty($productPayment, 'id');
    $reflection->setValue($productPayment, $id);

    return $productPayment;
}

function productTestCreateTldModel(array $properties = []): Model_Tld
{
    $tld = new Model_Tld();
    $tld->loadBean(new Tests\Helpers\DummyBean());
    foreach ($properties as $name => $value) {
        $tld->$name = $value;
    }

    return $tld;
}

function productTestCreateInvoiceModel(int $id): Model_Invoice
{
    $invoice = new Model_Invoice();
    $invoice->loadBean(new Tests\Helpers\DummyBean());
    $invoice->id = $id;

    return $invoice;
}

function productTestCreateEntityManagerWithRepositories(
    ?ProductRepository $productRepo = null,
    ?ProductPaymentRepository $paymentRepo = null,
    ?Product $persistedProduct = null,
    ?ProductPayment $persistedPayment = null,
    ?ProductCategoryRepository $categoryRepo = null,
    ?ProductCategory $persistedCategory = null,
): object {
    return new class($productRepo, $paymentRepo, $persistedProduct, $persistedPayment, $categoryRepo, $persistedCategory) {
        public int $flushCalls = 0;

        public function __construct(
            private readonly ?ProductRepository $productRepo,
            private readonly ?ProductPaymentRepository $paymentRepo,
            private readonly ?Product $persistedProduct,
            private readonly ?ProductPayment $persistedPayment,
            private readonly ?ProductCategoryRepository $categoryRepo,
            private readonly ?ProductCategory $persistedCategory,
        ) {
        }

        public function getRepository(string $class): object
        {
            return match ($class) {
                Product::class => $this->productRepo ?? throw new RuntimeException('Product repository not configured'),
                ProductPayment::class => $this->paymentRepo ?? throw new RuntimeException('ProductPayment repository not configured'),
                ProductCategory::class => $this->categoryRepo ?? throw new RuntimeException('ProductCategory repository not configured'),
                default => throw new RuntimeException('Unexpected repository ' . $class),
            };
        }

        public function persist(object $entity): void
        {
            if ($entity instanceof Product && $this->persistedProduct instanceof Product) {
                $reflection = new ReflectionProperty($entity, 'id');
                $reflection->setValue($entity, $this->persistedProduct->getId());
            }

            if ($entity instanceof ProductPayment && $this->persistedPayment instanceof ProductPayment) {
                $reflection = new ReflectionProperty($entity, 'id');
                $reflection->setValue($entity, $this->persistedPayment->getId());
            }

            if ($entity instanceof ProductCategory && $this->persistedCategory instanceof ProductCategory) {
                $reflection = new ReflectionProperty($entity, 'id');
                $reflection->setValue($entity, $this->persistedCategory->getId());
            }
        }

        public function remove(object $entity): void
        {
        }

        public function flush(): void
        {
            ++$this->flushCalls;
        }
    };
}

function productTestCreateProductPaymentEntityManager(ProductPaymentRepository $paymentRepo): object
{
    return new class($paymentRepo) {
        public int $flushCalls = 0;

        public function __construct(private readonly ProductPaymentRepository $paymentRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->paymentRepo;
        }

        public function persist(object $entity): void
        {
        }

        public function flush(): void
        {
            ++$this->flushCalls;
        }
    };
}

function productTestCreateDomainPricingDbalConnection(): Connection
{
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ]);

    $connection->executeStatement('CREATE TABLE tld_registrar (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->executeStatement('CREATE TABLE tld (
        id INTEGER PRIMARY KEY,
        tld_registrar_id INTEGER,
        tld TEXT,
        price_registration NUMERIC,
        price_renew NUMERIC,
        price_transfer NUMERIC,
        allow_register INTEGER,
        allow_transfer INTEGER,
        active INTEGER,
        min_years INTEGER
    )');
    $connection->executeStatement("INSERT INTO tld_registrar (id, name) VALUES (1, 'Registrar A')");
    $connection->executeStatement("INSERT INTO tld (id, tld_registrar_id, tld, price_registration, price_renew, price_transfer, allow_register, allow_transfer, active, min_years) VALUES (1, 1, '.com', 10.00, 12.00, 14.00, 1, 1, 1, 1)");
    $connection->executeStatement("INSERT INTO tld (id, tld_registrar_id, tld, price_registration, price_renew, price_transfer, allow_register, allow_transfer, active, min_years) VALUES (2, 1, '.net', 11.00, 13.00, 15.00, 1, 1, 0, 1)");

    return $connection;
}

function productTestCreateProductOrderDbalConnection(): Connection
{
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'memory' => true,
    ]);

    $connection->executeStatement('CREATE TABLE client_order (id INTEGER PRIMARY KEY, product_id INTEGER)');
    $connection->executeStatement('INSERT INTO client_order (id, product_id) VALUES (11, 7)');
    $connection->executeStatement('INSERT INTO client_order (id, product_id) VALUES (12, 8)');

    return $connection;
}

function productTestCreateDomainTldServiceMock(Model_Tld $tld): Mockery\MockInterface
{
    $tldService = Mockery::mock(Box\Mod\Servicedomain\ServiceTld::class);
    $tldService->shouldReceive('findOneByTld')->atLeast()->once()->with('.com')->andReturn($tld);

    return $tldService;
}

test('get pairs', function (): void {
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

    $productRepo = Mockery::mock(ProductRepository::class);
    $productRepo->shouldReceive('getPairs')->once()->with($data)->andReturn($expectArray);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepo);

    $service->setDi($di);

    $result = $service->getPairs($data);
    expect($result)->toBeArray();
    expect($result)->toEqual($expectArray);
});

test('to api array', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getStartingFromPrice')->atLeast()->once();
    $serviceMock->shouldReceive('getUpgradablePairs')->atLeast()->once();
    $productPaymentArray = [
        'type' => 'free',
        ProductPayment::FREE => ['price' => 0, 'setup' => 0],
        ProductPayment::ONCE => ['price' => 1, 'setup' => 10],
        ProductPayment::RECURRENT => [],
    ];
    $serviceMock->shouldReceive('toProductPaymentApiArray')->atLeast()->once()->andReturn($productPaymentArray);

    $model = productTestCreateProductEntity(1)
        ->setProductCategoryId(1)
        ->setProductPaymentId(2)
        ->setConfig('{}');

    $modelProductCategory = productTestCreateProductCategoryEntity(1)->setTitle('Category');

    $modelProductPayment = new ProductPayment();

    $paymentRepo = Mockery::mock(ProductPaymentRepository::class);
    $paymentRepo->shouldReceive('find')->once()->with(2)->andReturn($modelProductPayment);

    $categoryRepo = Mockery::mock(ProductCategoryRepository::class);
    $categoryRepo->shouldReceive('findById')->once()->with(1)->andReturn($modelProductCategory);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories(null, $paymentRepo, null, null, $categoryRepo);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($model, true, createEntity(Box\Mod\Staff\Entity\Admin::class));
    expect($result)->toBeArray();
});

test('get types', function (): void {
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
    $extensionServiceMock->shouldReceive('getInstalledMods')->atLeast()->once()->andReturn($modArray);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $extensionServiceMock);

    $service->setDi($di);
    $result = $service->getTypes();
    expect($result)->toBeArray();
    expect($result)->toEqual($expectedArray);
});

test('get main domain product', function (): void {
    $service = new Service();
    $model = productTestCreateProductEntity(1);
    $model->setType(Service::DOMAIN);

    $productRepository = Mockery::mock(ProductRepository::class);
    $productRepository->shouldReceive('findMainDomainProduct')->once()->andReturn($model);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepository);

    $service->setDi($di);

    $result = $service->getMainDomainProduct();
    expect($result)->toBeInstanceOf(Product::class);
});

test('get cart product title uses product service specific title', function (): void {
    $service = new Service();
    $productService = new class {
        public function getCartProductTitle(Product $product, array $config): string
        {
            return $product->getTitle() . ' ' . ($config['suffix'] ?? '');
        }
    };

    $product = productTestCreateProductEntity(1)->setType(Service::CUSTOM)->setTitle('Example');

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $serviceName): object => match ($serviceName) {
        'servicecustom' => $productService,
        default => throw new RuntimeException('Unexpected service request ' . $serviceName),
    });

    $service->setDi($di);
    expect($service->getCartProductTitle($product, ['suffix' => 'Title']))->toBe('Example Title');
});

test('get cart product title falls back to product title', function (): void {
    $service = new Service();
    $productService = new stdClass();
    $product = productTestCreateProductEntity(1)->setType(Service::CUSTOM)->setTitle('Example');

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $serviceName): object => match ($serviceName) {
        'servicecustom' => $productService,
        default => throw new RuntimeException('Unexpected service request ' . $serviceName),
    });

    $service->setDi($di);
    expect($service->getCartProductTitle($product, []))->toBe('Example');
});

test('get related product discount returns zero for non domain products', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(1)->setType(Service::CUSTOM);

    $service->setDi(container());
    expect($service->getRelatedProductDiscount($product, [['id' => 1]], ['period' => '1Y']))->toBe(0.0);
});

test('get selected addons for cart returns prepared addon items', function (): void {
    $parentProduct = productTestCreateProductEntity(10);
    $addon = productTestCreateProductEntity(20)->setStatus('enabled')->setType(Service::CUSTOM)->setIsAddon(true);

    $validator = Mockery::mock(FOSSBilling\Validate::class);
    $validator->shouldNotReceive('checkRequiredParamsForArray');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getAddonById')->once()->with(20)->andReturn($addon);
    $serviceMock->shouldReceive('isRecurrentProductPricing')->once()->with($addon)->andReturn(false);

    $di = container();
    $di['validator'] = $validator;
    $serviceMock->setDi($di);

    $result = $serviceMock->getSelectedAddonsForCart($parentProduct, [
        20 => ['selected' => true],
    ]);

    expect($result)->toHaveCount(1);
    expect($result[0]['product'])->toBe($addon);
    expect($result[0]['config']['parent_id'])->toBe(10);
});

test('reduce stock updates doctrine product state', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(1)
        ->setStockControl(true)
        ->setQuantityInStock(5);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories();
    $service->setDi($di);

    $result = $service->reduceStock($product, 2);

    expect($result)->toBeTrue();
    expect($product->getQuantityInStock())->toBe(3);
});

test('is stock available uses doctrine product state', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(1)
        ->setStockControl(true)
        ->setQuantityInStock(1);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories();
    $service->setDi($di);

    expect($service->isStockAvailable($product, 2))->toBeFalse();
});

test('get product pricing array uses product payment implementation', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(1)
        ->setType(Service::CUSTOM)
        ->setProductPaymentId(15);

    $productPayment = productTestCreateProductPaymentEntity(15)
        ->setType(ProductPayment::ONCE)
        ->setOncePrice(20.0)
        ->setOnceSetupPrice(5.0);

    $paymentRepo = Mockery::mock(ProductPaymentRepository::class);
    $paymentRepo->shouldReceive('find')->once()->with(15)->andReturn($productPayment);

    $di = container();
    $di['em'] = productTestCreateProductPaymentEntityManager($paymentRepo);
    $service->setDi($di);

    $pricing = $service->getProductPricingArray($product);

    expect($pricing['type'])->toBe(ProductPayment::ONCE);
    expect($pricing[ProductPayment::ONCE]['price'])->toBe(20.0);
    expect($pricing[ProductPayment::ONCE]['setup'])->toBe(5.0);
});

test('get product unit returns configured unit for non domain products', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(1)
        ->setType(Service::CUSTOM)
        ->setUnit('license');

    $service->setDi(container());

    expect($service->getProductUnit($product))->toBe('license');
});

test('get product order line config uses product payment pricing for recurring products', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(9)
        ->setType(Service::CUSTOM)
        ->setProductPaymentId(15);

    $productPayment = productTestCreateProductPaymentEntity(15)
        ->setType(ProductPayment::RECURRENT)
        ->setPeriodPricing('a', 20.0, 5.0, true);

    $paymentRepo = Mockery::mock(ProductPaymentRepository::class);
    $paymentRepo->shouldReceive('find')->twice()->with(15)->andReturn($productPayment);

    $di = container();
    $di['em'] = productTestCreateProductPaymentEntityManager($paymentRepo);
    $service->setDi($di);

    $line = $service->getProductOrderLineConfig($product, ['period' => '1Y', 'quantity' => 2]);

    expect($line)->toBe(['price' => 20.0, 'quantity' => 2, 'setup_price' => 5.0]);
});

test('get product renewal line config uses generic pricing implementation', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(9)
        ->setType(Service::CUSTOM)
        ->setProductPaymentId(15);

    $productPayment = productTestCreateProductPaymentEntity(15)
        ->setType(ProductPayment::RECURRENT)
        ->setPeriodPricing('a', 20.0, 5.0, true);

    $paymentRepo = Mockery::mock(ProductPaymentRepository::class);
    $paymentRepo->shouldReceive('find')->twice()->with(15)->andReturn($productPayment);

    $di = container();
    $di['em'] = productTestCreateProductPaymentEntityManager($paymentRepo);
    $service->setDi($di);

    $line = $service->getProductRenewalLineConfig($product, ['period' => '1Y', 'quantity' => 2]);

    expect($line)->toBe(['price' => 20.0, 'quantity' => 2, 'setup_price' => 5.0]);
});

test('get related product discount uses domain pricing implementation', function (): void {
    $service = new Service();
    $tld = productTestCreateTldModel([
        'tld' => '.com',
        'price_registration' => 13,
        'price_renew' => 20,
        'price_transfer' => 15,
    ]);

    $tldService = productTestCreateDomainTldServiceMock($tld);

    $di = container();
    $di['period'] = $di->protect(fn (string $period): Box_Period => new Box_Period($period));
    $di['mod_service'] = $di->protect(function (string $serviceName, ?string $sub = null) use ($tldService) {
        if ($serviceName === 'servicedomain' && $sub === 'Tld') {
            return $tldService;
        }

        throw new RuntimeException('Unexpected service request');
    });
    $service->setDi($di);

    $product = productTestCreateProductEntity(1)->setType(Service::DOMAIN);

    $discount = $service->getRelatedProductDiscount($product, [[
        'config' => [
            'action' => 'register',
            'domain' => [
                'action' => 'register',
                'register_sld' => 'example',
                'register_tld' => '.com',
            ],
            'free_domain' => true,
            'free_domain_periods' => ['2Y'],
            'period' => '2Y',
        ],
    ]], [
        'action' => 'register',
        'register_sld' => 'example',
        'register_tld' => '.com',
        'period' => '2Y',
    ]);

    expect($discount)->toBe(33.0);
});

test('get domain pricing array returns active tlds', function (): void {
    $service = new Service();
    $connection = productTestCreateDomainPricingDbalConnection();

    $di = container();
    $di['dbal'] = $connection;
    $service->setDi($di);

    $result = $service->getDomainPricingArray();

    expect($result)->toHaveKey('.com');
    expect($result)->not->toHaveKey('.net');
    expect($result['.com']['price_registration'])->toEqual(10.0);
    expect($result['.com']['registrar']['title'])->toBe('Registrar A');
});

test('get product pricing array uses domain pricing implementation', function (): void {
    $service = new Service();
    $connection = productTestCreateDomainPricingDbalConnection();

    $product = productTestCreateProductEntity(1)->setType(Service::DOMAIN);

    $di = container();
    $di['dbal'] = $connection;
    $service->setDi($di);

    $result = $service->getProductPricingArray($product);

    expect($result)->toHaveKey('.com');
    expect($result['.com']['price_registration'])->toEqual(10.0);
});

test('get product unit uses domain unit', function (): void {
    $service = new Service();
    $product = productTestCreateProductEntity(1)->setType(Service::DOMAIN);

    $service->setDi(container());

    expect($service->getProductUnit($product))->toBe('year');
});

test('get product order line config uses domain pricing implementation', function (): void {
    $service = new Service();
    $tld = productTestCreateTldModel([
        'tld' => '.com',
        'price_registration' => 13,
        'price_renew' => 20,
        'price_transfer' => 15,
    ]);

    $tldService = productTestCreateDomainTldServiceMock($tld);

    $di = container();
    $di['period'] = $di->protect(fn (string $period): Box_Period => new Box_Period($period));
    $di['mod_service'] = $di->protect(function (string $serviceName, ?string $sub = null) use ($tldService) {
        if ($serviceName === 'servicedomain' && $sub === 'Tld') {
            return $tldService;
        }

        throw new RuntimeException('Unexpected service request');
    });
    $service->setDi($di);

    $product = productTestCreateProductEntity(1)->setType(Service::DOMAIN);

    $line = $service->getProductOrderLineConfig($product, [
        'action' => 'register',
        'register_tld' => '.com',
        'register_years' => 2,
        'period' => '2Y',
    ]);

    expect($line)->toBe(['price' => 33.0, 'quantity' => 1, 'setup_price' => 0.0]);
});

test('get product renewal line config uses domain pricing implementation', function (): void {
    $service = new Service();
    $tld = productTestCreateTldModel([
        'tld' => '.com',
        'price_registration' => 13,
        'price_renew' => 20,
        'price_transfer' => 15,
    ]);

    $tldService = productTestCreateDomainTldServiceMock($tld);

    $di = container();
    $di['period'] = $di->protect(fn (string $period): Box_Period => new Box_Period($period));
    $di['mod_service'] = $di->protect(function (string $serviceName, ?string $sub = null) use ($tldService) {
        if ($serviceName === 'servicedomain' && $sub === 'Tld') {
            return $tldService;
        }

        throw new RuntimeException('Unexpected service request');
    });
    $service->setDi($di);

    $product = productTestCreateProductEntity(1)->setType(Service::DOMAIN);

    $line = $service->getProductRenewalLineConfig($product, [
        'action' => 'register',
        'register_tld' => '.com',
        'register_years' => 2,
        'period' => '2Y',
    ]);

    expect($line)->toBe(['price' => 20.0, 'quantity' => 2]);
});

test('get orders for product uses product order repository', function (): void {
    $service = new Service();
    $connection = productTestCreateProductOrderDbalConnection();

    $di = container();
    $di['dbal'] = $connection;
    $service->setDi($di);

    $product = productTestCreateProductEntity(7);
    $rows = $service->getOrdersForProduct($product);

    expect($rows)->toHaveCount(1);
    expect((int) $rows[0]['id'])->toBe(11);
    expect((int) $rows[0]['product_id'])->toBe(7);
});

test('get payment types', function (): void {
    $service = new Service();
    $expected = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];

    $result = $service->getPaymentTypes();
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('create product', function (): void {
    $service = new Service();

    $newProductId = 1;

    $toolMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolMock->shouldReceive('slug')->atLeast()->once()->andReturn('title');

    $productRepo = Mockery::mock(ProductRepository::class);
    $productRepo->shouldReceive('getMaxPriority')->once()->andReturn(0);
    $productRepo->shouldReceive('findOneBy')->once()->with(['slug' => 'title'])->andReturn(null);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepo, null, productTestCreateProductEntity($newProductId), productTestCreateProductPaymentEntity(1));
    $di['tools'] = $toolMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);
    $result = $service->createProduct('title', 'domain');
    expect($result)->toBeInt();
    expect($result)->toEqual($newProductId);
});

test('update product missing pricing type', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $typesArr = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];
    $serviceMock->shouldReceive('getPaymentTypes')->atLeast()->once()->andReturn($typesArr);

    $data = [
        'pricing' => [],
    ];

    $modelProduct = productTestCreateProductEntity(1);

    expect(fn () => $serviceMock->updateProduct($modelProduct, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Pricing type is required');
});

test('update product', function (): void {
    $modelProduct = productTestCreateProductEntity(1);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $typesArr = [
        'free' => 'Free',
        'once' => 'One time',
        'recurrent' => 'Recurrent',
    ];
    $serviceMock->shouldReceive('getPaymentTypes')->atLeast()->once()->andReturn($typesArr);

    $data = [
        'pricing' => [
            'type' => ProductPayment::RECURRENT,
            ProductPayment::RECURRENT => [
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

    $modelProduct->setProductPaymentId(1);

    $productPayment = productTestCreateProductPaymentEntity(1);

    $paymentRepo = Mockery::mock(ProductPaymentRepository::class);
    $paymentRepo->shouldReceive('find')->once()->with($productPayment->getId())->andReturn($productPayment);

    $di = container();
    $di['em'] = productTestCreateProductPaymentEntityManager($paymentRepo);
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->updateProduct($modelProduct, $data);
    expect($result)->toBeTrue();
});

test('update priority', function (): void {
    $service = new Service();
    $data = [
        'priority' => [
            1 => 10,
            5 => 1,
        ],
    ];

    $productA = productTestCreateProductEntity(1);
    $productB = productTestCreateProductEntity(5);

    $productRepo = Mockery::mock(ProductRepository::class);
    $productRepo->shouldReceive('find')->twice()->andReturnUsing(fn ($id): Product => match ($id) {
        1 => $productA,
        5 => $productB,
    });

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepo);
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->updatePriority($data);
    expect($result)->toBeTrue();
});

test('update config', function (): void {
    $service = new Service();
    $modelProduct = productTestCreateProductEntity(1)->setConfig('{"settings":5,"max":"10"}');

    $data = [
        'config' => [
            'settings' => 3,
            'max' => '',
        ],
        'new_config_name' => 'newParam',
        'new_config_value' => 'newValue',
    ];

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories();
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->updateConfig($modelProduct, $data);
    expect($result)->toBeTrue();
});

test('get addons', function (): void {
    $service = new Service();
    $expected = [
        1 => 'testTitle',
    ];

    $productRepo = Mockery::mock(ProductRepository::class);
    $productRepo->shouldReceive('getAddonPairs')->once()->andReturn($expected);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepo);
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->getAddons();
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('create addon', function (): void {
    $service = new Service();
    $newProductId = 1;

    $dbMock = Mockery::mock('\Box_Database')->shouldIgnoreMissing();

    $toolMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolMock->shouldReceive('slug')->atLeast()->once()->andReturn('title');

    $productRepo = Mockery::mock(ProductRepository::class);
    $productRepo->shouldReceive('findOneBy')->once()->with(['slug' => 'title'])->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepo, null, productTestCreateProductEntity($newProductId), productTestCreateProductPaymentEntity(1));
    $di['logger'] = new Box_Log();
    $di['tools'] = $toolMock;

    $service->setDi($di);

    $result = $service->createAddon('title');
    expect($result)->toBeInt();
    expect($result)->toEqual($newProductId);
});

test('delete product active order exception', function (): void {
    $service = new Service();
    $model = productTestCreateProductEntity(1);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('productHasOrders')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->deleteProduct($model))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove product which has active orders.');
});

test('get product category pairs', function (): void {
    $service = new Service();
    $expectArray = [
        '1' => 'title4test',
    ];

    $categoryRepo = Mockery::mock(ProductCategoryRepository::class);
    $categoryRepo->shouldReceive('getPairs')->once()->andReturn($expectArray);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories(null, null, null, null, $categoryRepo);

    $service->setDi($di);
    $result = $service->getProductCategoryPairs();
    expect($result)->toBeArray();
    expect($result)->toEqual($expectArray);
});

test('update category', function (): void {
    $service = new Service();
    $model = productTestCreateProductCategoryEntity(1);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories();
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->updateCategory($model, 'title', 'description', 'http://urltoimg.com/img.jpg');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('create category', function (): void {
    $service = new Service();
    $newCategoryId = 1;

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories(null, null, null, null, null, productTestCreateProductCategoryEntity($newCategoryId));
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->createCategory('title');

    expect($result)->toBeInt();
    expect($result)->toEqual($newCategoryId);
});

test('remove product category category has products exception', function (): void {
    $service = new Service();
    $modelProductCategory = productTestCreateProductCategoryEntity(1);
    $productRepository = Mockery::mock(ProductRepository::class);
    $productRepository->shouldReceive('hasProductsInCategory')->once()->with((int) $modelProductCategory->getId())->andReturn(true);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepository);

    $service->setDi($di);

    expect(fn (): bool => $service->removeProductCategory($modelProductCategory))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove product category with products');
});

test('remove product category', function (): void {
    $service = new Service();
    $modelProductCategory = productTestCreateProductCategoryEntity(1);
    $productRepository = Mockery::mock(ProductRepository::class);
    $productRepository->shouldReceive('hasProductsInCategory')->once()->with((int) $modelProductCategory->getId())->andReturn(false);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepository);
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->removeProductCategory($modelProductCategory);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('create promo', function (): void {
    $service = new Service();

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('findOneBy')->once()->with(['code' => 'code'])->andReturn(null);

    $emMock = new readonly class($promoRepo) {
        public function __construct(private object $promoRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->promoRepo;
        }

        public function persist(object $entity): void
        {
            $reflection = new ReflectionProperty($entity, 'id');
            $reflection->setValue($entity, 1);
        }

        public function flush(): void
        {
        }
    };

    $di = container();
    $di['logger'] = new Box_Log();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->createPromo('code', 'percentage', 50, [], [], [], []);
    expect($result)->toBeInt();
    expect($result)->toEqual(1);
});

test('to promo api array', function (): void {
    $service = new Service();
    $model = productTestCreatePromoEntity(1)
        ->setProducts('{}')
        ->setPeriods('{}');

    $repoMock = Mockery::mock(PromoRedemptionRepository::class);
    $repoMock->shouldReceive('countByPromoId')->once()->with(1)->andReturn(0);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('countLinkedOrdersByPromoId')->once()->with(1)->andReturn(0);

    $emMock = new readonly class($promoRepo, $repoMock) {
        public function __construct(private object $promoRepo, private object $redemptionRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }
    };

    $di = container();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class)->shouldIgnoreMissing();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->toPromoApiArray($model);
    expect($result)->toBeArray();
});

test('to promo api array includes usage stats for deep requests', function (): void {
    $service = new Service();
    $model = productTestCreatePromoEntity(1)
        ->setUsed(5)
        ->setMaxUses(10)
        ->setProducts('{}')
        ->setPeriods('{}');

    $repoMock = Mockery::mock(PromoRedemptionRepository::class);
    $repoMock->shouldReceive('getUsageStatsByPromoId')->once()->with(1)->andReturn([
        'recorded_applications' => 7,
        'checkout_applications' => 5,
        'renewal_applications' => 2,
        'active_checkout_applications' => 4,
        'reserved_applications' => 1,
        'committed_applications' => 5,
        'released_applications' => 1,
        'distinct_clients' => 3,
        'orders_using_promo' => 4,
    ]);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldNotReceive('countLinkedOrdersByPromoId');

    $emMock = new readonly class($promoRepo, $repoMock) {
        public function __construct(private object $promoRepo, private object $redemptionRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }
    };

    $di = container();
    $di['tools'] = Mockery::mock(FOSSBilling\Tools::class)->shouldIgnoreMissing();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->toPromoApiArray($model, true);
    expect($result['redemption_count'])->toBe(7);
    expect($result['usage_stats']['operational_use_count'])->toBe(5);
    expect($result['usage_stats']['max_uses'])->toBe(10);
    expect($result['usage_stats']['remaining_operational_uses'])->toBe(5);
    expect($result['usage_stats']['checkout_applications'])->toBe(5);
    expect($result['usage_stats']['renewal_applications'])->toBe(2);
    expect($result['usage_stats']['active_checkout_applications'])->toBe(4);
    expect($result['usage_stats']['reserved_applications'])->toBe(1);
    expect($result['usage_stats']['committed_applications'])->toBe(5);
    expect($result['usage_stats']['released_applications'])->toBe(1);
    expect($result['usage_stats']['distinct_clients'])->toBe(3);
    expect($result['usage_stats']['orders_using_promo'])->toBe(4);
});

test('client has active promo application', function (): void {
    $service = new Service();
    $promo = productTestCreatePromoEntity(5);

    $client = createEntity(Client::class, ['id' => 9]);

    $repoMock = Mockery::mock(PromoRedemptionRepository::class);
    $repoMock->shouldReceive('clientHasActiveCheckoutApplication')->once()->with(5, 9)->andReturn(true);

    $emMock = new class($repoMock) {
        public function __construct(private $repo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->repo;
        }
    };

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    expect($service->clientHasActivePromoApplication($client, $promo))->toBeTrue();
});

test('promo can be applied', function (Promo $promo, bool $expectedResult): void {
    $service = new Service();
    expect($service->promoCanBeApplied($promo))->toBe($expectedResult);
})->with([
    'inactive' => [fn (): Promo => productTestCreatePromoEntity(1)->setActive(false), false],
    'max uses reached' => [fn (): Promo => productTestCreatePromoEntity(2)->setActive(true)->setMaxUses(5)->setUsed(5), false],
    'not yet started' => [fn (): Promo => productTestCreatePromoEntity(3)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('tomorrow')), false],
    'already ended' => [fn (): Promo => productTestCreatePromoEntity(4)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('yesterday'))->setEndAt(new DateTime('yesterday')), false],
    'within window' => [fn (): Promo => productTestCreatePromoEntity(5)->setActive(true)->setMaxUses(10)->setUsed(5)->setStartAt(new DateTime('yesterday'))->setEndAt(new DateTime('tomorrow')), true],
]);

test('can client use promo returns false when promo cannot be applied', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('promoCanBeApplied')->once()->andReturn(false);

    $promo = productTestCreatePromoEntity(1);

    $client = createEntity(Client::class);

    expect($serviceMock->canClientUsePromo($client, $promo))->toBeFalse();
});

test('can client use promo returns true when promo is not once per client', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('promoCanBeApplied')->once()->andReturn(true);
    $serviceMock->shouldNotReceive('clientHasActivePromoApplication');

    $promo = productTestCreatePromoEntity(1)
        ->setOncePerClient(false);

    $client = createEntity(Client::class);

    expect($serviceMock->canClientUsePromo($client, $promo))->toBeTrue();
});

test('can client use promo returns false when client already has active promo application', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('promoCanBeApplied')->once()->andReturn(true);

    $promo = productTestCreatePromoEntity(1)
        ->setOncePerClient(true);

    $client = createEntity(Client::class);

    $serviceMock->shouldReceive('clientHasActivePromoApplication')->once()->with($client, $promo)->andReturn(true);

    expect($serviceMock->canClientUsePromo($client, $promo))->toBeFalse();
});

test('use promo', function (): void {
    $service = new Service();
    $promo = productTestCreatePromoEntity(12);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('incrementUsageIfAvailable')->once()->with(12, Mockery::type(DateTimeInterface::class))->andReturn(1);

    $emMock = new readonly class($promoRepo) {
        public function __construct(private object $promoRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->promoRepo;
        }
    };

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $service->usePromo($promo);
    expect(true)->toBeTrue();
});

test('use promo limit reached', function (): void {
    $service = new Service();
    $promo = productTestCreatePromoEntity(12);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('incrementUsageIfAvailable')->once()->andReturn(0);

    $emMock = new readonly class($promoRepo) {
        public function __construct(private object $promoRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->promoRepo;
        }
    };

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect(fn () => $service->usePromo($promo))
        ->toThrow(FOSSBilling\InformationException::class, 'This promo code has reached its maximum number of uses.');
});

test('reserve promo for order', function (): void {
    $service = new Service();
    $promo = productTestCreatePromoEntity(1)
        ->setRecurring(true);

    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('usePromo')->once()->with($promo);

    $di = container();
    $di['em'] = new readonly class {
        public function persist(object $entity): void
        {
        }

        public function flush(): void
        {
        }
    };
    $serviceMock->setDi($di);

    $serviceMock->reservePromoForOrder($promo, $order);

    expect($order->isPromoRecurring())->toBeTrue();
    expect($order->getPromoUsed())->toBe(1);
});

test('create checkout promo redemptions persists each order and flushes once', function (): void {
    $service = new Service();
    $promo = productTestCreatePromoEntity(4);

    $client = createEntity(Client::class, ['id' => 8]);

    $invoice = productTestCreateInvoiceModel(16);

    $firstOrder = createEntity(Box\Mod\Order\Entity\Order::class, [
        'id' => 11,
        'discount' => 5.0,
        'currency' => 'USD',
        'created_at' => '2026-01-01 12:00:00',
    ]);

    $secondOrder = createEntity(Box\Mod\Order\Entity\Order::class, [
        'id' => 12,
        'discount' => 7.0,
        'currency' => 'USD',
        'created_at' => '2026-01-01 12:05:00',
    ]);

    $emMock = new class {
        public array $persisted = [];
        public int $flushCalls = 0;

        public function persist(object $entity): void
        {
            $this->persisted[] = $entity;
        }

        public function flush(): void
        {
            ++$this->flushCalls;
        }
    };

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $service->createCheckoutPromoRedemptions(
        $promo,
        $client,
        [$firstOrder, $secondOrder],
        $invoice,
        PromoRedemption::STATUS_RESERVED,
    );

    expect($emMock->persisted)->toHaveCount(2);
    expect($emMock->flushCalls)->toBe(1);
    expect($emMock->persisted[0]->getClientOrderId())->toBe(11);
    expect($emMock->persisted[1]->getClientOrderId())->toBe(12);
});

test('find active promo by code', function (): void {
    $service = new Service();
    $promoEntity = new Promo();
    $promoEntity->setCode('CODE');
    $reflection = new ReflectionProperty($promoEntity, 'id');
    $reflection->setValue($promoEntity, 14);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('findActiveByCode')->once()->with('CODE')->andReturn($promoEntity);

    $emMock = new readonly class($promoRepo) {
        public function __construct(private object $promoRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->promoRepo;
        }
    };

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->findActivePromoByCode('CODE'))->toBe($promoEntity);
});

test('get promo discount title', function (): void {
    $service = new Service();
    $promo = productTestCreatePromoEntity(1)
        ->setCode('PROMO')
        ->setType(Promo::ABSOLUTE)
        ->setValue(5.0);

    $apiGuest = new class {
        public function currency_format(array $data): string
        {
            return $data['code'] . ' ' . $data['price'];
        }
    };

    $di = container();
    $di['api_guest'] = $apiGuest;
    $service->setDi($di);

    expect($service->getPromoDiscountTitle($promo, 'USD'))
        ->toBe('Promotional Code: PROMO - USD 5 Discount');
});

test('get renewal promo adjustment for domain order', function (): void {
    $service = new Service();
    $order = createEntity(Box\Mod\Order\Entity\Order::class, [
        'promo_id' => 15,
        'promo_recurring' => true,
        'product_id' => 17,
        'discount' => 2.0,
        'currency' => 'EUR',
        'config' => json_encode(['period' => '1Y']),
    ]);

    // The Order entity uses isPromoRecurring() but production code accesses ->promo_recurring.
    // The proxy __get can't find getPromoRecurring(), so set the _extra fallback.
    $ref = new ReflectionProperty($order, '_extra');
    $extra = $ref->getValue($order);
    $extra['promo_recurring'] = true;
    $ref->setValue($order, $extra);

    $product = productTestCreateProductEntity(17)->setType(Service::DOMAIN);

    $promoEntity = productTestCreatePromoEntity(15)
        ->setCode('PROMO')
        ->setType(Promo::ABSOLUTE)
        ->setValue(5.0);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldNotReceive('find');

    $currencyRepository = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepository->shouldReceive('getRateByCode')->once()->with('EUR')->andReturn(2.0);

    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getCurrencyRepository')->once()->andReturn($currencyRepository);

    $apiGuest = new class {
        public function currency_format(array $data): string
        {
            return $data['code'] . ' ' . $data['price'];
        }
    };

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('findPromoById')->once()->with(15)->andReturn($promoEntity);
    $serviceMock->shouldReceive('findProductById')->once()->with((int) $order->product_id)->andReturn($product);
    $serviceMock->shouldReceive('getRenewalProductDiscount')->once()->with($product, $promoEntity, ['period' => '1Y'])->andReturn(5.0);

    $di = container();
    $di['api_guest'] = $apiGuest;
    $di['em'] = new readonly class($promoRepo) {
        public function __construct(private object $promoRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return match ($class) {
                Promo::class => $this->promoRepo,
                default => throw new RuntimeException('Unexpected repository ' . $class),
            };
        }
    };
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $currencyService);
    $serviceMock->setDi($di);

    $result = $serviceMock->getRenewalPromoAdjustment($order, 20.0, 1.0);

    expect($result['promo'])->toBe($promoEntity);
    expect($result['discount_amount'])->toBe(10.0);
    expect($result['title'])->toBe('Promotional Code: PROMO - EUR 5 Discount');
    expect($result['currency'])->toBe('EUR');
});

test('get product discount uses product order line config', function (): void {
    $service = new Service();
    $promo = new Promo();
    $promo->setType(Promo::PERCENTAGE);
    $promo->setValue(10);

    $product = productTestCreateProductEntity(5)->setIsAddon(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getProductOrderLineConfig')->once()->with($product, ['period' => '1Y'])->andReturn([
        'price' => 100.0,
        'quantity' => 2,
    ]);

    expect($serviceMock->getProductDiscount($product, $promo, ['period' => '1Y']))->toBe(20.0);
});

test('get renewal product discount uses product renewal line config', function (): void {
    $service = new Service();
    $promo = new Promo();
    $promo->setType(Promo::PERCENTAGE);
    $promo->setValue(25);

    $product = productTestCreateProductEntity(5)->setIsAddon(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getProductRenewalLineConfig')->once()->with($product, ['period' => '1Y'])->andReturn([
        'price' => 40.0,
        'quantity' => 2,
    ]);

    expect($serviceMock->getRenewalProductDiscount($product, $promo, ['period' => '1Y']))->toBe(20.0);
});

test('is promo available for client group', function (Promo $promo, ?Client $client, bool $expectedResult): void {
    $service = new Service();
    $di = container();
    $di['loggedin_client'] = $client;
    $service->setDi($di);

    expect($service->isPromoAvailableForClientGroup($promo))->toBe($expectedResult);
})->with([
    'no restrictions' => [fn (): Promo => productTestCreatePromoEntity(1)->setClientGroups(json_encode([])), fn (): Client => $client = createEntity(Client::class), true],
    'restricted and no client group' => [fn (): Promo => productTestCreatePromoEntity(2)->setClientGroups(json_encode([1, 2])), function () {
        $client = createEntity(Client::class, ['client_group_id' => null]);

        return $client;
    }, false],
    'restricted and wrong client group' => [fn (): Promo => productTestCreatePromoEntity(3)->setClientGroups(json_encode([1, 2])), function () {
        $client = createEntity(Client::class, ['client_group_id' => 3]);

        return $client;
    }, false],
    'restricted and matching client group' => [fn (): Promo => productTestCreatePromoEntity(4)->setClientGroups(json_encode([1, 2])), function () {
        $client = createEntity(Client::class, ['client_group_id' => 2]);

        return $client;
    }, true],
    'no restrictions and no client' => [fn (): Promo => productTestCreatePromoEntity(5)->setClientGroups(json_encode([])), null, true],
    'restricted and no client' => [fn (): Promo => productTestCreatePromoEntity(6)->setClientGroups(json_encode([1, 2])), null, false],
]);

test('release reserved promo redemptions for invoice releases reservations and decrements operational counter', function (): void {
    $service = new Service();
    $invoice = productTestCreateInvoiceModel(11);

    $checkoutRedemption = (new PromoRedemption())
        ->setPromoId(7)
        ->setPhase(PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(PromoRedemption::STATUS_RESERVED);
    $secondCheckoutRedemption = (new PromoRedemption())
        ->setPromoId(7)
        ->setPhase(PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(PromoRedemption::STATUS_RESERVED);
    $renewalRedemption = (new PromoRedemption())
        ->setPromoId(7)
        ->setPhase(PromoRedemption::PHASE_RENEWAL)
        ->setStatus(PromoRedemption::STATUS_RESERVED);

    $repoMock = Mockery::mock(PromoRedemptionRepository::class);
    $repoMock->shouldReceive('findBy')->once()->with([
        'invoiceId' => 11,
        'status' => PromoRedemption::STATUS_RESERVED,
    ])->andReturn([$checkoutRedemption, $secondCheckoutRedemption, $renewalRedemption]);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('decrementUsage')->once()->with(7, 2, Mockery::type(DateTimeInterface::class));

    $di = container();
    $di['em'] = new class($promoRepo, $repoMock) {
        public int $flushCalls = 0;

        public function __construct(private readonly object $promoRepo, private readonly object $redemptionRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }

        public function flush(): void
        {
            ++$this->flushCalls;
        }
    };

    $service->setDi($di);
    $service->releaseReservedPromoRedemptionsForInvoice($invoice, 'invoice_deleted');

    expect($checkoutRedemption->getStatus())->toBe(PromoRedemption::STATUS_RELEASED);
    expect($renewalRedemption->getStatus())->toBe(PromoRedemption::STATUS_RELEASED);
    expect($checkoutRedemption->getReleaseReason())->toBe('invoice_deleted');
    expect($di['em']->flushCalls)->toBe(1);
});

test('compensateCheckoutPromoFailure deletes orphaned redemptions and decrements usage', function (): void {
    $service = new Service();

    $promo = productTestCreatePromoEntity(7)->setCode('COMPENSATE');

    $redemption = new PromoRedemption();
    $redemption->setPromoId(7);
    $redemption->setClientOrderId(42);
    $redemption->setStatus(PromoRedemption::STATUS_COMMITTED);

    $redemptionRepo = Mockery::mock(PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('findBy')
        ->once()
        ->with(['promoId' => 7, 'clientOrderId' => [42, 43]])
        ->andReturn([$redemption]);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('decrementUsage')
        ->once()
        ->with(7, 1, Mockery::type(DateTimeInterface::class));

    $emMock = new class($promoRepo, $redemptionRepo) {
        public int $removeCalls = 0;

        public function __construct(
            private readonly object $promoRepo,
            private readonly object $redemptionRepo,
        ) {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }

        public function remove(object $entity): void
        {
            ++$this->removeCalls;
        }

        public function flush(): void
        {
        }
    };

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $service->compensateCheckoutPromoFailure($promo, [42, 43], 2);

    expect($emMock->removeCalls)->toBe(1);
});

test('update promo', function (): void {
    $service = new Service();
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

    $promoEntity = new Promo();
    $promoEntity->setCode('OLD');
    $reflection = new ReflectionProperty($promoEntity, 'id');
    $reflection->setValue($promoEntity, 1);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('findOneBy')->once()->with(['code' => 'GO'])->andReturn(null);

    $emMock = new readonly class($promoRepo) {
        public function __construct(private object $promoRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->promoRepo;
        }

        public function flush(): void
        {
        }
    };

    $di = container();
    $di['logger'] = new Box_Log();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->updatePromo($promoEntity, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('delete promo', function (): void {
    $service = new Service();
    $promoEntity = productTestCreatePromoEntity(1)
        ->setCode('PROMO');

    $redemptionRepo = Mockery::mock(PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('countByPromoId')->once()->with(1)->andReturn(0);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('countLinkedOrdersByPromoId')->once()->with(1)->andReturn(0);

    $emMock = new readonly class($promoRepo, $redemptionRepo) {
        public function __construct(private object $promoRepo, private object $redemptionRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }

        public function remove(object $entity): void
        {
            assert($entity instanceof Promo);
        }

        public function flush(): void
        {
        }
    };

    $di = container();
    $di['db'] = Mockery::mock('\Box_Database')->shouldIgnoreMissing();
    $di['logger'] = new Box_Log();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->deletePromo($promoEntity);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('delete promo blocks deletion when redemption history exists', function (): void {
    $service = new Service();
    $promoEntity = productTestCreatePromoEntity(1);

    $redemptionRepo = Mockery::mock(PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('countByPromoId')->once()->with(1)->andReturn(1);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldNotReceive('countLinkedOrdersByPromoId');

    $emMock = new readonly class($promoRepo, $redemptionRepo) {
        public function __construct(private object $promoRepo, private object $redemptionRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }
    };

    $di = container();
    $di['db'] = Mockery::mock('\Box_Database')->shouldIgnoreMissing();
    $di['em'] = $emMock;

    $service->setDi($di);

    expect(fn (): bool => $service->deletePromo($promoEntity))
        ->toThrow(FOSSBilling\InformationException::class, 'Promotions with redemption history cannot be deleted. Disable the promotion instead.');
});

test('delete promo blocks deletion when linked orders exist without ledger history', function (): void {
    $service = new Service();
    $promoEntity = productTestCreatePromoEntity(1);

    $redemptionRepo = Mockery::mock(PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('countByPromoId')->once()->with(1)->andReturn(0);

    $promoRepo = Mockery::mock(PromoRepository::class);
    $promoRepo->shouldReceive('countLinkedOrdersByPromoId')->once()->with(1)->andReturn(1);

    $emMock = new readonly class($promoRepo, $redemptionRepo) {
        public function __construct(private object $promoRepo, private object $redemptionRepo)
        {
        }

        public function getRepository(string $class): object
        {
            return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
        }
    };

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    expect(fn (): bool => $service->deletePromo($promoEntity))
        ->toThrow(FOSSBilling\InformationException::class, 'Promotions with redemption history cannot be deleted. Disable the promotion instead.');
});

test('is promo linked to tld returns true when promo has no product restrictions', function (): void {
    $service = new Service();
    $promo = new Promo();

    $tld = productTestCreateTldModel(['id' => 1]);

    expect($service->isPromoLinkedToTld($promo, $tld))->toBeTrue();
});

test('is promo linked to tld uses main domain product linkage', function (): void {
    $service = new Service();
    $promo = new Promo();
    $promo->setProducts(json_encode([10]));

    $domainProduct = productTestCreateProductEntity(10)
        ->setIsAddon(false);

    $tld = productTestCreateTldModel(['id' => 1]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getMainDomainProduct')->once()->andReturn($domainProduct);

    expect($serviceMock->isPromoLinkedToTld($promo, $tld))->toBeTrue();
});

test('is promo linked to tld returns false when domain product is not linked', function (): void {
    $service = new Service();
    $promo = new Promo();
    $promo->setProducts(json_encode([25]));

    $domainProduct = productTestCreateProductEntity(10)
        ->setIsAddon(false);

    $tld = productTestCreateTldModel(['id' => 1]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getMainDomainProduct')->once()->andReturn($domainProduct);

    expect($serviceMock->isPromoLinkedToTld($promo, $tld))->toBeFalse();
});

test('get product search query', function (): void {
    $service = new Service();
    $data = [
        'search' => 'keyword',
        'type' => 'domain',
        'status' => 'active',
        'show_hidden' => true,
    ];

    $di = container();

    $service->setDi($di);

    $repo = Mockery::mock(ProductRepository::class);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class)->shouldIgnoreMissing();
    $repo->shouldReceive('getSearchQueryBuilder')->once()->with($data)->andReturn($qb);

    $di = container();
    $di['em'] = new readonly class($repo) {
        public function __construct(private ProductRepository $repo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->repo;
        }
    };

    $service->setDi($di);

    $result = $service->getProductSearchQueryBuilder($data);

    expect($result)->toBe($qb);
});

test('to product category api array', function (): void {
    $service = new Service();
    $model = productTestCreateProductCategoryEntity(1)
        ->setTitle('Category')
        ->setDescription('Description')
        ->setIconUrl('http://urltoimg.com/img.jpg');

    $modelProduct = productTestCreateProductEntity(1)->setType('custom');
    $categoryProductsArr = [
        $modelProduct,
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive('getCategoryProducts')->atLeast()->once()->andReturn($categoryProductsArr);

    $apiArrayResult = [
        'price_starting_from' => 1,
    ];
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($apiArrayResult);

    $serviceMock->setDi(container());
    $result = $serviceMock->toProductCategoryApiArray($model);
    expect($result)->toBeArray();
});

test('to product category api array starting from value not zero', function (): void {
    $service = new Service();
    $model = productTestCreateProductCategoryEntity(1);

    $modelProduct = productTestCreateProductEntity(1)->setType('custom');
    $categoryProductsArr = [
        $modelProduct,
        $modelProduct,
        $modelProduct,
        $modelProduct,
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive('getCategoryProducts')->atLeast()->once()->andReturn($categoryProductsArr);

    $min = 1;

    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn(
        ['price_starting_from' => 4],
        ['price_starting_from' => 5],
        ['price_starting_from' => 2],
        ['price_starting_from' => $min],
    );

    $serviceMock->setDi(container());
    $result = $serviceMock->toProductCategoryApiArray($model);
    expect($result)->toBeArray();
    expect($result['price_starting_from'])->toEqual($min);
});

test('find one active by id', function (): void {
    $service = new Service();
    $model = productTestCreateProductEntity(1);

    $repo = Mockery::mock(ProductRepository::class);
    $repo->shouldReceive('findActiveById')->once()->with(1)->andReturn($model);

    $di = container();
    $di['em'] = new readonly class($repo) {
        public function __construct(private ProductRepository $repo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->repo;
        }
    };

    $service->setDi($di);
    $result = $service->findOneActiveById(1);
    expect($result)->toBeInstanceOf(Product::class);
});

test('find one active by slug', function (): void {
    $service = new Service();
    $model = productTestCreateProductEntity(1);

    $repo = Mockery::mock(ProductRepository::class);
    $repo->shouldReceive('findActiveBySlug')->once()->with('product/1')->andReturn($model);

    $di = container();
    $di['em'] = new readonly class($repo) {
        public function __construct(private ProductRepository $repo)
        {
        }

        public function getRepository(string $class): object
        {
            return $this->repo;
        }
    };

    $service->setDi($di);
    $result = $service->findOneActiveBySlug('product/1');
    expect($result)->toBeInstanceOf(Product::class);
});

test('get product category search query builder', function (): void {
    $service = new Service();
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class)->shouldIgnoreMissing();
    $categoryRepo = Mockery::mock(ProductCategoryRepository::class);
    $categoryRepo->shouldReceive('getEnabledVisibleSearchQueryBuilder')->once()->andReturn($qb);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories(null, null, null, null, $categoryRepo);

    $service->setDi($di);
    $result = $service->getProductCategorySearchQueryBuilder([]);
    expect($result)->toBe($qb);
});

test('get starting from price type free', function (): void {
    $service = new Service();
    $productModel = productTestCreateProductEntity(1)->setProductPaymentId(1);

    $productPaymentModel = productTestCreateProductPaymentEntity(1)
        ->setType(ProductPayment::FREE);

    $paymentRepo = Mockery::mock(ProductPaymentRepository::class);
    $paymentRepo->shouldReceive('find')->once()->with(1)->andReturn($productPaymentModel);

    $di = container();
    $di['em'] = productTestCreateProductPaymentEntityManager($paymentRepo);

    $service->setDi($di);
    $result = $service->getStartingFromPrice($productModel);

    expect($result)->toBeInt();
    expect($result)->toEqual('0');
});

test('get starting from price payment not defined', function (): void {
    $service = new Service();
    $productModel = productTestCreateProductEntity(1);

    $result = $service->getStartingFromPrice($productModel);

    expect($result)->toBeNull();
});

test('get starting from price domain type', function (): void {
    $service = new Service();
    $productModel = productTestCreateProductEntity(1)
        ->setType(Service::DOMAIN)
        ->setProductPaymentId(1);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getStartingDomainPrice')->atLeast()->once()->andReturn(10.00);
    $serviceMock->shouldNotReceive('getStartingPrice');

    $result = $serviceMock->getStartingFromPrice($productModel);
    expect($result)->not->toBeNull();
});

test('get upgradable pairs', function (): void {
    $service = new Service();
    $productModel = productTestCreateProductEntity(1)->setUpgrades('{}');

    $expected = [];

    $result = $service->getUpgradablePairs($productModel);
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('get product titles by ids', function (): void {
    $service = new Service();
    $ids = ['1', '2'];
    $expected = [
        1 => 'test',
        2 => 'Another',
    ];
    $productRepository = Mockery::mock(ProductRepository::class);
    $productRepository->shouldReceive('findByIds')->once()->with([1, 2])->andReturn([
        productTestCreateProductEntity(1)->setTitle('test'),
        productTestCreateProductEntity(2)->setTitle('Another'),
    ]);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepository);

    $service->setDi($di);

    $result = $service->getProductTitlesByIds($ids);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('get category products', function (): void {
    $service = new Service();
    $productCategoryModel = productTestCreateProductCategoryEntity(1);

    $productModel = productTestCreateProductEntity(1);
    $productRepository = Mockery::mock(ProductRepository::class);
    $productRepository->shouldReceive('findEnabledVisibleByCategoryId')->once()->with((int) $productCategoryModel->getId())->andReturn([$productModel]);

    $di = container();
    $di['em'] = productTestCreateEntityManagerWithRepositories($productRepository);

    $service->setDi($di);
    $result = $service->getCategoryProducts($productCategoryModel);
    expect($result)->toBeArray();
});

test('to product payment api array', function (): void {
    $service = new Service();
    $productPaymentModel = productTestCreateProductPaymentEntity(1);

    $result = $service->toProductPaymentApiArray($productPaymentModel);
    expect($result)->toBeArray();
});

test('get starting price', function (): void {
    $service = new Service();
    $productPaymentModel = productTestCreateProductPaymentEntity(1)
        ->setType(ProductPayment::RECURRENT);

    $minPrice = 1;

    $productPaymentModel->setPeriodPricing('w', 2, 0, true);
    $productPaymentModel->setPeriodPricing('m', 4, 0, true);
    $productPaymentModel->setPeriodPricing('q', 8, 0, true);
    $productPaymentModel->setPeriodPricing('b', $minPrice, 0, true);
    $productPaymentModel->setPeriodPricing('a', 10, 0, true);
    $productPaymentModel->setPeriodPricing('bia', 12, 0, true);
    $productPaymentModel->setPeriodPricing('tria', 14, 0, true);

    $result = $service->getStartingPrice($productPaymentModel);
    expect($result)->toBeNumeric();
    expect($result)->toEqual($minPrice);
});

test('can upgrade to returns true', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getUpgradablePairs')->atLeast()->once()->andReturn(['2' => 'Hosting']);

    $productModel = productTestCreateProductEntity(1);
    $newProductModel = productTestCreateProductEntity(2);

    $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeTrue();
});

test('can upgrade to upgrade is impossible', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getUpgradablePairs')->atLeast()->once()->andReturn(['4' => 'Domain']);

    $productModel = productTestCreateProductEntity(1);
    $newProductModel = productTestCreateProductEntity(2);

    $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeFalse();
});

test('can upgrade to same products', function (): void {
    $service = new Service();
    $productModel = productTestCreateProductEntity(1);
    $newProductModel = productTestCreateProductEntity(1);

    $result = $service->canUpgradeTo($productModel, $newProductModel);
    expect($result)->toBeFalse();
});

test('assert upgrade allowed by ids throws helpful exception', function (): void {
    $service = new Service();
    $currentProduct = productTestCreateProductEntity(1)
        ->setTitle('Starter');
    $upgradeProduct = productTestCreateProductEntity(2)
        ->setTitle('Pro');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getUpgradablePairsByProductId')->once()->with(1)->andReturn([]);
    $serviceMock->shouldReceive('findProductById')->twice()->andReturnUsing(fn ($id): Product => match ($id) {
        1 => $currentProduct,
        2 => $upgradeProduct,
    });

    expect(fn () => $serviceMock->assertUpgradeAllowedByIds(1, 2))
        ->toThrow(FOSSBilling\InformationException::class, 'Sorry, but "Starter" is not allowed to be upgraded to "Pro"');
});
