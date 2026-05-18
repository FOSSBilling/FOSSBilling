<?php

declare(strict_types=1);

namespace Box\Mod\Product;

use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Entity\ProductPayment;
use Box\Mod\Product\Entity\PromoRedemption;
use Box\Mod\Product\Repository\ProductCategoryRepository;
use Box\Mod\Product\Repository\ProductRepository;
use Box\Mod\Product\Repository\ProductPaymentRepository;
use Box\Mod\Product\Repository\PromoRepository;
use Box\Mod\Product\Repository\PromoRedemptionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
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

        $productRepo = $this->createMock(ProductRepository::class);
        $productRepo->expects($this->once())
            ->method('getPairs')
            ->with($data)
            ->willReturn($expectArray);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepo);

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
            ProductPayment::FREE => ['price' => 0, 'setup' => 0],
            ProductPayment::ONCE => ['price' => 1, 'setup' => 10],
            ProductPayment::RECURRENT => [],
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('toProductPaymentApiArray')
            ->willReturn($productPaymentArray);

        $model = $this->createProductEntity(1)
            ->setProductCategoryId(1)
            ->setProductPaymentId(2)
            ->setConfig('{}');

        $modelProductCategory = $this->createProductCategoryEntity(1)->setTitle('Category');

        $modelProductPayment = new ProductPayment();

        $paymentRepo = $this->createMock(ProductPaymentRepository::class);
        $paymentRepo->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($modelProductPayment);

        $categoryRepo = $this->createMock(ProductCategoryRepository::class);
        $categoryRepo->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($modelProductCategory);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories(null, $paymentRepo, null, null, $categoryRepo);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testGetTypes(): void
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

        $extensionServiceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getInstalledMods')
            ->willReturn($modArray);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $extensionServiceMock);

        $this->service->setDi($di);
        $result = $this->service->getTypes();
        $this->assertIsArray($result);
        $this->assertEquals($expectedArray, $result);
    }

    public function testGetMainDomainProduct(): void
    {
        $model = $this->createProductEntity(1);
        $model->setType(Service::DOMAIN);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())
            ->method('findMainDomainProduct')
            ->willReturn($model);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepository);

        $this->service->setDi($di);

        $result = $this->service->getMainDomainProduct();
        $this->assertInstanceOf(Product::class, $result);
    }

    public function testGetCartProductTitleUsesProductServiceSpecificTitle(): void
    {
        $productService = new class {
            public function getCartProductTitle(Product $product, array $config): string
            {
                return $product->getTitle() . ' ' . ($config['suffix'] ?? '');
            }
        };

        $product = $this->createProductEntity(1)->setType(Service::CUSTOM)->setTitle('Example');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $serviceName): object => match ($serviceName) {
            'servicecustom' => $productService,
            default => throw new \RuntimeException('Unexpected service request ' . $serviceName),
        });

        $this->service->setDi($di);
        $this->assertSame('Example Title', $this->service->getCartProductTitle($product, ['suffix' => 'Title']));
    }

    public function testGetCartProductTitleFallsBackToProductTitle(): void
    {
        $productService = new \stdClass();
        $product = $this->createProductEntity(1)->setType(Service::CUSTOM)->setTitle('Example');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $serviceName): object => match ($serviceName) {
            'servicecustom' => $productService,
            default => throw new \RuntimeException('Unexpected service request ' . $serviceName),
        });

        $this->service->setDi($di);
        $this->assertSame('Example', $this->service->getCartProductTitle($product, []));
    }

    public function testGetRelatedProductDiscountReturnsZeroForNonDomainProducts(): void
    {
        $product = $this->createProductEntity(1)->setType(Service::CUSTOM);

        $this->service->setDi($this->getDi());
        $this->assertSame(0.0, $this->service->getRelatedProductDiscount($product, [['id' => 1]], ['period' => '1Y']));
    }

    public function testGetSelectedAddonsForCartReturnsPreparedAddonItems(): void
    {
        $parentProduct = $this->createProductEntity(10);
        $addon = $this->createProductEntity(20)->setStatus('enabled')->setType(Service::CUSTOM)->setIsAddon(true);

        $validator = $this->getMockBuilder(\FOSSBilling\Validate::class)
            ->disableOriginalConstructor()
            ->getMock();
        $validator->expects($this->never())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getAddonById', 'isRecurrentProductPricing'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getAddonById')
            ->with(20)
            ->willReturn($addon);
        $serviceMock->expects($this->once())
            ->method('isRecurrentProductPricing')
            ->with($addon)
            ->willReturn(false);

        $di = $this->getDi();
        $di['validator'] = $validator;
        $serviceMock->setDi($di);

        $result = $serviceMock->getSelectedAddonsForCart($parentProduct, [
            20 => ['selected' => true],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame($addon, $result[0]['product']);
        $this->assertSame(10, $result[0]['config']['parent_id']);
    }

    public function testReduceStockUpdatesDoctrineProductState(): void
    {
        $product = $this->createProductEntity(1)
            ->setStockControl(true)
            ->setQuantityInStock(5);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories();
        $this->service->setDi($di);

        $result = $this->service->reduceStock($product, 2);

        $this->assertTrue($result);
        $this->assertSame(3, $product->getQuantityInStock());
    }

    public function testIsStockAvailableUsesDoctrineProductState(): void
    {
        $product = $this->createProductEntity(1)
            ->setStockControl(true)
            ->setQuantityInStock(1);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories();
        $this->service->setDi($di);

        $this->assertFalse($this->service->isStockAvailable($product, 2));
    }

    public function testGetProductPricingArrayUsesProductPaymentImplementation(): void
    {
        $product = $this->createProductEntity(1)
            ->setType(Service::CUSTOM)
            ->setProductPaymentId(15);

        $productPayment = $this->createProductPaymentEntity(15)
            ->setType(ProductPayment::ONCE)
            ->setOncePrice(20.0)
            ->setOnceSetupPrice(5.0);

        $paymentRepo = $this->createMock(ProductPaymentRepository::class);
        $paymentRepo->expects($this->once())
            ->method('find')
            ->with(15)
            ->willReturn($productPayment);

        $di = $this->getDi();
        $di['em'] = $this->createProductPaymentEntityManager($paymentRepo);
        $this->service->setDi($di);

        $pricing = $this->service->getProductPricingArray($product);

        $this->assertSame(ProductPayment::ONCE, $pricing['type']);
        $this->assertSame(20.0, $pricing[ProductPayment::ONCE]['price']);
        $this->assertSame(5.0, $pricing[ProductPayment::ONCE]['setup']);
    }

    public function testGetProductUnitReturnsConfiguredUnitForNonDomainProducts(): void
    {
        $product = $this->createProductEntity(1)
            ->setType(Service::CUSTOM)
            ->setUnit('license');

        $this->service->setDi($this->getDi());

        $this->assertSame('license', $this->service->getProductUnit($product));
    }

    public function testGetProductOrderLineConfigUsesProductPaymentPricingForRecurringProducts(): void
    {
        $product = $this->createProductEntity(9)
            ->setType(Service::CUSTOM)
            ->setProductPaymentId(15);

        $productPayment = $this->createProductPaymentEntity(15)
            ->setType(ProductPayment::RECURRENT)
            ->setPeriodPricing('a', 20.0, 5.0, true);

        $paymentRepo = $this->createMock(ProductPaymentRepository::class);
        $paymentRepo->expects($this->exactly(2))
            ->method('find')
            ->with(15)
            ->willReturn($productPayment);

        $di = $this->getDi();
        $di['em'] = $this->createProductPaymentEntityManager($paymentRepo);
        $this->service->setDi($di);

        $line = $this->service->getProductOrderLineConfig($product, ['period' => '1Y', 'quantity' => 2]);

        $this->assertSame(['price' => 20.0, 'quantity' => 2, 'setup_price' => 5.0], $line);
    }

    public function testGetProductRenewalLineConfigUsesGenericPricingImplementation(): void
    {
        $product = $this->createProductEntity(9)
            ->setType(Service::CUSTOM)
            ->setProductPaymentId(15);

        $productPayment = $this->createProductPaymentEntity(15)
            ->setType(ProductPayment::RECURRENT)
            ->setPeriodPricing('a', 20.0, 5.0, true);

        $paymentRepo = $this->createMock(ProductPaymentRepository::class);
        $paymentRepo->expects($this->exactly(2))
            ->method('find')
            ->with(15)
            ->willReturn($productPayment);

        $di = $this->getDi();
        $di['em'] = $this->createProductPaymentEntityManager($paymentRepo);
        $this->service->setDi($di);

        $line = $this->service->getProductRenewalLineConfig($product, ['period' => '1Y', 'quantity' => 2]);

        $this->assertSame(['price' => 20.0, 'quantity' => 2, 'setup_price' => 5.0], $line);
    }

    public function testGetRelatedProductDiscountUsesDomainPricingImplementation(): void
    {
        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->tld = '.com';
        $tld->price_registration = 13;
        $tld->price_renew = 20;
        $tld->price_transfer = 15;

        $tldService = $this->createDomainTldServiceMock($tld);

        $di = $this->getDi();
        $di['period'] = $di->protect(fn (string $period): \Box_Period => new \Box_Period($period));
        $di['mod_service'] = $di->protect(function (string $serviceName, ?string $sub = null) use ($tldService) {
            if ($serviceName === 'servicedomain' && $sub === 'Tld') {
                return $tldService;
            }

            throw new \RuntimeException('Unexpected service request');
        });
        $this->service->setDi($di);

        $product = $this->createProductEntity(1)->setType(Service::DOMAIN);

        $discount = $this->service->getRelatedProductDiscount($product, [[
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

        $this->assertSame(33.0, $discount);
    }

    public function testGetDomainPricingArrayReturnsActiveTlds(): void
    {
        $connection = $this->createDomainPricingDbalConnection();

        $di = $this->getDi();
        $di['dbal'] = $connection;
        $this->service->setDi($di);

        $result = $this->service->getDomainPricingArray();

        $this->assertArrayHasKey('.com', $result);
        $this->assertArrayNotHasKey('.net', $result);
        $this->assertEquals(10.0, $result['.com']['price_registration']);
        $this->assertSame('Registrar A', $result['.com']['registrar']['title']);
    }

    public function testGetProductPricingArrayUsesDomainPricingImplementation(): void
    {
        $connection = $this->createDomainPricingDbalConnection();

        $product = $this->createProductEntity(1)->setType(Service::DOMAIN);

        $di = $this->getDi();
        $di['dbal'] = $connection;
        $this->service->setDi($di);

        $result = $this->service->getProductPricingArray($product);

        $this->assertArrayHasKey('.com', $result);
        $this->assertEquals(10.0, $result['.com']['price_registration']);
    }

    public function testGetProductUnitUsesDomainUnit(): void
    {
        $product = $this->createProductEntity(1)->setType(Service::DOMAIN);

        $this->service->setDi($this->getDi());

        $this->assertSame('year', $this->service->getProductUnit($product));
    }

    public function testGetProductOrderLineConfigUsesDomainPricingImplementation(): void
    {
        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->tld = '.com';
        $tld->price_registration = 13;
        $tld->price_renew = 20;
        $tld->price_transfer = 15;

        $tldService = $this->createDomainTldServiceMock($tld);

        $di = $this->getDi();
        $di['period'] = $di->protect(fn (string $period): \Box_Period => new \Box_Period($period));
        $di['mod_service'] = $di->protect(function (string $serviceName, ?string $sub = null) use ($tldService) {
            if ($serviceName === 'servicedomain' && $sub === 'Tld') {
                return $tldService;
            }

            throw new \RuntimeException('Unexpected service request');
        });
        $this->service->setDi($di);

        $product = $this->createProductEntity(1)->setType(Service::DOMAIN);

        $line = $this->service->getProductOrderLineConfig($product, [
            'action' => 'register',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ]);

        $this->assertSame(['price' => 33.0, 'quantity' => 1, 'setup_price' => 0.0], $line);
    }

    public function testGetProductRenewalLineConfigUsesDomainPricingImplementation(): void
    {
        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->tld = '.com';
        $tld->price_registration = 13;
        $tld->price_renew = 20;
        $tld->price_transfer = 15;

        $tldService = $this->createDomainTldServiceMock($tld);

        $di = $this->getDi();
        $di['period'] = $di->protect(fn (string $period): \Box_Period => new \Box_Period($period));
        $di['mod_service'] = $di->protect(function (string $serviceName, ?string $sub = null) use ($tldService) {
            if ($serviceName === 'servicedomain' && $sub === 'Tld') {
                return $tldService;
            }

            throw new \RuntimeException('Unexpected service request');
        });
        $this->service->setDi($di);

        $product = $this->createProductEntity(1)->setType(Service::DOMAIN);

        $line = $this->service->getProductRenewalLineConfig($product, [
            'action' => 'register',
            'register_tld' => '.com',
            'register_years' => 2,
            'period' => '2Y',
        ]);

        $this->assertSame(['price' => 20.0, 'quantity' => 2], $line);
    }

    public function testGetOrdersForProductUsesProductOrderRepository(): void
    {
        $connection = $this->createProductOrderDbalConnection();

        $di = $this->getDi();
        $di['dbal'] = $connection;
        $this->service->setDi($di);

        $product = $this->createProductEntity(7);
        $rows = $this->service->getOrdersForProduct($product);

        $this->assertCount(1, $rows);
        $this->assertSame(11, (int) $rows[0]['id']);
        $this->assertSame(7, (int) $rows[0]['product_id']);
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

        $newProductId = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $toolMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolMock->expects($this->atLeastOnce())
            ->method('slug')
            ->willReturn('title');

        $productRepo = $this->createMock(ProductRepository::class);
        $productRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'title'])
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['db'] = $dbMock;
        $di['em'] = $this->createEntityManagerWithRepositories($productRepo, null, $this->createProductEntity($newProductId), $this->createProductPaymentEntity(1));
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

        $modelProduct = $this->createProductEntity(1);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Pricing type is required');
        $serviceMock->updateProduct($modelProduct, $data);
    }

    public function testUpdateProduct(): void
    {
        $modelProduct = $this->createProductEntity(1);

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

        $productPayment = $this->createProductPaymentEntity(1);

        $di = $this->getDi();
        $di['em'] = $this->createProductPaymentEntityManager($this->createProductPaymentRepositoryMockReturning($productPayment));
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

        $productA = $this->createProductEntity(1);
        $productB = $this->createProductEntity(5);

        $productRepo = $this->createMock(ProductRepository::class);
        $productRepo->expects($this->exactly(2))
            ->method('find')
            ->willReturnMap([
                [1, $productA],
                [5, $productB],
            ]);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepo);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updatePriority($data);
        $this->assertTrue($result);
    }

    public function testUpdateConfig(): void
    {
        $modelProduct = $this->createProductEntity(1)->setConfig('{"settings":5,"max":"10"}');

        $data = [
            'config' => [
                'settings' => 3,
                'max' => '',
            ],
            'new_config_name' => 'newParam',
            'new_config_value' => 'newValue',
        ];

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories();
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

        $dbMock = $this->createStub('\Box_Database');

        $toolMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolMock->expects($this->atLeastOnce())
            ->method('slug')
            ->willReturn('title');

        $productRepo = $this->createMock(ProductRepository::class);
        $productRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'title'])
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['em'] = $this->createEntityManagerWithRepositories($productRepo, null, $this->createProductEntity($newProductId), $this->createProductPaymentEntity(1));
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolMock;

        $this->service->setDi($di);

        $result = $this->service->createAddon('title');
        $this->assertIsInt($result);
        $this->assertEquals($newProductId, $result);
    }

    public function testDeleteProductActivaOrderException(): void
    {
        $model = $this->createProductEntity(1);

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
        $expectArray = [
            '1' => 'title4test',
        ];

        $categoryRepo = $this->createMock(ProductCategoryRepository::class);
        $categoryRepo->expects($this->once())
            ->method('getPairs')
            ->willReturn($expectArray);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories(null, null, null, null, $categoryRepo);

        $this->service->setDi($di);
        $result = $this->service->getProductCategoryPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expectArray, $result);
    }

    public function testUpdateCategory(): void
    {
        $model = $this->createProductCategoryEntity(1);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories();
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

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories(null, null, null, null, null, $this->createProductCategoryEntity($newCategoryId));
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->createCategory('title');

        $this->assertIsInt($result);
        $this->assertEquals($newCategoryId, $result);
    }

    public function testRemoveProductCategoryCategoryHasProductsException(): void
    {
        $modelProductCategory = $this->createProductCategoryEntity(1);
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())
            ->method('hasProductsInCategory')
            ->with((int) $modelProductCategory->getId())
            ->willReturn(true);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepository);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove product category with products');
        $this->service->removeProductCategory($modelProductCategory);
    }

    public function testRemoveProductCategory(): void
    {
        $modelProductCategory = $this->createProductCategoryEntity(1);
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())
            ->method('hasProductsInCategory')
            ->with((int) $modelProductCategory->getId())
            ->willReturn(false);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepository);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->removeProductCategory($modelProductCategory);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCreatePromo(): void
    {
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('checkLimits');

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'code'])
            ->willReturn(null);

        $emMock = new class($promoRepo) {
            public function __construct(private object $promoRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->promoRepo;
            }

            public function persist(object $entity): void
            {
                $reflection = new \ReflectionProperty($entity, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($entity, 1);
            }

            public function flush(): void
            {
            }
        };

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->createPromo('code', 'percentage', 50, [], [], [], []);
        $this->assertIsInt($result);
        $this->assertEquals(1, $result);
    }

    public function testToPromoApiArray(): void
    {
        $model = $this->createPromoEntity(1)
            ->setProducts('{}')
            ->setPeriods('{}');

        $repoMock = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->once())
            ->method('countByPromoId')
            ->with(1)
            ->willReturn(0);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('countLinkedOrdersByPromoId')
            ->with(1)
            ->willReturn(0);

        $emMock = new class($promoRepo, $repoMock) {
            public function __construct(private object $promoRepo, private object $redemptionRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
            }
        };

        $di = $this->getDi();
        $di['tools'] = $this->createStub(\FOSSBilling\Tools::class);
        $di['em'] = $emMock;

        $this->service->setDi($di);

        $result = $this->service->toPromoApiArray($model);
        $this->assertIsArray($result);
    }

    public function testToPromoApiArrayIncludesUsageStatsForDeepRequests(): void
    {
        $model = $this->createPromoEntity(1)
            ->setUsed(5)
            ->setMaxUses(10)
            ->setProducts('{}')
            ->setPeriods('{}');

        $repoMock = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->once())
            ->method('getUsageStatsByPromoId')
            ->with(1)
            ->willReturn([
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

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->never())
            ->method('countLinkedOrdersByPromoId');

        $emMock = new class($promoRepo, $repoMock) {
            public function __construct(private object $promoRepo, private object $redemptionRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
            }
        };

        $di = $this->getDi();
        $di['tools'] = $this->createStub(\FOSSBilling\Tools::class);
        $di['em'] = $emMock;

        $this->service->setDi($di);

        $result = $this->service->toPromoApiArray($model, true);
        $this->assertSame(7, $result['redemption_count']);
        $this->assertSame(5, $result['usage_stats']['operational_use_count']);
        $this->assertSame(10, $result['usage_stats']['max_uses']);
        $this->assertSame(5, $result['usage_stats']['remaining_operational_uses']);
        $this->assertSame(5, $result['usage_stats']['checkout_applications']);
        $this->assertSame(2, $result['usage_stats']['renewal_applications']);
        $this->assertSame(4, $result['usage_stats']['active_checkout_applications']);
        $this->assertSame(1, $result['usage_stats']['reserved_applications']);
        $this->assertSame(5, $result['usage_stats']['committed_applications']);
        $this->assertSame(1, $result['usage_stats']['released_applications']);
        $this->assertSame(3, $result['usage_stats']['distinct_clients']);
        $this->assertSame(4, $result['usage_stats']['orders_using_promo']);
    }

    public function testClientHasActivePromoApplication(): void
    {
        $promo = $this->createPromoEntity(5);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 9;

        $repoMock = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->once())
            ->method('clientHasActiveCheckoutApplication')
            ->with(5, 9)
            ->willReturn(true);

        $emMock = new class($repoMock) {
            public function __construct(private $repo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->repo;
            }
        };

        $di = $this->getDi();
        $di['em'] = $emMock;

        $this->service->setDi($di);

        $this->assertTrue($this->service->clientHasActivePromoApplication($client, $promo));
    }

    public static function productPromoCanBeAppliedProvider(): array
    {
        $promo1 = self::createPromoEntityStatic(1)
            ->setActive(false);

        $promo2 = self::createPromoEntityStatic(2)
            ->setActive(true)
            ->setMaxUses(5)
            ->setUsed(5);

        $promo3 = self::createPromoEntityStatic(3)
            ->setActive(true)
            ->setMaxUses(10)
            ->setUsed(5)
            ->setStartAt(new \DateTime('tomorrow'));

        $promo4 = self::createPromoEntityStatic(4)
            ->setActive(true)
            ->setMaxUses(10)
            ->setUsed(5)
            ->setStartAt(new \DateTime('yesterday'))
            ->setEndAt(new \DateTime('yesterday'));

        $promo5 = self::createPromoEntityStatic(5)
            ->setActive(true)
            ->setMaxUses(10)
            ->setUsed(5)
            ->setStartAt(new \DateTime('yesterday'))
            ->setEndAt(new \DateTime('tomorrow'));

        return [
            [$promo1, false],
            [$promo2, false],
            [$promo3, false],
            [$promo4, false],
            [$promo5, true],
        ];
    }

    #[DataProvider('productPromoCanBeAppliedProvider')]
    public function testPromoCanBeApplied(Promo $promo, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->service->promoCanBeApplied($promo));
    }

    public function testCanClientUsePromoReturnsFalseWhenPromoCannotBeApplied(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['promoCanBeApplied'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('promoCanBeApplied')
            ->willReturn(false);

        $promo = $this->createPromoEntity(1);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $this->assertFalse($serviceMock->canClientUsePromo($client, $promo));
    }

    public function testCanClientUsePromoReturnsTrueWhenPromoIsNotOncePerClient(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['promoCanBeApplied', 'clientHasActivePromoApplication'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('promoCanBeApplied')
            ->willReturn(true);
        $serviceMock->expects($this->never())
            ->method('clientHasActivePromoApplication');

        $promo = $this->createPromoEntity(1)
            ->setOncePerClient(false);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $this->assertTrue($serviceMock->canClientUsePromo($client, $promo));
    }

    public function testCanClientUsePromoReturnsFalseWhenClientAlreadyHasActivePromoApplication(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['promoCanBeApplied', 'clientHasActivePromoApplication'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('promoCanBeApplied')
            ->willReturn(true);

        $promo = $this->createPromoEntity(1)
            ->setOncePerClient(true);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $serviceMock->expects($this->once())
            ->method('clientHasActivePromoApplication')
            ->with($client, $promo)
            ->willReturn(true);

        $this->assertFalse($serviceMock->canClientUsePromo($client, $promo));
    }

    public function testUsePromo(): void
    {
        $promo = $this->createPromoEntity(12);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('incrementUsageIfAvailable')
            ->with(12, $this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn(1);

        $emMock = new class($promoRepo) {
            public function __construct(private object $promoRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->promoRepo;
            }
        };

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $this->service->usePromo($promo);
        $this->addToAssertionCount(1);
    }

    public function testUsePromoLimitReached(): void
    {
        $promo = $this->createPromoEntity(12);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('incrementUsageIfAvailable')
            ->willReturn(0);

        $emMock = new class($promoRepo) {
            public function __construct(private object $promoRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->promoRepo;
            }
        };

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('This promo code has reached its maximum number of uses.');
        $this->service->usePromo($promo);
    }

    public function testReservePromoForOrder(): void
    {
        $promo = $this->createPromoEntity(1)
            ->setRecurring(true);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('store')
            ->with($order);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['usePromo'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('usePromo')
            ->with($promo);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $serviceMock->reservePromoForOrder($promo, $order);

        $this->assertSame(1, $order->promo_recurring);
        $this->assertSame(1, $order->promo_used);
    }

    public function testCreateCheckoutPromoRedemptionsPersistsEachOrderAndFlushesOnce(): void
    {
        $promo = $this->createPromoEntity(4);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 8;

        $invoice = new \Model_Invoice();
        $invoice->loadBean(new \DummyBean());
        $invoice->id = 16;

        $firstOrder = new \Model_ClientOrder();
        $firstOrder->loadBean(new \DummyBean());
        $firstOrder->id = 11;
        $firstOrder->discount = 5.0;
        $firstOrder->currency = 'USD';
        $firstOrder->created_at = '2026-01-01 12:00:00';

        $secondOrder = new \Model_ClientOrder();
        $secondOrder->loadBean(new \DummyBean());
        $secondOrder->id = 12;
        $secondOrder->discount = 7.0;
        $secondOrder->currency = 'USD';
        $secondOrder->created_at = '2026-01-01 12:05:00';

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

        $di = $this->getDi();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $this->service->createCheckoutPromoRedemptions(
            $promo,
            $client,
            [$firstOrder, $secondOrder],
            $invoice,
            PromoRedemption::STATUS_RESERVED,
        );

        $this->assertCount(2, $emMock->persisted);
        $this->assertSame(1, $emMock->flushCalls);
        $this->assertSame(11, $emMock->persisted[0]->getClientOrderId());
        $this->assertSame(12, $emMock->persisted[1]->getClientOrderId());
    }

    public function testFindActivePromoByCode(): void
    {
        $promoEntity = new Promo();
        $promoEntity->setCode('CODE');
        $reflection = new \ReflectionProperty($promoEntity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($promoEntity, 14);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('findActiveByCode')
            ->with('CODE')
            ->willReturn($promoEntity);

        $emMock = new class($promoRepo) {
            public function __construct(private object $promoRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->promoRepo;
            }
        };

        $di = $this->getDi();
        $di['em'] = $emMock;
        $this->service->setDi($di);

        $this->assertSame($promoEntity, $this->service->findActivePromoByCode('CODE'));
    }

    public function testGetPromoDiscountTitle(): void
    {
        $promo = $this->createPromoEntity(1)
            ->setCode('PROMO')
            ->setType(Promo::ABSOLUTE)
            ->setValue(5.0);

        $apiGuest = new class {
            public function currency_format(array $data): string
            {
                return $data['code'] . ' ' . $data['price'];
            }
        };

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;
        $this->service->setDi($di);

        $this->assertSame(
            'Promotional Code: PROMO - USD 5 Discount',
            $this->service->getPromoDiscountTitle($promo, 'USD')
        );
    }

    public function testGetRenewalPromoAdjustmentForDomainOrder(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->promo_id = 15;
        $order->promo_recurring = true;
        $order->product_id = 17;
        $order->discount = 2.0;
        $order->currency = 'EUR';
        $order->config = json_encode(['period' => '1Y']);

        $product = $this->createProductEntity(17)->setType(Service::DOMAIN);

        $dbMock = $this->createStub('\Box_Database');

        $promoEntity = $this->createPromoEntity(15)
            ->setCode('PROMO')
            ->setType(Promo::ABSOLUTE)
            ->setValue(5.0);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('find')
            ->with(15)
            ->willReturn($promoEntity);

        $currencyRepository = $this->createMock(\Box\Mod\Currency\Repository\CurrencyRepository::class);
        $currencyRepository->expects($this->once())
            ->method('getRateByCode')
            ->with('EUR')
            ->willReturn(2.0);

        $currencyService = $this->createMock(\Box\Mod\Currency\Service::class);
        $currencyService->expects($this->once())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepository);

        $apiGuest = new class {
            public function currency_format(array $data): string
            {
                return $data['code'] . ' ' . $data['price'];
            }
        };

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getRenewalProductDiscount', 'findProductById'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('findProductById')
            ->with((int) $order->product_id)
            ->willReturn($product);
        $serviceMock->expects($this->once())
            ->method('getRenewalProductDiscount')
            ->with($product, $promoEntity, ['period' => '1Y'])
            ->willReturn(5.0);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['api_guest'] = $apiGuest;
        $di['em'] = new class($promoRepo) {
            public function __construct(private object $promoRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return match ($class) {
                    Promo::class => $this->promoRepo,
                    default => throw new \RuntimeException('Unexpected repository ' . $class),
                };
            }
        };
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyService);
        $serviceMock->setDi($di);

        $result = $serviceMock->getRenewalPromoAdjustment($order, 20.0, 1.0);

        $this->assertSame($promoEntity, $result['promo']);
        $this->assertSame(10.0, $result['discount_amount']);
        $this->assertSame('Promotional Code: PROMO - EUR 5 Discount', $result['title']);
        $this->assertSame('EUR', $result['currency']);
    }

    public function testGetProductDiscountUsesProductOrderLineConfig(): void
    {
        $promo = new Promo();
        $promo->setType(Promo::PERCENTAGE);
        $promo->setValue(10);

        $product = $this->createProductEntity(5)->setIsAddon(false);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getProductOrderLineConfig'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getProductOrderLineConfig')
            ->with($product, ['period' => '1Y'])
            ->willReturn([
                'price' => 100.0,
                'quantity' => 2,
            ]);

        $this->assertSame(20.0, $serviceMock->getProductDiscount($product, $promo, ['period' => '1Y']));
    }

    public function testGetRenewalProductDiscountUsesProductRenewalLineConfig(): void
    {
        $promo = new Promo();
        $promo->setType(Promo::PERCENTAGE);
        $promo->setValue(25);

        $product = $this->createProductEntity(5)->setIsAddon(false);

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getProductRenewalLineConfig'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getProductRenewalLineConfig')
            ->with($product, ['period' => '1Y'])
            ->willReturn([
                'price' => 40.0,
                'quantity' => 2,
            ]);

        $this->assertSame(20.0, $serviceMock->getRenewalProductDiscount($product, $promo, ['period' => '1Y']));
    }

    public static function productIsPromoAvailableForClientGroupProvider(): array
    {
        $promo1 = self::createPromoEntityStatic(1)
            ->setClientGroups(json_encode([]));

        $client1 = new \Model_Client();
        $client1->loadBean(new \DummyBean());

        $promo2 = self::createPromoEntityStatic(2)
            ->setClientGroups(json_encode([1, 2]));

        $client2 = new \Model_Client();
        $client2->loadBean(new \DummyBean());
        $client2->client_group_id = null;

        $promo3 = self::createPromoEntityStatic(3)
            ->setClientGroups(json_encode([1, 2]));

        $client3 = new \Model_Client();
        $client3->loadBean(new \DummyBean());
        $client3->client_group_id = 3;

        $promo4 = self::createPromoEntityStatic(4)
            ->setClientGroups(json_encode([1, 2]));

        $client4 = new \Model_Client();
        $client4->loadBean(new \DummyBean());
        $client4->client_group_id = 2;

        $promo5 = self::createPromoEntityStatic(5)
            ->setClientGroups(json_encode([]));

        $promo6 = self::createPromoEntityStatic(6)
            ->setClientGroups(json_encode([1, 2]));

        return [
            [$promo1, $client1, true],
            [$promo2, $client2, false],
            [$promo3, $client3, false],
            [$promo4, $client4, true],
            [$promo5, null, true],
            [$promo6, null, false],
        ];
    }

    #[DataProvider('productIsPromoAvailableForClientGroupProvider')]
    public function testIsPromoAvailableForClientGroup(Promo $promo, ?\Model_Client $client, bool $expectedResult): void
    {
        $di = $this->getDi();
        $di['loggedin_client'] = $client;
        $this->service->setDi($di);

        $this->assertSame($expectedResult, $this->service->isPromoAvailableForClientGroup($promo));
    }

    public function testReleaseReservedPromoRedemptionsForInvoiceReleasesReservationsAndDecrementsOperationalCounter(): void
    {
        $invoice = new \Model_Invoice();
        $invoice->loadBean(new \DummyBean());
        $invoice->id = 11;

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

        $repoMock = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $repoMock->expects($this->once())
            ->method('findBy')
            ->with([
                'invoiceId' => 11,
                'status' => PromoRedemption::STATUS_RESERVED,
            ])
            ->willReturn([$checkoutRedemption, $secondCheckoutRedemption, $renewalRedemption]);

        $emMock = new class($repoMock) {
            public int $flushCalls = 0;

            public function __construct(private $repo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->repo;
            }

            public function flush(): void
            {
                ++$this->flushCalls;
            }
        };

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('decrementUsage')
            ->with(7, 2, $this->isInstanceOf(\DateTimeInterface::class));

        $di = $this->getDi();
        $di['em'] = new class($promoRepo, $repoMock) {
            public int $flushCalls = 0;

            public function __construct(private object $promoRepo, private object $redemptionRepo)
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

        $this->service->setDi($di);
        $this->service->releaseReservedPromoRedemptionsForInvoice($invoice, 'invoice_deleted');

        $this->assertSame(PromoRedemption::STATUS_RELEASED, $checkoutRedemption->getStatus());
        $this->assertSame(PromoRedemption::STATUS_RELEASED, $renewalRedemption->getStatus());
        $this->assertSame('invoice_deleted', $checkoutRedemption->getReleaseReason());
        $this->assertSame(1, $di['em']->flushCalls);
    }

    public function testUpdatePromo(): void
    {
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
        $reflection = new \ReflectionProperty($promoEntity, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($promoEntity, 1);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'GO'])
            ->willReturn(null);

        $emMock = new class($promoRepo) {
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

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->updatePromo($promoEntity, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDeletePromo(): void
    {
        $promoEntity = $this->createPromoEntity(1)
            ->setCode('PROMO');

        $redemptionRepo = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $redemptionRepo->expects($this->once())
            ->method('countByPromoId')
            ->with(1)
            ->willReturn(0);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('countLinkedOrdersByPromoId')
            ->with(1)
            ->willReturn(0);

        $emMock = new class($promoRepo, $redemptionRepo, $this) {
            public function __construct(private object $promoRepo, private object $redemptionRepo, private \PHPUnit\Framework\TestCase $testCase)
            {
            }

            public function getRepository(string $class): object
            {
                return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
            }

            public function remove(object $entity): void
            {
                $this->testCase->assertInstanceOf(Promo::class, $entity);
            }

            public function flush(): void
            {
            }
        };

        $di = $this->getDi();
        $di['db'] = $this->createStub('\Box_Database');
        $di['logger'] = new \Box_Log();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->deletePromo($promoEntity);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDeletePromoBlocksDeletionWhenRedemptionHistoryExists(): void
    {
        $promoEntity = $this->createPromoEntity(1);

        $redemptionRepo = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $redemptionRepo->expects($this->once())
            ->method('countByPromoId')
            ->with(1)
            ->willReturn(1);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->never())
            ->method('countLinkedOrdersByPromoId');

        $emMock = new class($promoRepo, $redemptionRepo) {
            public function __construct(private object $promoRepo, private object $redemptionRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
            }
        };

        $di = $this->getDi();
        $di['db'] = $this->createStub('\Box_Database');
        $di['em'] = $emMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('Promotions with redemption history cannot be deleted. Disable the promotion instead.');
        $this->service->deletePromo($promoEntity);
    }

    public function testDeletePromoBlocksDeletionWhenLinkedOrdersExistWithoutLedgerHistory(): void
    {
        $promoEntity = $this->createPromoEntity(1);

        $redemptionRepo = $this->getMockBuilder(PromoRedemptionRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $redemptionRepo->expects($this->once())
            ->method('countByPromoId')
            ->with(1)
            ->willReturn(0);

        $promoRepo = $this->getMockBuilder(PromoRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $promoRepo->expects($this->once())
            ->method('countLinkedOrdersByPromoId')
            ->with(1)
            ->willReturn(1);

        $emMock = new class($promoRepo, $redemptionRepo) {
            public function __construct(private object $promoRepo, private object $redemptionRepo)
            {
            }

            public function getRepository(string $class): object
            {
                return $class === Promo::class ? $this->promoRepo : $this->redemptionRepo;
            }
        };

        $di = $this->getDi();
        $di['em'] = $emMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('Promotions with redemption history cannot be deleted. Disable the promotion instead.');
        $this->service->deletePromo($promoEntity);
    }

    public function testIsPromoLinkedToTldReturnsTrueWhenPromoHasNoProductRestrictions(): void
    {
        $promo = new Promo();

        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->id = 1;

        $this->assertTrue($this->service->isPromoLinkedToTld($promo, $tld));
    }

    public function testIsPromoLinkedToTldUsesMainDomainProductLinkage(): void
    {
        $promo = new Promo();
        $promo->setProducts(json_encode([10]));

        $domainProduct = $this->createProductEntity(10)
            ->setIsAddon(false);

        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->id = 1;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getMainDomainProduct'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getMainDomainProduct')
            ->willReturn($domainProduct);

        $this->assertTrue($serviceMock->isPromoLinkedToTld($promo, $tld));
    }

    public function testIsPromoLinkedToTldReturnsFalseWhenDomainProductIsNotLinked(): void
    {
        $promo = new Promo();
        $promo->setProducts(json_encode([25]));

        $domainProduct = $this->createProductEntity(10)
            ->setIsAddon(false);

        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->id = 1;

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getMainDomainProduct'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getMainDomainProduct')
            ->willReturn($domainProduct);

        $this->assertFalse($serviceMock->isPromoLinkedToTld($promo, $tld));
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

        $repo = $this->createMock(ProductRepository::class);
        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $repo->expects($this->once())
            ->method('getSearchQueryBuilder')
            ->with($data)
            ->willReturn($qb);

        $di = $this->getDi();
        $di['em'] = new class($repo) {
            public function __construct(private ProductRepository $repo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->repo;
            }
        };

        $this->service->setDi($di);

        $result = $this->service->getProductSearchQueryBuilder($data);

        $this->assertSame($qb, $result);
    }

    public function testToProductCategoryApiArray(): void
    {
        $model = $this->createProductCategoryEntity(1)
            ->setTitle('Category')
            ->setDescription('Description')
            ->setIconUrl('http://urltoimg.com/img.jpg');

        $modelProduct = $this->createProductEntity(1)->setType('custom');
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

        $serviceMock->setDi($this->getDi());
        $result = $serviceMock->toProductCategoryApiArray($model);
        $this->assertIsArray($result);
    }

    public function testToProductCategoryApiArrayStartingFromValueNotZero(): void
    {
        $model = $this->createProductCategoryEntity(1);

        $modelProduct = $this->createProductEntity(1)->setType('custom');
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

        $serviceMock->setDi($this->getDi());
        $result = $serviceMock->toProductCategoryApiArray($model);
        $this->assertIsArray($result);
        $this->assertEquals($min, $result['price_starting_from']);
    }

    public function testFindOneActiveById(): void
    {
        $model = $this->createProductEntity(1);

        $repo = $this->createMock(ProductRepository::class);
        $repo->expects($this->once())
            ->method('findActiveById')
            ->with(1)
            ->willReturn($model);

        $di = $this->getDi();
        $di['em'] = new class($repo) {
            public function __construct(private ProductRepository $repo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->repo;
            }
        };

        $this->service->setDi($di);
        $result = $this->service->findOneActiveById(1);
        $this->assertInstanceOf(Product::class, $result);
    }

    public function testFindOneActiveBySlug(): void
    {
        $model = $this->createProductEntity(1);

        $repo = $this->createMock(ProductRepository::class);
        $repo->expects($this->once())
            ->method('findActiveBySlug')
            ->with('product/1')
            ->willReturn($model);

        $di = $this->getDi();
        $di['em'] = new class($repo) {
            public function __construct(private ProductRepository $repo)
            {
            }

            public function getRepository(string $class): object
            {
                return $this->repo;
            }
        };

        $this->service->setDi($di);
        $result = $this->service->findOneActiveBySlug('product/1');
        $this->assertInstanceOf(Product::class, $result);
    }

    public function testGetProductCategorySearchQueryBuilder(): void
    {
        $qb = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $categoryRepo = $this->createMock(ProductCategoryRepository::class);
        $categoryRepo->expects($this->once())
            ->method('getEnabledVisibleSearchQueryBuilder')
            ->willReturn($qb);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories(null, null, null, null, $categoryRepo);

        $this->service->setDi($di);
        $result = $this->service->getProductCategorySearchQueryBuilder([]);
        $this->assertSame($qb, $result);
    }

    public function testGetStartingFromPriceTypeFree(): void
    {
        $productModel = $this->createProductEntity(1)->setProductPaymentId(1);

        $productPaymentModel = $this->createProductPaymentEntity(1)
            ->setType(ProductPayment::FREE);

        $paymentRepo = $this->createMock(ProductPaymentRepository::class);
        $paymentRepo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($productPaymentModel);

        $di = $this->getDi();
        $di['em'] = $this->createProductPaymentEntityManager($paymentRepo);

        $this->service->setDi($di);
        $result = $this->service->getStartingFromPrice($productModel);

        $this->assertIsInt($result);
        $this->assertEquals('0', $result);
    }

    public function testGetStartingFromPricePaymentNotDefined(): void
    {
        $productModel = $this->createProductEntity(1);

        $result = $this->service->getStartingFromPrice($productModel);

        $this->assertNull($result);
    }

    public function testGetStartingFromPriceDomainType(): void
    {
        $productModel = $this->createProductEntity(1)
            ->setType(Service::DOMAIN)
            ->setProductPaymentId(1);

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

    private function createProductPaymentEntity(int $id): ProductPayment
    {
        $productPayment = new ProductPayment();
        $reflection = new \ReflectionProperty($productPayment, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($productPayment, $id);

        return $productPayment;
    }

    private function createProductEntity(int $id): Product
    {
        $product = new Product();
        $reflection = new \ReflectionProperty($product, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($product, $id);

        return $product;
    }

    private function createPromoEntity(int $id): Promo
    {
        return self::createPromoEntityStatic($id);
    }

    private static function createPromoEntityStatic(int $id): Promo
    {
        $promo = new Promo();
        $reflection = new \ReflectionProperty($promo, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($promo, $id);

        return $promo;
    }

    private function createProductCategoryEntity(int $id): ProductCategory
    {
        $category = new ProductCategory();
        $reflection = new \ReflectionProperty($category, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($category, $id);

        return $category;
    }

    private function createProductPaymentRepositoryMockReturning(ProductPayment $productPayment): ProductPaymentRepository
    {
        $paymentRepo = $this->createMock(ProductPaymentRepository::class);
        $paymentRepo->expects($this->once())
            ->method('find')
            ->with($productPayment->getId())
            ->willReturn($productPayment);

        return $paymentRepo;
    }

    private function createProductPaymentEntityManager(ProductPaymentRepository $paymentRepo): object
    {
        return new class($paymentRepo) {
            public int $flushCalls = 0;

            public function __construct(private ProductPaymentRepository $paymentRepo)
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

    private function createEntityManagerWithRepositories(?ProductRepository $productRepo = null, ?ProductPaymentRepository $paymentRepo = null, ?Product $persistedProduct = null, ?ProductPayment $persistedPayment = null, ?ProductCategoryRepository $categoryRepo = null, ?ProductCategory $persistedCategory = null): object
    {
        return new class($productRepo, $paymentRepo, $persistedProduct, $persistedPayment, $categoryRepo, $persistedCategory) {
            public int $flushCalls = 0;

            public function __construct(
                private ?ProductRepository $productRepo,
                private ?ProductPaymentRepository $paymentRepo,
                private ?Product $persistedProduct,
                private ?ProductPayment $persistedPayment,
                private ?ProductCategoryRepository $categoryRepo,
                private ?ProductCategory $persistedCategory,
            ) {
            }

            public function getRepository(string $class): object
            {
                return match ($class) {
                    Product::class => $this->productRepo ?? throw new \RuntimeException('Product repository not configured'),
                    ProductPayment::class => $this->paymentRepo ?? throw new \RuntimeException('ProductPayment repository not configured'),
                    ProductCategory::class => $this->categoryRepo ?? throw new \RuntimeException('ProductCategory repository not configured'),
                    default => throw new \RuntimeException('Unexpected repository ' . $class),
                };
            }

            public function persist(object $entity): void
            {
                if ($entity instanceof Product && $this->persistedProduct instanceof Product) {
                    $reflection = new \ReflectionProperty($entity, 'id');
                    $reflection->setAccessible(true);
                    $reflection->setValue($entity, $this->persistedProduct->getId());
                }

                if ($entity instanceof ProductPayment && $this->persistedPayment instanceof ProductPayment) {
                    $reflection = new \ReflectionProperty($entity, 'id');
                    $reflection->setAccessible(true);
                    $reflection->setValue($entity, $this->persistedPayment->getId());
                }

                if ($entity instanceof ProductCategory && $this->persistedCategory instanceof ProductCategory) {
                    $reflection = new \ReflectionProperty($entity, 'id');
                    $reflection->setAccessible(true);
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

    private function createProductPaymentEntityManagerForPersistedEntity(ProductPayment $productPayment): object
    {
        return new class($productPayment) {
            public function __construct(private ProductPayment $productPayment)
            {
            }

            public function getRepository(string $class): object
            {
                throw new \RuntimeException('Repository access not expected');
            }

            public function persist(object $entity): void
            {
                $reflection = new \ReflectionProperty($entity, 'id');
                $reflection->setAccessible(true);
                $reflection->setValue($entity, $this->productPayment->getId());
            }

            public function flush(): void
            {
            }
        };
    }

    private function createDomainPricingDbalConnection(): Connection
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

    private function createProductOrderDbalConnection(): Connection
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

    private function createDomainTldServiceMock(\Model_Tld $tld): \PHPUnit\Framework\MockObject\MockObject
    {
        $tldService = $this->getMockBuilder(\Box\Mod\Servicedomain\ServiceTld::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneByTld'])
            ->getMock();
        $tldService->expects($this->atLeastOnce())
            ->method('findOneByTld')
            ->with('.com')
            ->willReturn($tld);

        return $tldService;
    }

    public function testGetUpgradablePairs(): void
    {
        $productModel = $this->createProductEntity(1)->setUpgrades('{}');

        $expected = [];

        $result = $this->service->getUpgradablePairs($productModel);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetProductTitlesByIds(): void
    {
        $ids = ['1', '2'];
        $expected = [
            1 => 'test',
            2 => 'Another',
        ];
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn([
                $this->createProductEntity(1)->setTitle('test'),
                $this->createProductEntity(2)->setTitle('Another'),
            ]);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepository);

        $this->service->setDi($di);

        $result = $this->service->getProductTitlesByIds($ids);
        $this->assertIsArray($result);
        $this->assertSame($expected, $result);
    }

    public function testGetCategoryProducts(): void
    {
        $productCategoryModel = $this->createProductCategoryEntity(1);

        $productModel = $this->createProductEntity(1);
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())
            ->method('findEnabledVisibleByCategoryId')
            ->with((int) $productCategoryModel->getId())
            ->willReturn([$productModel]);

        $di = $this->getDi();
        $di['em'] = $this->createEntityManagerWithRepositories($productRepository);

        $this->service->setDi($di);
        $result = $this->service->getCategoryProducts($productCategoryModel);
        $this->assertIsArray($result);
    }

    public function testToProductPaymentApiArray(): void
    {
        $productPaymentModel = $this->createProductPaymentEntity(1);

        $result = $this->service->toProductPaymentApiArray($productPaymentModel);
        $this->assertIsArray($result);
    }

    public function testGetStartingPrice(): void
    {
        $productPaymentModel = $this->createProductPaymentEntity(1)
            ->setType(ProductPayment::RECURRENT);

        $minPrice = 1;

        $productPaymentModel->setPeriodPricing('w', 2, 0, true);
        $productPaymentModel->setPeriodPricing('m', 4, 0, true);
        $productPaymentModel->setPeriodPricing('q', 8, 0, true);
        $productPaymentModel->setPeriodPricing('b', $minPrice, 0, true);
        $productPaymentModel->setPeriodPricing('a', 10, 0, true);
        $productPaymentModel->setPeriodPricing('bia', 12, 0, true);
        $productPaymentModel->setPeriodPricing('tria', 14, 0, true);

        $result = $this->service->getStartingPrice($productPaymentModel);
        $this->assertIsNumeric($result);
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

        $productModel = $this->createProductEntity(1);
        $newProductModel = $this->createProductEntity(2);

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

        $productModel = $this->createProductEntity(1);
        $newProductModel = $this->createProductEntity(2);

        $result = $serviceMock->canUpgradeTo($productModel, $newProductModel);
        $this->assertFalse($result);
    }

    public function testCanUpgradeToSameProducts(): void
    {
        $productModel = $this->createProductEntity(1);
        $newProductModel = $this->createProductEntity(1);

        $result = $this->service->canUpgradeTo($productModel, $newProductModel);
        $this->assertFalse($result);
    }

    public function testAssertUpgradeAllowedByIdsThrowsHelpfulException(): void
    {
        $currentProduct = $this->createProductEntity(1)
            ->setTitle('Starter');
        $upgradeProduct = $this->createProductEntity(2)
            ->setTitle('Pro');

        $serviceMock = $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findProductById', 'getUpgradablePairsByProductId'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getUpgradablePairsByProductId')
            ->with(1)
            ->willReturn([]);
        $serviceMock->expects($this->exactly(2))
            ->method('findProductById')
            ->willReturnMap([
                [1, $currentProduct],
                [2, $upgradeProduct],
            ]);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('Sorry, but "Starter" is not allowed to be upgraded to "Pro"');

        $serviceMock->assertUpgradeAllowedByIds(1, 2);
    }
}
