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

        $currencyMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($currencyMock);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

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

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn(null);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

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

        $currencyMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($currencyMock);
        $currencyRepositoryMock->expects($this->never())
            ->method('findDefault');

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $di = new \Pimple\Container();
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

        $currencyMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn(null);
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn($currencyMock);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $di = new \Pimple\Container();
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

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('The promo code has expired or does not exist');
        $result = $this->guestApi->apply_promo($data);
        $this->assertTrue($result);
    }
}
