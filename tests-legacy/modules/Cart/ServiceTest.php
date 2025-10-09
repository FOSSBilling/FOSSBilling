<?php

namespace Box\Tests\Mod\Cart;

use Symfony\Component\HttpFoundation\Request;

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Cart\Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Cart\Service();
    }

    public function testDi(): void
    {
        $service = new \Box\Mod\Cart\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

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

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $sessionMock = $this->getMockBuilder('\\' . \FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn($session_id);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $service->setDi($di);

        $result = $service->getSessionCart();

        $this->assertInstanceOf('Model_Cart', $result);
        $this->assertEquals($result->session_id, $session_id);
    }

    public static function getSessionCartDoesNotExistProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        return [
            [
                100,
                $self->atLeastOnce(),
                $self->never(),
            ],
            [
                null,
                $self->never(),
                $self->atLeastOnce(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getSessionCartDoesNotExistProvider')]
    public function testGetSessionCartDoesNotExist(?int $sessionGetWillReturn, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $getCurrencyByClientIdExpects, \PHPUnit\Framework\MockObject\Rule\InvokedCount|\PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce $getDefaultExpects): void
    {
        $service = new \Box\Mod\Cart\Service();

        $curencyModel = new \Model_Currency();
        $curencyModel->loadBean(new \DummyBean());
        $curencyModel->id = random_int(0, 1000);

        $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';
        $model = null; // Does not exist in database
        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
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
            ->willReturn(random_int(1, 100));

        $sessionMock = $this->getMockBuilder('\\' . \FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('getId')
            ->willReturn($session_id);
        $sessionMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturn($sessionGetWillReturn);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->onlyMethods(['getCurrencyByClientId', 'getDefault'])->getMock();
        $currencyServiceMock->expects($getCurrencyByClientIdExpects)
            ->method('getCurrencyByClientId')
            ->willReturn($curencyModel);
        $currencyServiceMock->expects($getDefaultExpects)
            ->method('getDefault')
            ->willReturn($curencyModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
        $service->setDi($di);

        $result = $service->getSessionCart();

        $this->assertInstanceOf('Model_Cart', $result);
        $this->assertEquals($result->session_id, $session_id);
        $this->assertEquals($result->currency_id, $curencyModel->id);
    }

    public function testIsStockAvailable(): void
    {
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->stock_control = true;
        $product->quantity_in_stock = 5;

        $result = $this->service->isStockAvailable($product, 6);
        $this->assertFalse($result);
    }

    public function testIsStockAvailableNoStockControl(): void
    {
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->stock_control = false;
        $product->quantity_in_stock = 5;

        $result = $this->service->isStockAvailable($product, 6);
        $this->assertTrue($result);
    }

    public function testIsRecurrentPricing(): void
    {
        $productTable = $this->getMockBuilder('\Model_ProductTable')->getMock();
        $productTable->expects($this->atLeastOnce())->method('getPricingArray')
            ->willReturn(['type' => \Model_ProductPayment::RECURRENT]);

        $productModelMock = $this->getMockBuilder('\Model_Product')
            ->onlyMethods(['getTable'])->getMock();
        $productModelMock->expects($this->atLeastOnce())
            ->method('getTable')
            ->willReturn($productTable);

        $result = $this->service->isRecurrentPricing($productModelMock);

        $this->assertTrue($result);
    }

    public function testIsPeriodEnabledForProduct(): void
    {
        $enabled = false;
        $pricingArray = [
            'type' => \Model_ProductPayment::RECURRENT,
            'recurrent' => [
                'monthly' => [
                    'enabled' => $enabled,
                ],
            ],
        ];
        $productTable = $this->getMockBuilder('\Model_ProductTable')->getMock();
        $productTable->expects($this->atLeastOnce())->method('getPricingArray')
            ->willReturn($pricingArray);

        $productModelMock = $this->getMockBuilder('\Model_Product')
            ->onlyMethods(['getTable'])->getMock();
        $productModelMock->expects($this->atLeastOnce())
            ->method('getTable')
            ->willReturn($productTable);

        $result = $this->service->isPeriodEnabledForProduct($productModelMock, 'monthly');

        $this->assertIsBool($result);
        $this->assertEquals($result, $enabled);
    }

    public function testIsPeriodEnabledForProductNotRecurrent(): void
    {
        $enabled = false;
        $pricingArray = [
            'type' => \Model_ProductPayment::FREE,
            'recurrent' => [
                'monthly' => [
                    'enabled' => $enabled,
                ],
            ],
        ];
        $productTable = $this->getMockBuilder('\Model_ProductTable')->getMock();
        $productTable->expects($this->atLeastOnce())->method('getPricingArray')
            ->willReturn($pricingArray);

        $productModelMock = $this->getMockBuilder('\Model_Product')
            ->onlyMethods(['getTable'])->getMock();
        $productModelMock->expects($this->atLeastOnce())
            ->method('getTable')
            ->willReturn($productTable);

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

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $result = $this->service->removeProduct($cart, random_int(1, 100));
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

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->removeProduct($cart, random_int(1, 100));
        $this->assertTrue($result);
    }

    public function testChangeCartCurrency(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $currency = new \Model_Currency();
        $currency->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->changeCartCurrency($cart, $currency);
        $this->assertTrue($result);
    }

    public function testResetCart(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_Product(), new \Model_Product()]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->resetCart($cart);
        $this->assertTrue($result);
    }

    public function testRemovePromo(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->removePromo($cart);
        $this->assertTrue($result);
    }

    public function testApplyPromo(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_CartProduct(), new \Model_CartProduct()]);

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());
        $promo->id = 2;

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 1;

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testApplyPromoAlreadyApplied(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isEmptyCart'])->getMock();
        $serviceMock->expects($this->never())->method('isEmptyCart')
            ->willReturn(false);

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());
        $promo->id = 5;

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 5;

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testApplyPromoEmptyCartException(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isEmptyCart'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isEmptyCart')
            ->willReturn(true);

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());
        $promo->id = 2;

        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = 1;

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
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

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->rm($cart);
        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromo(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['promoCanBeApplied', 'clientHadUsedPromo'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('clientHadUsedPromo')
            ->willReturn(true);

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());
        $promo->once_per_client = true;

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public function testClientHadUsedPromo(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['promoCanBeApplied'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(random_int(1, 100));

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());
        $promo->once_per_client = true;

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public function testIsClientAbleToUsePromoOncePerClient(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['promoCanBeApplied', 'clientHadUsedPromo'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->willReturn(true);
        $serviceMock->expects($this->never())->method('clientHadUsedPromo')
            ->willReturn(true);

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromoCanNotBeApplied(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['promoCanBeApplied', 'clientHadUsedPromo'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->willReturn(false);
        $serviceMock->expects($this->never())->method('clientHadUsedPromo')
            ->willReturn(true);

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public static function promoCanBeAppliedProvider(): array
    {
        $promo1 = new \Model_Promo();
        $promo1->loadBean(new \DummyBean());
        $promo1->active = false;

        $promo2 = new \Model_Promo();
        $promo2->loadBean(new \DummyBean());
        $promo2->active = true;
        $promo2->maxuses = 5;
        $promo2->used = 5;

        $promo3 = new \Model_Promo();
        $promo3->loadBean(new \DummyBean());
        $promo3->active = true;
        $promo3->maxuses = 10;
        $promo3->used = 5;
        $promo3->start_at = date('c', strtotime('tomorrow'));

        $promo4 = new \Model_Promo();
        $promo4->loadBean(new \DummyBean());
        $promo4->active = true;
        $promo4->maxuses = 10;
        $promo4->used = 5;
        $promo4->start_at = date('c', strtotime('yesterday'));
        $promo4->end_at = date('c', strtotime('yesterday'));

        $promo5 = new \Model_Promo();
        $promo5->loadBean(new \DummyBean());
        $promo5->active = true;
        $promo5->maxuses = 10;
        $promo5->used = 5;
        $promo5->start_at = date('c', strtotime('yesterday'));
        $promo5->end_at = date('c', strtotime('tomorrow'));

        return [
            [$promo1, false],
            [$promo2, false],
            [$promo3, false],
            [$promo4, false],
            [$promo5, true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('promoCanBeAppliedProvider')]
    public function testPromoCanBeApplied(\Model_Promo $promo, bool $expectedResult): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
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

        $di = new \Pimple\Container();
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
        $cart->promo_id = random_int(1, 100);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['createFromCart', 'isClientAbleToUsePromo', 'rm', 'isPromoAvailableForClientGroup'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('createFromCart')
            ->willReturn([$order, random_int(1, 100), [random_int(1, 100)]]);
        $serviceMock->expects($this->atLeastOnce())->method('isClientAbleToUsePromo')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $invoice = new \Model_Invoice();
        $invoice->loadBean(new \DummyBean());
        $invoice->hash = sha1('str');

        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($promo);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = new Request();

        $serviceMock->setDi($di);
        $result = $serviceMock->checkoutCart($cart, $client);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gateway_id', $result);
        $this->assertArrayHasKey('invoice_hash', $result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('orders', $result);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testCheckoutCartClientIsNotAbleToUsePromoException(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->promo_id = random_int(1, 100);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isClientAbleToUsePromo'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isClientAbleToUsePromo')
            ->willReturn(false);

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Promo());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
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
        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->usePromo($promo);

        $this->assertNull($result);
    }

    public function testFindActivePromoByCode(): void
    {
        $promo = new \Model_Promo();
        $promo->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($promo);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findActivePromoByCode('CODE');

        $this->assertInstanceOf('Model_Promo', $result);
    }

    public function testaddItemmRecurringPaymentPeriodParamMissing(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = 'Custom';

        $data = [];

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');
        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting')->getMock();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceHostingServiceMock);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Period parameter not passed'));
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Period parameter not passed');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testaddItemmRecurringPaymentPeriodIsNotEnabled(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = 'hosting';

        $data = ['period' => '1W'];

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting')->getMock();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isPeriodEnabledForProduct'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isPeriodEnabledForProduct')
            ->willReturn(false);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceHostingServiceMock);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Selected billing period is invalid');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testaddItemmOutOfStock(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = 'hosting';

        $data = [];

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting')->getMock();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isStockAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->willReturn(false);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceHostingServiceMock);

        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This item is currently out of stock');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testaddItemmTypeHosting(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = 'hosting';

        $data = [];

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $productDomainModel = new \Model_ProductDomain();
        $productDomainModel->loadBean(new \DummyBean());
        $domainProduct = ['config' => [], 'product' => $productDomainModel];

        $serviceHostingServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceHostingServiceMock->expects($this->atLeastOnce())
            ->method('getDomainProductFromConfig')
            ->willReturn($domainProduct);
        $serviceHostingServiceMock->expects($this->atLeastOnce())
            ->method('prependOrderConfig')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
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

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceHostingServiceMock);
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);
        $productModel->setDi($di);
        $productDomainModel->setDi($di);

        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testaddItemmTypeLicense(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = 'license';

        $data = [];

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceLicenseServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicelicense\Service::class)->getMock();
        $serviceLicenseServiceMock->expects($this->atLeastOnce())
            ->method('attachOrderConfig')
            ->willReturn([]);
        $serviceLicenseServiceMock->expects($this->atLeastOnce())
            ->method('validateOrderData')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($productModel);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceLicenseServiceMock);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testaddItemmTypeCustom(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->type = 'custom';

        $data = [];

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceCustomServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicecustom\Service::class)->getMock();
        $serviceCustomServiceMock->expects($this->atLeastOnce())
            ->method('validateCustomForm');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['isRecurrentPricing', 'isStockAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);
        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($cartProduct);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceCustomServiceMock);
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testtoApiArray(): void
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \DummyBean());

        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getCartProducts', 'cartProductToApiArray'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCartProducts')
            ->willReturn([$cartProductModel]);
        $cartProductApiArray = [
            'total' => 1,
            'discount_price' => 0,
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('cartProductToApiArray')
            ->willReturn($cartProductApiArray);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($currencyModel);

        $currencyService = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

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

    public function testgetProductDiscount(): void
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());
        $modelCart->promo_id = 1;

        $promoModel = new \Model_Promo();
        $promoModel->loadBean(new \DummyBean());

        $discountPrice = 25;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Promo')
            ->willReturn($promoModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
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

    public function testgetProductDiscountNoPromo(): void
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());

        $promoModel = new \Model_Promo();
        $promoModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
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

    public function testgetProductDiscountProductQtyIsSetAndFreeSetup(): void
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \DummyBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \DummyBean());
        $modelCart->promo_id = 1;

        $promoModel = new \Model_Promo();
        $promoModel->loadBean(new \DummyBean());
        $promoModel->freesetup = 1;

        $discountPrice = 25;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Promo')
            ->willReturn($promoModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
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
        $promo1 = new \Model_Promo();
        $promo1->loadBean(new \DummyBean());
        $promo1->client_groups = json_encode([]);

        $client1 = new \Model_Client();
        $client1->loadBean(new \DummyBean());

        $promo2 = new \Model_Promo();
        $promo2->loadBean(new \DummyBean());
        $promo2->client_groups = json_encode([1, 2]);

        $client2 = new \Model_Client();
        $client2->loadBean(new \DummyBean());
        $client2->client_group_id = null;

        $promo3 = new \Model_Promo();
        $promo3->loadBean(new \DummyBean());
        $promo3->client_groups = json_encode([1, 2]);

        $client3 = new \Model_Client();
        $client3->loadBean(new \DummyBean());
        $client3->client_group_id = 3;

        $promo4 = new \Model_Promo();
        $promo4->loadBean(new \DummyBean());
        $promo4->client_groups = json_encode([1, 2]);

        $client4 = new \Model_Client();
        $client4->loadBean(new \DummyBean());
        $client4->client_group_id = 2;

        $promo5 = new \Model_Promo();
        $promo5->loadBean(new \DummyBean());
        $promo5->client_groups = json_encode([]);

        $client5 = null;

        $promo6 = new \Model_Promo();
        $promo6->loadBean(new \DummyBean());
        $promo6->client_groups = json_encode([1, 2]);

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

    #[\PHPUnit\Framework\Attributes\DataProvider('isPromoAvailableForClientGroupProvider')]
    public function testIsPromoAvailableForClientGroup(\Model_Promo $promo, ?\Model_Client $client, bool $expectedResult): void
    {
        $di = new \Pimple\Container();
        $di['loggedin_client'] = $client;
        $this->service->setDi($di);

        $result = $this->service->isPromoAvailableForClientGroup($promo);

        $this->assertEquals($result, $expectedResult);
    }
}
