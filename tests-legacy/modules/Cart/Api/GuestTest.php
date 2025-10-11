<?php

namespace Box\Tests\Mod\Cart\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Cart\Api\Guest
     */
    protected $guestApi;

    public function setup(): void
    {
        $this->guestApi = new \Box\Mod\Cart\Api\Guest();
    }

    public function testGet(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn(new \Model_Cart());
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $this->guestApi->setService($serviceMock);

        $result = $this->guestApi->get();

        $this->assertIsArray($result);
    }

    public function testReset(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'resetCart'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn(new \Model_Cart());
        $serviceMock->expects($this->atLeastOnce())->method('resetCart')
            ->willReturn(true);

        $this->guestApi->setService($serviceMock);

        $result = $this->guestApi->reset();

        $this->assertTrue($result);
    }

    public function testSetCurrency(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'changeCartCurrency'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn(new \Model_Cart());
        $serviceMock->expects($this->atLeastOnce())->method('changeCartCurrency')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getByCode'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getByCode')
            ->willReturn(new \Model_Currency());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'currency' => 'EUR',
        ];
        $result = $this->guestApi->set_currency($data);

        $this->assertTrue($result);
    }

    public function testSetCurrencyNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'changeCartCurrency'])->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->willReturn(new \Model_Cart());
        $serviceMock->expects($this->never())->method('changeCartCurrency')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getByCode'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getByCode')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'currency' => 'EUR',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Currency not found');
        $result = $this->guestApi->set_currency($data);
        $this->assertTrue($result);
    }

    public function testGetCurrency(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['toApiArray', 'getDefault'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);
        $currencyServiceMock->expects($this->never())->method('getDefault')
            ->willReturn(new \Model_Currency());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_Currency());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'currency' => 'EUR',
        ];
        $result = $this->guestApi->get_currency();

        $this->assertIsArray($result);
    }

    public function testGetCurrencyNotFound(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['toApiArray', 'getDefault'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);
        $currencyServiceMock->expects($this->atLeastOnce())->method('getDefault')
            ->willReturn(new \Model_Currency());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $currencyServiceMock);
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'currency' => 'EUR',
        ];
        $result = $this->guestApi->get_currency();

        $this->assertIsArray($result);
    }

    public function testApplyPromo(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->atLeastOnce())->method('applyPromo')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->willReturn(new \Model_Promo());
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'promocode' => 'CODE',
        ];
        $result = $this->guestApi->apply_promo($data);

        $this->assertTrue($result);
    }

    public function testApplyPromoNotFoundException(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'])->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->never())->method('applyPromo')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('promoCanBeApplied')
            ->willReturn(true);
        $serviceMock->expects($this->never())->method('isPromoAvailableForClientGroup')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'promocode' => 'CODE',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->apply_promo($data);
        $this->assertTrue($result);
    }

    public function testApplyPromoCanNotBeApplied(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'promoCanBeApplied', 'isPromoAvailableForClientGroup'])->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->never())->method('applyPromo')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->willReturn(new \Model_Promo());
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('promoCanBeApplied')
            ->willReturn(false);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'promocode' => 'CODE',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->apply_promo($data);

        $this->assertTrue($result);
    }

    public function testApplyPromoCanNotBeAppliedForUser(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'applyPromo', 'findActivePromoByCode', 'isPromoAvailableForClientGroup'])->getMock();
        $serviceMock->expects($this->never())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->never())->method('applyPromo')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('findActivePromoByCode')
            ->willReturn(new \Model_Promo());
        $serviceMock->expects($this->atLeastOnce())->method('isPromoAvailableForClientGroup')
            ->willReturn(false);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'promocode' => 'CODE',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->apply_promo($data);

        $this->assertTrue($result);
    }

    public function testRemovePromo(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'removePromo'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->atLeastOnce())->method('removePromo')
            ->willReturn(true);

        $this->guestApi->setService($serviceMock);

        $result = $this->guestApi->remove_promo();

        $this->assertTrue($result);
    }

    public function testRemoveItem(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'removeProduct'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->atLeastOnce())->method('removeProduct')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];

        $result = $this->guestApi->remove_item($data);

        $this->assertTrue($result);
    }

    public function testAddItem(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'addItem'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->atLeastOnce())->method('addItem')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Product());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
            'multiple' => true,
        ];

        $result = $this->guestApi->add_item($data);

        $this->assertTrue($result);
    }

    public function testAddItemSingle(): void
    {
        $cart = new \Model_Cart();
        $cart->loadBean(new \DummyBean());
        $cart->currency_id = random_int(1, 100);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSessionCart', 'addItem'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSessionCart')
            ->willReturn($cart);
        $serviceMock->expects($this->atLeastOnce())->method('addItem')
            ->willReturn(true);

        $apiMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Api\Guest::class)
            ->onlyMethods(['reset'])->getMock();
        $apiMock->expects($this->atLeastOnce())->method('reset')
            ->willReturn($cart);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Product());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $apiMock->setDi($di);

        $apiMock->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
            'multiple' => false, // should reset cart before adding
        ];

        $result = $apiMock->add_item($data);

        $this->assertTrue($result);
    }
}
