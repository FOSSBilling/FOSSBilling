<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Cart;

use Box\Mod\Product\Entity\Promo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?\Box\Mod\Cart\Service $service;

    public function setUp(): void
    {
        $this->service = new \Box\Mod\Cart\Service();
    }

    private function createProductEntity(?int $id = null, ?string $type = null, ?string $config = null): \Box\Mod\Product\Entity\Product
    {
        $product = new \Box\Mod\Product\Entity\Product();
        if ($id !== null) {
            $reflection = new \ReflectionProperty($product, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($product, $id);
        }
        if ($type !== null) {
            $product->setType($type);
        }
        if ($config !== null) {
            $product->setConfig($config);
        }

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

    public function testDi(): void
    {
        $service = new \Box\Mod\Cart\Service();

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function testGetSearchQuery(): void
    {
        $service = new \Box\Mod\Cart\Service();
        $result = $service->getSearchQuery([]);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertNotFalse(strpos($result[0], 'SELECT cart.id FROM cart'));
    }

    public function testGetSessionCartExists(): void
    {
        $service = new \Box\Mod\Cart\Service();

        $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';

        $model = new \Model_Cart();
        $model->loadBean(new \DummyBean());
        $model->session_id = $session_id;

        $dbMock = $this->createMock('Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn($session_id);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $service->setDi($di);

        $result = $service->getSessionCart();

        $this->assertInstanceOf('Model_Cart', $result);
        $this->assertEquals($result->session_id, $session_id);
    }

    public static function getSessionCartDoesNotExistProvider(): array
    {
        return [
            [
                100,
                'atLeastOnce',
                'never',
            ],
            [
                null,
                'never',
                'atLeastOnce',
            ],
        ];
    }

    #[DataProvider('getSessionCartDoesNotExistProvider')]
    public function testGetSessionCartDoesNotExist(?int $sessionGetWillReturn, string $getCurrencyByClientIdExpects, string $getDefaultExpects): void
    {
        $service = new \Box\Mod\Cart\Service();

        $currencyModel = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyId = random_int(0, 1000);
        $currencyModel->expects($this->any())
            ->method('getId')
            ->willReturn($currencyId);

        $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';
        $model = null; // Does not exist in database
        $dbMock = $this->createMock('Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);
        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($modelCart);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn($session_id);
        $sessionMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($sessionGetWillReturn);

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($sessionGetWillReturn === null) {
            $currencyRepositoryMock->expects($this->atLeastOnce())
                ->method('findDefault')
                ->willReturn($currencyModel);
        } else {
            $currencyRepositoryMock->expects($this->never())
                ->method('findDefault');
        }

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyByClientId', 'getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->$getCurrencyByClientIdExpects())
            ->method('getCurrencyByClientId')
            ->willReturn($currencyModel);
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
        $service->setDi($di);

        $result = $service->getSessionCart();

        $this->assertInstanceOf('Model_Cart', $result);
        $this->assertEquals($result->session_id, $session_id);
        $this->assertEquals($currencyId, $result->currency_id);
    }

    public function testIsStockAvailable(): void
    {
        $product = $this->createProductEntity();
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('isStockAvailable')
            ->with($product, 6)
            ->willReturn(false);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(static fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);
        $result = $this->service->isStockAvailable($product, 6);
        $this->assertFalse($result);
    }

    public function testIsStockAvailableNoStockControl(): void
    {
        $product = $this->createProductEntity();
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('isStockAvailable')
            ->with($product, 6)
            ->willReturn(true);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(static fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);
        $result = $this->service->isStockAvailable($product, 6);
        $this->assertTrue($result);
    }

    public function testIsRecurrentPricing(): void
    {
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('isRecurrentProductPricing')
            ->with($this->isInstanceOf(\Box\Mod\Product\Entity\Product::class))
            ->willReturn(true);

        $productModelMock = $this->createProductEntity();

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(static fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->isRecurrentPricing($productModelMock);

        $this->assertTrue($result);
    }

    public function testIsPeriodEnabledForProduct(): void
    {
        $enabled = false;
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('isProductPeriodEnabled')
            ->with($this->isInstanceOf(\Box\Mod\Product\Entity\Product::class), 'monthly')
            ->willReturn($enabled);

        $productModelMock = $this->createProductEntity();

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(static fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->isPeriodEnabledForProduct($productModelMock, 'monthly');

        $this->assertIsBool($result);
        $this->assertEquals($result, $enabled);
    }

    public function testIsPeriodEnabledForProductNotRecurrent(): void
    {
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('isProductPeriodEnabled')
            ->with($this->isInstanceOf(\Box\Mod\Product\Entity\Product::class), 'monthly')
            ->willReturn(true);

        $productModelMock = $this->createProductEntity();

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(static fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->isPeriodEnabledForProduct($productModelMock, 'monthly');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testRemoveProduct(): void
    {
        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($cartProduct);
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$cartProduct]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $result = $this->service->removeProduct($cart, 1);
        $this->assertTrue($result);
    }

    public function testRemoveProductCartProductNotFound(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);
        $dbMock->expects($this->never())
            ->method('trash')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->removeProduct($cart, 1);
        $this->assertTrue($result);
    }

    public function testChangeCartCurrency(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $currency = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->changeCartCurrency($cart, $currency);
        $this->assertTrue($result);
    }

    public function testResetCart(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_CartProduct(), new \Model_CartProduct()]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->resetCart($cart);
        $this->assertTrue($result);
    }

    public function testRemovePromo(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->removePromo($cart);
        $this->assertTrue($result);
    }

    public function testApplyPromo(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_CartProduct(), new \Model_CartProduct()]);

        $promo = $this->createPromoEntity(2);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 1;

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testApplyPromoAlreadyApplied(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(1);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isEmptyCart'])->getMock();
        $serviceMock->expects($this->never())->method('isEmptyCart')
            ->willReturn(false);

        $promo = $this->createPromoEntity(5);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 5;

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $result = $serviceMock->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testApplyPromoEmptyCartException(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(1);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isEmptyCart'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isEmptyCart')
            ->willReturn(true);

        $promo = $this->createPromoEntity(2);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 1;

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $serviceMock->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_CartProduct()]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->rm($cart);
        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromo(): void
    {
        $promo = $this->createPromoEntity(1)
            ->setOncePerClient(true);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('canClientUsePromo')
            ->with($client, $promo)
            ->willReturn(false);

        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);

        $serviceMock = new \Box\Mod\Cart\Service();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public function testClientHadUsedPromo(): void
    {
        $promo = $this->createPromoEntity(1);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('clientHasActivePromoApplication')
            ->with($client, $promo)
            ->willReturn(true);

        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $service = new \Box\Mod\Cart\Service();
        $service->setDi($di);

        $reflection = new \ReflectionObject($service);
        $method = $reflection->getMethod('clientHadUsedPromo');
        $result = $method->invoke($service, $client, $promo);

        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromoOncePerClient(): void
    {
        $promo = $this->createPromoEntity(1);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('canClientUsePromo')
            ->with($client, $promo)
            ->willReturn(true);

        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);

        $serviceMock = new \Box\Mod\Cart\Service();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromoCanNotBeApplied(): void
    {
        $promo = $this->createPromoEntity(1);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('canClientUsePromo')
            ->with($client, $promo)
            ->willReturn(false);

        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);

        $serviceMock = new \Box\Mod\Cart\Service();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public static function promoCanBeAppliedProvider(): array
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

    #[DataProvider('promoCanBeAppliedProvider')]
    public function testPromoCanBeApplied(Promo $promo, bool $expectedResult): void
    {
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('promoCanBeApplied')
            ->with($promo)
            ->willReturn($expectedResult);

        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->promoCanBeApplied($promo);
        $this->assertEquals($result, $expectedResult);
    }

    public function testGetCartProducts(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_CartProduct()]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $result = $this->service->getCartProducts($cart);
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_CartProduct', $result[0]);
    }

    public function testCheckoutCart(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 1;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['createFromCart', 'isClientAbleToUsePromo', 'rm', 'isPromoAvailableForClientGroup'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('createFromCart')
            ->willReturn([$order, 1, [1]]);
        $serviceMock->expects($this->atLeastOnce())->method('isClientAbleToUsePromo')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $invoice = new \Model_Invoice();
        $invoice->loadBean(new \DummyBean());
        $invoice->hash = sha1('str');

        $promo = new \Box\Mod\Product\Entity\Promo();

        $dbMock = $this->createMock('Box_Database');

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($promo);

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $di['request'] = new Request();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);

        $serviceMock->setDi($di);
        $result = $serviceMock->checkoutCart($cart, $client);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gateway_id', $result);
        $this->assertArrayHasKey('invoice_hash', $result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('orders', $result);
    }

    public function testCheckoutCartClientIsNotAbleToUsePromoException(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 1;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isClientAbleToUsePromo'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isClientAbleToUsePromo')
            ->willReturn(false);

        $dbMock = $this->createMock('Box_Database');
        $promo = new \Box\Mod\Product\Entity\Promo();
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($promo);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $serviceMock->checkoutCart($cart, $client);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gateway_id', $result);
        $this->assertArrayHasKey('invoice_hash', $result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('orders', $result);
    }

    public function testUsePromo(): void
    {
        $promo = $this->createPromoEntity(1);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('usePromo')
            ->with($promo);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->usePromo($promo);

        $this->assertNull($result);
    }

    public function testCreateFromCartUsesDatabaseTransaction(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = 2;

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->currency = 'USD';

        $currency = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currency->expects($this->once())
            ->method('getCode')
            ->willReturn('USD');

        $currencyRepository = $this->createMock(\Box\Mod\Currency\Repository\CurrencyRepository::class);
        $currencyRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($currency);
        $currencyRepository->expects($this->never())
            ->method('findDefault');

        $currencyService = $this->createMock(\Box\Mod\Currency\Service::class);
        $currencyService->expects($this->once())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepository);

        $clientService = $this->createMock(\Box\Mod\Client\Service::class);
        $clientService->expects($this->once())
            ->method('isClientTaxable')
            ->with($client)
            ->willReturn(false);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 99;

        $dbMock = $this->getMockBuilder('Box_Database')
            ->onlyMethods(['transaction'])
            ->getMock();
        $dbMock->expects($this->once())
            ->method('transaction')
            ->with($this->isInstanceOf(\Closure::class))
            ->willReturn([$order, null, [99]]);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'toApiArray'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->once())
            ->method('toApiArray')
            ->with($cart)
            ->willReturn([
                'items' => [['id' => 1]],
                'total' => 0,
            ]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $clientService) {
            if ($serviceName === 'currency') {
                return $currencyService;
            }
            if ($serviceName === 'client') {
                return $clientService;
            }
        });

        $serviceMock->setDi($di);
        $result = $serviceMock->createFromCart($client);

        $this->assertSame([$order, null, [99]], $result);
    }

    public function testCreateFromCartWithPromoEntityUsesProductPromoService(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->id = 3;
        $cart->currency_id = 2;
        $cart->promo_id = 7;

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 9;
        $client->currency = 'USD';

        $currency = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currency->expects($this->once())
            ->method('getCode')
            ->willReturn('USD');
        $currency->expects($this->atLeastOnce())
            ->method('getConversionRate')
            ->willReturn(1.0);

        $currencyRepository = $this->createMock(\Box\Mod\Currency\Repository\CurrencyRepository::class);
        $currencyRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($currency);

        $currencyService = $this->createMock(\Box\Mod\Currency\Service::class);
        $currencyService->expects($this->once())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepository);

        $clientService = $this->createMock(\Box\Mod\Client\Service::class);
        $clientService->expects($this->once())
            ->method('isClientTaxable')
            ->with($client)
            ->willReturn(false);

        $promo = new \Box\Mod\Product\Entity\Promo();
        $promo->setCode('PROMO');
        $promoIdReflection = new \ReflectionProperty($promo, 'id');
        $promoIdReflection->setAccessible(true);
        $promoIdReflection->setValue($promo, 7);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findPromoById')
            ->with(7)
            ->willReturn($promo);
        $productService->expects($this->once())
            ->method('reservePromoForOrder')
            ->with($promo, $this->isInstanceOf(\Model_ClientOrder::class));
        $productService->expects($this->once())
            ->method('createCheckoutPromoRedemptions')
            ->with(
                $promo,
                $client,
                $this->callback(function (array $orders): bool {
                    return count($orders) === 1 && $orders[0] instanceof \Model_ClientOrder;
                }),
                null,
                \Box\Mod\Product\Entity\PromoRedemption::STATUS_COMMITTED
            );

        $product = new \Box\Mod\Product\Entity\Product();
        $productIdReflection = new \ReflectionProperty($product, 'id');
        $productIdReflection->setAccessible(true);
        $productIdReflection->setValue($product, 5);
        $product->setStatus('enabled');
        $product->setType('service');
        $product->setSetup('manual');

        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \DummyBean());
        $cartProduct->id = 13;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 42;

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveStatusChange', 'toApiArray'])
            ->getMock();
        $orderService->expects($this->once())
            ->method('saveStatusChange')
            ->with($this->isInstanceOf(\Model_ClientOrder::class), 'Order Created');
        $orderService->expects($this->once())
            ->method('toApiArray')
            ->with($this->isInstanceOf(\Model_ClientOrder::class), false, $client)
            ->willReturn([
                'product_id' => 5,
                'total' => 0,
                'discount' => 0,
            ]);

        $dbMock = $this->getMockBuilder('Box_Database')
            ->onlyMethods(['transaction', 'dispense', 'store'])
            ->getMock();
        $dbMock->expects($this->once())
            ->method('transaction')
            ->with($this->isInstanceOf(\Closure::class))
            ->willReturnCallback(fn (\Closure $callback) => $callback());
        $dbMock->expects($this->once())
            ->method('dispense')
            ->with('ClientOrder')
            ->willReturn($order);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'toApiArray', 'getCartProducts', 'cartProductToApiArray', 'isStockAvailable'])
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->once())
            ->method('toApiArray')
            ->with($cart)
            ->willReturn([
                'items' => [['id' => 1]],
                'total' => 0,
            ]);
        $serviceMock->expects($this->once())
            ->method('getCartProducts')
            ->with($cart)
            ->willReturn([$cartProduct]);
        $serviceMock->expects($this->once())
            ->method('cartProductToApiArray')
            ->with($cartProduct)
            ->willReturn([
                'product_id' => 5,
                'form_id' => null,
                'title' => 'Example product',
                'type' => 'service',
                'unit' => 'service',
                'period' => '1M',
                'quantity' => 1,
                'price' => 0,
                'discount_price' => 0,
                'setup_price' => 0,
                'discount_setup' => 0,
                'notes' => null,
            ]);
        $serviceMock->expects($this->once())
            ->method('isStockAvailable')
            ->with($product, 1)
            ->willReturn(true);

        $productService->expects($this->exactly(2))
            ->method('findProductById')
            ->with(5)
            ->willReturn($product);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($currencyService, $clientService, $productService, $orderService) {
            return match ($serviceName) {
                'currency' => $currencyService,
                'client' => $clientService,
                'Product' => $productService,
                'order', 'Order' => $orderService,
                default => null,
            };
        });

        $serviceMock->setDi($di);
        $result = $serviceMock->createFromCart($client);

        $this->assertSame([$order, null, [42]], $result);
    }

    public function testUsePromoLimitReached(): void
    {
        $promo = $this->createPromoEntity(1);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('usePromo')
            ->with($promo)
            ->willThrowException(new \FOSSBilling\InformationException('This promo code has reached its maximum number of uses.'));

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->service->usePromo($promo);
    }

    public function testFindActivePromoByCode(): void
    {
        $promo = new \Box\Mod\Product\Entity\Promo();

        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findActivePromoByCode')
            ->with('CODE')
            ->willReturn($promo);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->findActivePromoByCode('CODE');

        $this->assertInstanceOf(\Box\Mod\Product\Entity\Promo::class, $result);
    }

    public function testAddItemmRecurringPaymentPeriodParamMissing(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = $this->createProductEntity(type: 'Custom');

        $data = [];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');
        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(true);

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Period parameter not passed'));
        $di['validator'] = $validatorMock;
        $productService->setDi($di);
        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Period parameter not passed');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testAddItemmRecurringPaymentPeriodIsNotEnabled(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = $this->createProductEntity(type: 'hosting');

        $data = ['period' => '1W'];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isPeriodEnabledForProduct'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isPeriodEnabledForProduct')
            ->willReturn(false);

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
        ;
        $di['validator'] = $validatorMock;
        $productService->setDi($di);
        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Selected billing period is invalid');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testAddItemmOutOfStock(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = $this->createProductEntity(type: 'hosting');

        $data = [];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isStockAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->willReturn(false);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });

        $productService->setDi($di);
        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This item is currently out of stock');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testAddItemRejectsCumulativeStockOverflow(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());
        $cartModel->id = 10;

        $productModel = $this->createProductEntity(id: 7, type: 'hosting');
        $productModel->setStockControl(true);
        $productModel->setQuantityInStock(1);

        $existingCartProduct = new \Model_CartProduct();
        $existingCartProduct->loadBean(new \DummyBean());
        $existingCartProduct->product_id = 7;
        $existingCartProduct->config = json_encode(['quantity' => 1]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();
        $productServiceMock = $this->createMock(\Box\Mod\Product\Service::class);
        $productServiceMock->expects($this->once())
            ->method('isStockAvailable')
            ->with($productModel, 2)
            ->willReturn(false);

        $dbMock = $this->createMock('Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('CartProduct')
            ->willReturn([$existingCartProduct]);
        $dbMock->expects($this->never())
            ->method('store');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productServiceMock) {
            if ($name === 'Product') {
                return $productServiceMock;
            }

            return $serviceHostingServiceMock;
        });

        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This item is currently out of stock');
        $serviceMock->addItem($cartModel, $productModel, ['quantity' => 1]);
    }

    public function testAddItemRejectsDuplicateDomainRegister(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());
        $cartModel->id = 1;

        $productModel = $this->createProductEntity(type: 'domain');

        // An existing cart item already holds example.com via register keys.
        $existingCartProduct = new \Model_CartProduct();
        $existingCartProduct->loadBean(new \DummyBean());
        $existingCartProduct->config = json_encode(['register_sld' => 'example', 'register_tld' => '.com']);

        $dbMock = $this->createMock('Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('CartProduct')
            ->willReturn([$existingCartProduct]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);

        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });
        $productService->setDi($di);
        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('This domain is already in the cart.');
        $serviceMock->addItem($cartModel, $productModel, ['register_sld' => 'example', 'register_tld' => '.com']);
    }

    public function testAddItemRejectsDuplicateDomainTransfer(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());
        $cartModel->id = 2;

        $productModel = $this->createProductEntity(type: 'domain');

        // An existing cart item holds example.net via transfer keys.
        $existingCartProduct = new \Model_CartProduct();
        $existingCartProduct->loadBean(new \DummyBean());
        $existingCartProduct->config = json_encode(['transfer_sld' => 'example', 'transfer_tld' => '.net']);

        $dbMock = $this->createMock('Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('CartProduct')
            ->willReturn([$existingCartProduct]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);

        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });
        $productService->setDi($di);
        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('This domain is already in the cart.');
        // Incoming uses transfer keys too, same domain.
        $serviceMock->addItem($cartModel, $productModel, ['transfer_sld' => 'example', 'transfer_tld' => '.net']);
    }

    public function testAddItemRejectsDuplicateDomainNested(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());
        $cartModel->id = 3;

        $productModel = $this->createProductEntity(type: 'hosting');

        // An existing hosting cart item stores the domain under the nested 'domain' key.
        $existingCartProduct = new \Model_CartProduct();
        $existingCartProduct->loadBean(new \DummyBean());
        $existingCartProduct->config = json_encode([
            'domain' => ['register_sld' => 'mysite', 'register_tld' => '.org'],
        ]);

        $dbMock = $this->createMock('Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('CartProduct')
            ->willReturn([$existingCartProduct]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);

        $serviceHostingServiceMock = $this->getMockBuilder(\Box\Mod\Servicehosting\Service::class)->getMock();

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });
        $productService->setDi($di);
        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('This domain is already in the cart.');
        // New hosting order bundles the same domain under the 'domain' key.
        $serviceMock->addItem($cartModel, $productModel, [
            'domain' => ['register_sld' => 'mysite', 'register_tld' => '.org'],
        ]);
    }

    public function testAddItemmTypeHosting(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = $this->createProductEntity(type: 'hosting');

        $data = [];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $productDomainModel = $this->createProductEntity(type: 'domain');
        $domainProduct = ['config' => [], 'product' => $productDomainModel];

        $serviceHostingServiceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
        $serviceHostingServiceMock->expects($this->atLeastOnce())
            ->method('getDomainProductFromConfig')
            ->willReturn($domainProduct);
        $serviceHostingServiceMock->expects($this->atLeastOnce())
            ->method('prependOrderConfig')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isStockAvailable', 'addProduct'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())
            ->method('addProduct');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceHostingServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceHostingServiceMock;
        });
        $di['logger'] = new \Box_Log();

        $productService->setDi($di);
        $serviceMock->setDi($di);
        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testAddItemmTypeLicense(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = $this->createProductEntity(type: 'license');

        $data = [];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceLicenseServiceMock = $this->createMock(\Box\Mod\Servicelicense\Service::class);
        $serviceLicenseServiceMock->expects($this->atLeastOnce())
            ->method('attachOrderConfig')
            ->willReturn([]);
        $serviceLicenseServiceMock->expects($this->atLeastOnce())
            ->method('validateOrderData')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isStockAvailable', 'addProduct'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())
            ->method('addProduct');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceLicenseServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceLicenseServiceMock;
        });
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $productService->setDi($di);
        $serviceMock->setDi($di);
        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testAddItemmTypeCustom(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = $this->createProductEntity(type: 'custom');

        $data = [];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceCustomServiceMock = $this->createMock(\Box\Mod\Servicecustom\Service::class);
        $serviceCustomServiceMock->expects($this->atLeastOnce())
            ->method('validateCustomForm');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isStockAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);
        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($cartProduct);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $productService = new \Box\Mod\Product\Service();
        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($name) use ($serviceCustomServiceMock, $productService) {
            if ($name === 'Product') {
                return $productService;
            }

            return $serviceCustomServiceMock;
        });
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $productService->setDi($di);
        $serviceMock->setDi($di);
        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testToApiArray(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getCartProducts', 'cartProductToApiArray'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCartProducts')
            ->willReturn([$cartProductModel]);
        $cartProductApiArray = [
            'total' => 1,
            'setup_price' => 0,
            'discount' => 0,
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('cartProductToApiArray')
            ->willReturn($cartProductApiArray);

        $currencyService = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->getMock();

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $currencyModel = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyModel->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($currencyModel);

        $currencyService->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyService);

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($cartModel);

        $expected = [
            'promocode' => null,
            'discount' => 0,
            'total' => 1,
            'items' => [$cartProductApiArray],
            'currency' => [],
            'subtotal' => 1,
        ];
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetProductDiscount(): void
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());
        $modelCart->promo_id = 1;

        $promoModel = new \Box\Mod\Product\Entity\Promo();

        $discountPrice = 25;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($promoModel);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getRelatedItemsDiscount', 'getItemPromoDiscount', 'getItemConfig'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getRelatedItemsDiscount')
            ->willReturn(0);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getItemPromoDiscount')
            ->willReturn($discountPrice);

        $serviceMock->setDi($di);
        $setupPrice = 0;
        $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

        $this->assertIsArray($result);
        $this->assertEquals($discountPrice, $result[0]);
        $discountSetup = 0;
        $this->assertEquals($discountSetup, $result[1]);
    }

    public function testGetProductDiscountNoPromo(): void
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getRelatedItemsDiscount', 'getItemPromoDiscount', 'getItemConfig'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getRelatedItemsDiscount')
            ->willReturn(0);

        $serviceMock->setDi($di);
        $setupPrice = 0;
        $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result[0]);
        $this->assertEquals(0, $result[1]);
    }

    public function testGetProductDiscountProductQtyIsSetAndFreeSetup(): void
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());
        $modelCart->promo_id = 1;

        $promoModel = new \Box\Mod\Product\Entity\Promo();
        $promoModel->setFreeSetup(true);

        $discountPrice = 25;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('findPromoById')
            ->with(1)
            ->willReturn($promoModel);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getRelatedItemsDiscount', 'getItemPromoDiscount', 'getItemConfig'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getRelatedItemsDiscount')
            ->willReturn(0);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getItemPromoDiscount')
            ->willReturn($discountPrice);

        $serviceMock->setDi($di);
        $setupPrice = 25;
        $result = $serviceMock->getProductDiscount($cartProductModel, $setupPrice);

        $this->assertIsArray($result);
        $this->assertEquals($discountPrice, $result[0]);
        $discountSetup = $setupPrice;
        $this->assertEquals($discountSetup, $result[1]);
    }

    public static function isPromoAvailableForClientGroupProvider(): array
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

        $client5 = null;

        $promo6 = self::createPromoEntityStatic(6)
            ->setClientGroups(json_encode([1, 2]));

        $client6 = null;

        return [
            [$promo1, $client1, true], // No client groups set for Promo, any client should be is valid
            [$promo2, $client2, false], // Client groups are set for Promo, but client is not assigned to any client group
            [$promo3, $client3, false], // Client groups are set for Promo, but client group is not included
            [$promo4, $client4, true], // Client groups are set for Promo and it applies to client
            [$promo5, null, true], // No client groups set for Promo, guest should be is valid
            [$promo6, null, false], // Client groups are set for Promo,  guest should be is invalid
        ];
    }

    #[DataProvider('isPromoAvailableForClientGroupProvider')]
    public function testIsPromoAvailableForClientGroup(Promo $promo, ?\Model_Client $client, bool $expectedResult): void
    {
        $productService = $this->createMock(\Box\Mod\Product\Service::class);
        $productService->expects($this->once())
            ->method('isPromoAvailableForClientGroup')
            ->with($promo)
            ->willReturn($expectedResult);

        $di = $this->getDi();
        $di['loggedin_client'] = $client;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $productService);
        $this->service->setDi($di);

        $result = $this->service->isPromoAvailableForClientGroup($promo);

        $this->assertEquals($result, $expectedResult);
    }
}
