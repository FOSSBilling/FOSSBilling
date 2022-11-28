<?php
namespace Box\Tests\Mod\Cart;


class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Cart\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Cart\Service();
    }

    public function testDi()
    {
        $service = new \Box\Mod\Cart\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function testGetSearchQuery()
    {
        $service = new \Box\Mod\Cart\Service();
        $result  = $service->getSearchQuery(array());
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertNotFalse(strpos($result[0], 'SELECT cart.id FROM cart'));
    }

    public function testGetSessionCartExists()
    {
        $service = new \Box\Mod\Cart\Service();

        $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';

        $model = new \Model_Cart();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->session_id = $session_id;

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $sessionMock = $this->getMockBuilder("Box_Session")
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method("getId")
            ->will($this->returnValue($session_id));

        $di            = new \Box_Di();
        $di['db']      = $dbMock;
        $di['session'] = $sessionMock;
        $service->setDi($di);

        $result = $service->getSessionCart();

        $this->assertInstanceOf('Model_Cart', $result);
        $this->assertEquals($result->session_id, $session_id);
    }

    public function getSessionCartDoesNotExistProvider()
    {
        return array(
            array(
                100,
                $this->atLeastOnce(),
                $this->never()
            ),
            array(
                null,
                $this->never(),
                $this->atLeastOnce()
            )
        );
    }

    /**
     * @dataProvider getSessionCartDoesNotExistProvider
     */
    public function testGetSessionCartDoesNotExist($sessionGetWillReturn, $getCurrencyByClientIdExpects, $getDefaultExpects)
    {
        $service = new \Box\Mod\Cart\Service();

        $curencyModel = new \Model_Currency();
        $curencyModel->loadBean(new \RedBeanPHP\OODBBean());
        $curencyModel->id = rand(0, 1000);

        $session_id = 'rrcpqo7tkjh14d2vmf0car64k7';
        $model      = null; //Does not exist in database
        $dbMock     = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));
        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($modelCart));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $sessionMock = $this->getMockBuilder("Box_Session")
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method("getId")
            ->will($this->returnValue($session_id));
        $sessionMock->expects($this->atLeastOnce())
            ->method("get")
            ->will($this->returnValue($sessionGetWillReturn));

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->setMethods(array('getCurrencyByClientId', 'getDefault'))->getMock();
        $currencyServiceMock->expects($getCurrencyByClientIdExpects)
            ->method("getCurrencyByClientId")
            ->will($this->returnValue($curencyModel));
        $currencyServiceMock->expects($getDefaultExpects)
            ->method("getDefault")
            ->will($this->returnValue($curencyModel));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['session']     = $sessionMock;
        $di['mod_service'] = $di->protect(function () use ($currencyServiceMock) {
            return $currencyServiceMock;
        });
        $service->setDi($di);

        $result = $service->getSessionCart();

        $this->assertInstanceOf('Model_Cart', $result);
        $this->assertEquals($result->session_id, $session_id);
        $this->assertEquals($result->currency_id, $curencyModel->id);
    }

    public function testIsStockAvailable()
    {
        $product = new \Model_Product();
        $product->loadBean(new \RedBeanPHP\OODBBean());
        $product->stock_control     = true;
        $product->quantity_in_stock = 5;

        $result = $this->service->isStockAvailable($product, 6);
        $this->assertFalse($result);
    }

    public function testIsStockAvailableNoStockControl()
    {
        $product = new \Model_Product();
        $product->loadBean(new \RedBeanPHP\OODBBean());
        $product->stock_control     = false;
        $product->quantity_in_stock = 5;

        $result = $this->service->isStockAvailable($product, 6);
        $this->assertTrue($result);
    }

    public function testIsRecurrentPricing()
    {
        $productTable = $this->getMockBuilder('\Model_ProductTable')->getMock();
        $productTable->expects($this->atLeastOnce())->method('getPricingArray')
            ->will($this->returnValue(array('type' => \Model_ProductPayment::RECURRENT)));

        $productModelMock = $this->getMockBuilder('\Model_Product')
            ->setMethods(array('getTable'))->getMock();
        $productModelMock->expects($this->atLeastOnce())
            ->method('getTable')
            ->will($this->returnValue($productTable));

        $result = $this->service->isRecurrentPricing($productModelMock);

        $this->assertTrue($result);
    }

    public function testIsPeriodEnabledForProduct()
    {
        $enabled            = false;
        $pricingArray       = array(
            'type'      => \Model_ProductPayment::RECURRENT,
            'recurrent' => array(
                'monthly' => array(
                    'enabled' => $enabled
                )
            )
        );
        $productTable = $this->getMockBuilder('\Model_ProductTable')->getMock();
        $productTable->expects($this->atLeastOnce())->method('getPricingArray')
            ->will($this->returnValue($pricingArray));

        $productModelMock = $this->getMockBuilder('\Model_Product')
            ->setMethods(array('getTable'))->getMock();
        $productModelMock->expects($this->atLeastOnce())
            ->method('getTable')
            ->will($this->returnValue($productTable));

        $result = $this->service->isPeriodEnabledForProduct($productModelMock, 'monthly');

        $this->assertIsBool($result);
        $this->assertEquals($result, $enabled);
    }

    public function testIsPeriodEnabledForProductNotRecurrent()
    {
        $enabled            = false;
        $pricingArray       = array(
            'type'      => \Model_ProductPayment::FREE,
            'recurrent' => array(
                'monthly' => array(
                    'enabled' => $enabled
                )
            )
        );
        $productTable = $this->getMockBuilder('\Model_ProductTable')->getMock();
        $productTable->expects($this->atLeastOnce())->method('getPricingArray')
            ->will($this->returnValue($pricingArray));

        $productModelMock = $this->getMockBuilder('\Model_Product')
            ->setMethods(array('getTable'))->getMock();
        $productModelMock->expects($this->atLeastOnce())
            ->method('getTable')
            ->will($this->returnValue($productTable));

        $result = $this->service->isPeriodEnabledForProduct($productModelMock, 'monthly');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testRemoveProduct()
    {
        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($cartProduct));
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($cartProduct)));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->removeProduct($cart, rand(1, 100));
        $this->assertTrue($result);
    }

    public function testRemoveProductCartProductNotFound()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));
        $dbMock->expects($this->never())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $this->expectException(\Box_Exception::class);
        $result = $this->service->removeProduct($cart, rand(1, 100));
        $this->assertTrue($result);
    }

    public function testChangeCartCurrency()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());

        $currency = new \Model_Currency();
        $currency->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->changeCartCurrency($cart, $currency);
        $this->assertTrue($result);
    }

    public function testResetCart()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_Product(), new \Model_Product())));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->resetCart($cart);
        $this->assertTrue($result);
    }

    public function testRemovePromo()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));


        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->removePromo($cart);
        $this->assertTrue($result);
    }

    public function testApplyPromo()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_CartProduct(), new \Model_CartProduct())));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());
        $promo->id = 2;

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->promo_id = 1;

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }


    public function testApplyPromoAlreadyApplied()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isEmptyCart'))->getMock();
        $serviceMock->expects($this->never())->method('isEmptyCart')
            ->will($this->returnValue(false));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());
        $promo->id = 5;

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->promo_id = 5;

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testApplyPromoEmptyCartException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isEmptyCart'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isEmptyCart')
            ->will($this->returnValue(true));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());
        $promo->id = 2;

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->promo_id = 1;

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $serviceMock->applyPromo($cart, $promo);
        $this->assertTrue($result);
    }

    public function testRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_CartProduct())));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->rm($cart);
        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromo()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('promoCanBeApplied', 'clientHadUsedPromo'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('clientHadUsedPromo')
            ->will($this->returnValue(true));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());
        $promo->once_per_client = true;

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public function testClientHadUsedPromo()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('promoCanBeApplied'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(rand(1, 100)));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());
        $promo->once_per_client = true;

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['db']     = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }


    public function testIsClientAbleToUsePromoOncePerClient()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('promoCanBeApplied', 'clientHadUsedPromo'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->never())->method('clientHadUsedPromo')
            ->will($this->returnValue(true));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertTrue($result);
    }

    public function testIsClientAbleToUsePromoCanNotBeApplied()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('promoCanBeApplied', 'clientHadUsedPromo'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->never())->method('clientHadUsedPromo')
            ->will($this->returnValue(true));

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $result = $serviceMock->isClientAbleToUsePromo($client, $promo);
        $this->assertFalse($result);
    }

    public function promoCanBeAppliedProvider()
    {
        $promo1 = new \Model_Promo();
        $promo1->loadBean(new \RedBeanPHP\OODBBean());
        $promo1->active = false;

        $promo2 = new \Model_Promo();
        $promo2->loadBean(new \RedBeanPHP\OODBBean());
        $promo2->active  = true;
        $promo2->maxuses = 5;
        $promo2->used    = 5;

        $promo3 = new \Model_Promo();
        $promo3->loadBean(new \RedBeanPHP\OODBBean());
        $promo3->active   = true;
        $promo3->maxuses  = 10;
        $promo3->used     = 5;
        $promo3->start_at = date("c", strtotime("tomorrow"));

        $promo4 = new \Model_Promo();
        $promo4->loadBean(new \RedBeanPHP\OODBBean());
        $promo4->active   = true;
        $promo4->maxuses  = 10;
        $promo4->used     = 5;
        $promo4->start_at = date("c", strtotime("yesterday"));
        $promo4->end_at   = date("c", strtotime("yesterday"));

        $promo5 = new \Model_Promo();
        $promo5->loadBean(new \RedBeanPHP\OODBBean());
        $promo5->active   = true;
        $promo5->maxuses  = 10;
        $promo5->used     = 5;
        $promo5->start_at = date("c", strtotime("yesterday"));
        $promo5->end_at   = date("c", strtotime("tomorrow"));

        return array(
            array($promo1, false),
            array($promo2, false),
            array($promo3, false),
            array($promo4, false),
            array($promo5, true),
        );
    }

    /**
     * @dataProvider promoCanBeAppliedProvider
     */
    public function testPromoCanBeApplied($promo, $expectedResult)
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->promoCanBeApplied($promo);
        $this->assertEquals($result, $expectedResult);
    }

    public function testGetCartProducts()
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_CartProduct(),)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->getCartProducts($cart);
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_CartProduct', $result[0]);
    }

    public function testCheckoutCart()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->promo_id = rand(1, 100);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('createFromCart', 'isClientAbleToUsePromo', 'rm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('createFromCart')
            ->will($this->returnValue(array($order, rand(1, 100), array(rand(1, 100)))));
        $serviceMock->expects($this->atLeastOnce())->method('isClientAbleToUsePromo')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');


        $requestMock = $this->getMockBuilder('\Box_Request')->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('getClientAddress')
            ->will($this->returnValue('1.1.1.1'));


        $invoice = new \Model_Invoice();
        $invoice->loadBean(new \RedBeanPHP\OODBBean());
        $invoice->hash = sha1('str');

        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($promo));

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $requestMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->checkoutCart($cart, $client);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gateway_id', $result);
        $this->assertArrayHasKey('invoice_hash', $result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('orders', $result);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testCheckoutCartClientIsNotAbleToUsePromoException()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->promo_id = rand(1, 100);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isClientAbleToUsePromo'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isClientAbleToUsePromo')
            ->will($this->returnValue(false));

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_Promo()));

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);
        
        $this->expectException(\Box_Exception::class);
        $result = $serviceMock->checkoutCart($cart, $client);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('gateway_id', $result);
        $this->assertArrayHasKey('invoice_hash', $result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('orders', $result);
    }

    public function testUsePromo()
    {
        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->usePromo($promo);

        $this->assertNull($result);
    }

    public function testFindActivePromoByCode()
    {
        $promo = new \Model_Promo();
        $promo->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($promo));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->findActivePromoByCode('CODE');

        $this->assertInstanceOf('Model_Promo', $result);
    }

    public function testaddItemm_RecurringPaymentPeriodParamMissing()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->type = 'Custom';

        $data = array();

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');
        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting')->getMock();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isRecurrentPricing'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function($name) use($serviceHostingServiceMock) {return $serviceHostingServiceMock;} );
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \Box_Exception('Period parameter not passed'));
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Period parameter not passed');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testaddItemm_RecurringPaymentPeriodIsNotEnabled()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->type = 'hosting';

        $data = array('period' => '1W');

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting')->getMock();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isRecurrentPricing', 'isPeriodEnabledForProduct'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())
            ->method('isPeriodEnabledForProduct')
            ->will($this->returnValue(false));

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function($name) use($serviceHostingServiceMock) {return $serviceHostingServiceMock;} );
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willReturn(null);
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Selected billing period is not valid');
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testaddItemm_OutOfStock()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->type = 'hosting';

        $data = array();

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting')->getMock();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isRecurrentPricing', 'isStockAvailable'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->will($this->returnValue(false));

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function($name) use($serviceHostingServiceMock) {return $serviceHostingServiceMock;} );
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage("I'm afraid we are out of stock.");
        $serviceMock->addItem($cartModel, $productModel, $data);
    }

    public function testaddItemm_TypeHosting()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->type = 'hosting';

        $data = array();

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $productDomainModel = new \Model_ProductDomain();
        $productDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $domainProduct = array('config' => array(), 'product' => $productDomainModel );

        $serviceHostingServiceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceHostingServiceMock->expects($this->atLeastOnce())
            ->method('getDomainProductFromConfig')
            ->will($this->returnValue($domainProduct));
        $serviceHostingServiceMock->expects($this->atLeastOnce())
            ->method('prependOrderConfig')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isRecurrentPricing', 'isStockAvailable', 'addProduct'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())
            ->method('addProduct');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function($name) use($serviceHostingServiceMock) {return $serviceHostingServiceMock;} );
        $di['logger'] = new \Box_Log();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);
        $productModel->setDi($di);
        $productDomainModel->setDi($di);

        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testaddItemm_TypeLicense()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->type = 'license';

        $data = array();

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceLicenseServiceMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Service')->getMock();
        $serviceLicenseServiceMock->expects($this->atLeastOnce())
            ->method('attachOrderConfig')
            ->will($this->returnValue(array()));
        $serviceLicenseServiceMock->expects($this->atLeastOnce())
            ->method('validateOrderData')
            ->will($this->returnValue(true));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isRecurrentPricing', 'isStockAvailable', 'addProduct'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())
            ->method('addProduct');


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($productModel));

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function($name) use($serviceLicenseServiceMock) {return $serviceLicenseServiceMock;} );
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testaddItemm_TypeCustom()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->type = 'custom';

        $data = array();

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $serviceCustomServiceMock = $this->getMockBuilder('\Box\Mod\Servicecustom\Service')->getMock();
        $serviceCustomServiceMock->expects($this->atLeastOnce())
            ->method('validateCustomForm')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('isRecurrentPricing', 'isStockAvailable'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isRecurrentPricing')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));
        $cartProduct = new \Model_CartProduct();
        $cartProduct->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($cartProduct));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function($name) use($serviceCustomServiceMock) {return $serviceCustomServiceMock;} );
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);
        $productModel->setDi($di);

        $result = $serviceMock->addItem($cartModel, $productModel, $data);
        $this->assertTrue($result);
    }

    public function testtoApiArray()
    {
        $cartModel = new \Model_Cart();
        $cartModel->loadBean(new \RedBeanPHP\OODBBean());

        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getCartProducts', 'cartProductToApiArray'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCartProducts')
            ->will($this->returnValue(array($cartProductModel)));
        $cartProductApiArray = array(
            'total' => 1,
            'discount_price' => 0,
        );
        $serviceMock->expects($this->atLeastOnce())
            ->method('cartProductToApiArray')
            ->will($this->returnValue($cartProductApiArray));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($currencyModel));

        $currencyService = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($currencyService) {return $currencyService;});

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($cartModel);

        $expected = array(
            'promocode' => null,
            'discount'  => 0,
            'total'     => 1,
            'items'     => array($cartProductApiArray),
            'currency'  => array(),
            'subtotal' => 1
        );
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testcartProductToApiArray()
    {
        /*
         * @TODO Need to transfer functions from CartProductTable;
         */
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \RedBeanPHP\OODBBean());
    }

    public function testgetProductDiscount()
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \RedBeanPHP\OODBBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \RedBeanPHP\OODBBean());
        $modelCart->promo_id = 1;

        $promoModel = new \Model_Promo();
        $promoModel->loadBean(new \RedBeanPHP\OODBBean());

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

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getRelatedItemsDiscount', 'getItemPromoDiscount', 'getItemConfig'))
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

    public function testgetProductDiscount_NoPromo()
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \RedBeanPHP\OODBBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \RedBeanPHP\OODBBean());

        $promoModel = new \Model_Promo();
        $promoModel->loadBean(new \RedBeanPHP\OODBBean());


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Cart')
            ->willReturn($modelCart);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getRelatedItemsDiscount', 'getItemPromoDiscount', 'getItemConfig'))
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

    public function testgetProductDiscount_ProductQtyIsSetAndFreeSetup()
    {
        $cartProductModel = new \Model_CartProduct();
        $cartProductModel->loadBean(new \RedBeanPHP\OODBBean());

        $modelCart = new \Model_Cart();
        $modelCart->loadBean(new \RedBeanPHP\OODBBean());
        $modelCart->promo_id = 1;

        $promoModel = new \Model_Promo();
        $promoModel->loadBean(new \RedBeanPHP\OODBBean());
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getRelatedItemsDiscount', 'getItemPromoDiscount', 'getItemConfig'))
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

    public function isPromoAvailableForClientGroupProvider()
    {
        $promo1 = new \Model_Promo();
        $promo1->loadBean(new \RedBeanPHP\OODBBean());
        $promo1->client_groups = json_encode(array());

        $client1 = new \Model_Client();
        $client1->loadBean(new \RedBeanPHP\OODBBean());


        $promo2 = new \Model_Promo();
        $promo2->loadBean(new \RedBeanPHP\OODBBean());
        $promo2->client_groups = json_encode(array(1, 2));

        $client2 = new \Model_Client();
        $client2->loadBean(new \RedBeanPHP\OODBBean());
        $client2->client_group_id = null;


        $promo3 = new \Model_Promo();
        $promo3->loadBean(new \RedBeanPHP\OODBBean());
        $promo3->client_groups = json_encode(array(1, 2));

        $client3 = new \Model_Client();
        $client3->loadBean(new \RedBeanPHP\OODBBean());
        $client3->client_group_id = 3;


        $promo4 = new \Model_Promo();
        $promo4->loadBean(new \RedBeanPHP\OODBBean());
        $promo4->client_groups = json_encode(array(1, 2));

        $client4 = new \Model_Client();
        $client4->loadBean(new \RedBeanPHP\OODBBean());
        $client4->client_group_id = 2;


        $promo5 = new \Model_Promo();
        $promo5->loadBean(new \RedBeanPHP\OODBBean());
        $promo5->client_groups = json_encode(array());

        $client5 = null;


        $promo6 = new \Model_Promo();
        $promo6->loadBean(new \RedBeanPHP\OODBBean());
        $promo6->client_groups = json_encode(array(1, 2));

        $client6 = null;


        return array(
            array($promo1, $client1, true), //No client groups set for Promo, any client should be is valid
            array($promo2, $client2, false), //Client groups are set for Promo, but client is not assigned to any client group
            array($promo3, $client3, false), //Client groups are set for Promo, but client group is not included
            array($promo4, $client4, true), //Client groups are set for Promo and it applies to client
            array($promo5, null, true), //No client groups set for Promo, guest should be is valid
            array($promo6, null, false), //Client groups are set for Promo,  guest should be is invalid
        );
    }

    /**
     * @dataProvider isPromoAvailableForClientGroupProvider
     */
    public function testIsPromoAvailableForClientGroup(\Model_Promo $promo, $client, $expectedResult)
    {

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->willReturn(json_decode($promo->client_groups, 1));

        $di                    = new \Box_Di();
        $di['loggedin_client'] = $client;
        $di['tools']           = $toolsMock;
        $this->service->setDi($di);

        $result = $this->service->isPromoAvailableForClientGroup($promo);

        $this->assertEquals($result, $expectedResult);
    }
}