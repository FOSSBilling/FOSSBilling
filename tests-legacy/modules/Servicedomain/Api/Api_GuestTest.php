<?php

namespace Box\Tests\Mod\Servicedomain\Api;

class Api_GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedomain\Api\Guest
     */
    protected $guestApi;

    public function setup(): void
    {
        $this->guestApi = new \Box\Mod\Servicedomain\Api\Guest();
    }

    public function testTlds(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $this->guestApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_Tld()]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->guestApi->setDi($di);

        $result = $this->guestApi->tlds([]);
        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testPricing(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $result = $this->guestApi->pricing($data);
        $this->assertIsArray($result);
    }

    public function testPricingTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldToApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->pricing($data);
        $this->assertIsArray($result);
    }

    public function testCheck(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->willReturn(true);

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $result = $this->guestApi->check($data);
        $this->assertTrue($result);
    }

    public function testCheckSldNotValidException(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(false);
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->check($data);
    }

    public function testCheckTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('isDomainAvailable')
            ->willReturn(true);

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->check($data);
    }

    public function testCheckDomainNotAvailableException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->willReturn(false);

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->check($data);
    }

    public function testCanBeTransferred(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('canBeTransferred')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $result = $this->guestApi->can_be_transferred($data);
        $this->assertTrue($result);
    }

    public function testCanBeTransferredTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('canBeTransferred')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->can_be_transferred($data);
    }

    public function testCanBeTransferredCanNotBeTransferredException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('canBeTransferred')
            ->willReturn(false);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->can_be_transferred($data);
    }
}
