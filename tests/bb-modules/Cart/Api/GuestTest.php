<?php
namespace Box\Tests\Mod\Cart\Api;


class GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Cart\Api\Guest
     */
    protected $guestApi = null;

    public function setup(): void
    {
        $this->guestApi = new \Box\Mod\Cart\Api\Guest();
    }

    public function testGet()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue(new \Model_Cart()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $this->guestApi->setService($serviceMock);

        $result = $this->guestApi->get();

        $this->assertIsArray($result);
    }

    public function testReset()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'resetCart'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue(new \Model_Cart()));
        $serviceMock->expects($this->atLeastOnce())->method('resetCart')
            ->will($this->returnValue(true));

        $this->guestApi->setService($serviceMock);

        $result = $this->guestApi->reset();

        $this->assertTrue($result);
    }

    public function testSet_currency()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'changeCartCurrency'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue(new \Model_Cart()));
        $serviceMock->expects($this->atLeastOnce())->method('changeCartCurrency')
            ->will($this->returnValue(true));


        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')
            ->setMethods(array('getByCode'))->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getByCode')
            ->will($this->returnValue(new \Model_Currency()));

        $di                = new \Box_Di();
        $di['validator']   = $validatorMock;
        $di['mod_service'] = $di->protect(function () use ($currencyServiceMock) {
            return $currencyServiceMock;
        });
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'currency' => 'EUR'
        );
        $result = $this->guestApi->set_currency($data);

        $this->assertTrue($result);
    }

    public function testSet_currencyNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'changeCartCurrency'))->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->will($this->returnValue(new \Model_Cart()));
        $serviceMock->expects($this->never())->method('changeCartCurrency')
            ->will($this->returnValue(true));


        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')
            ->setMethods(array('getByCode'))->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getByCode')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['validator']   = $validatorMock;
        $di['mod_service'] = $di->protect(function () use ($currencyServiceMock) {
            return $currencyServiceMock;
        });
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'currency' => 'EUR'
        );
        
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Currency not found');
        $result = $this->guestApi->set_currency($data);
        $this->assertTrue($result);
    }

    public function testGet_currency()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart',))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));


        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')
            ->setMethods(array('toApiArray', 'getDefault'))->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));
        $currencyServiceMock->expects($this->never())->method('getDefault')
            ->will($this->returnValue(new \Model_Currency()));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(new \Model_Currency()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($currencyServiceMock) {
            return $currencyServiceMock;
        });
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'currency' => 'EUR'
        );
        $result = $this->guestApi->get_currency($data);

        $this->assertIsArray($result);
    }

    public function testGet_currencyNotFound()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart',))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));


        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')
            ->setMethods(array('toApiArray', 'getDefault'))->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));
        $currencyServiceMock->expects($this->atLeastOnce())->method('getDefault')
            ->will($this->returnValue(new \Model_Currency()));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($currencyServiceMock) {
            return $currencyServiceMock;
        });
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'currency' => 'EUR'
        );
        $result = $this->guestApi->get_currency($data);

        $this->assertIsArray($result);
    }

    public function testApply_promo()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->atLeastOnce())->method('applyPromo')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->will($this->returnValue(new \Model_Promo()));
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'promocode' => 'CODE'
        );
        $result = $this->guestApi->apply_promo($data);

        $this->assertTrue($result);
    }

    public function testApply_promoNotFoundException()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'))->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->never())->method('applyPromo')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('promoCanBeApplied')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->never())->method('isPromoAvailableForClientGroup')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'promocode' => 'CODE'
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->guestApi->apply_promo($data);
        $this->assertTrue($result);
    }

    public function testApply_promoCanNotBeApplied()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'))->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->never())->method('applyPromo')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->will($this->returnValue(new \Model_Promo()));
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->will($this->returnValue(false));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'promocode' => 'CODE'
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->guestApi->apply_promo($data);

        $this->assertTrue($result);
    }

    public function testApply_promoCanNotBeAppliedForUser()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'applyPromo', 'findActivePromoByCode', 'isPromoAvailableForClientGroup'))->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->never())->method('applyPromo')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->will($this->returnValue(new \Model_Promo()));
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->will($this->returnValue(false));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data   = array(
            'promocode' => 'CODE'
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->guestApi->apply_promo($data);

        $this->assertTrue($result);
    }


    public function testRemove_promo()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'removePromo'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->atLeastOnce())->method('removePromo')
            ->will($this->returnValue(true));

        $this->guestApi->setService($serviceMock);

        $result = $this->guestApi->remove_promo();

        $this->assertTrue($result);
    }

    public function testRemove_item()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'removeProduct'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->atLeastOnce())->method('removeProduct')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = array(
            'id' => rand(1, 100)
        );

        $result = $this->guestApi->remove_item($data);

        $this->assertTrue($result);
    }

    public function testAdd_item()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'addItem'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->atLeastOnce())->method('addItem')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_Product()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = array(
            'id'       => rand(1, 100),
            'multiple' => true
        );

        $result = $this->guestApi->add_item($data);

        $this->assertTrue($result);
    }

    public function testAdd_itemSingle()
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \RedBeanPHP\OODBBean());
        $cart->currency_id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')
            ->setMethods(array('getSessionCart', 'addItem'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->will($this->returnValue($cart));
        $serviceMock->expects($this->atLeastOnce())->method('addItem')
            ->will($this->returnValue(true));


        $apiMock = $this->getMockBuilder('\Box\Mod\Cart\Api\Guest')
            ->setMethods(array('reset'))->getMock();
        $apiMock->expects($this->atLeastOnce())->method('reset')
            ->will($this->returnValue($cart));


        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_Product()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $apiMock->setDi($di);

        $apiMock->setService($serviceMock);

        $data = array(
            'id'       => rand(1, 100),
            'multiple' => false //should reset cart before adding
        );

        $result = $apiMock->add_item($data);

        $this->assertTrue($result);
    }


}
 