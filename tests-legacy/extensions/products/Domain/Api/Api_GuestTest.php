<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Domain\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_GuestTest extends \BBTestCase
{
    protected ?Guest $guestApi;

    public function setUp(): void
    {
        $this->guestApi = new Guest();
    }

    public function testTlds(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $this->guestApi->setService($serviceMock);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_Tld()]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->guestApi->setDi($di);

        $result = $this->guestApi->guest_tlds([]);
        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testPricing(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $result = $this->guestApi->guest_pricing($data);
        $this->assertIsArray($result);
    }

    public function testPricingTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldToApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->guest_pricing($data);
        $this->assertIsArray($result);
    }

    public function testCheck(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->willReturn(true);

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $result = $this->guestApi->guest_check($data);
        $this->assertTrue($result);
    }

    public function testCheckSldNotValidException(): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(false);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->guest_check($data);
    }

    public function testCheckTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('isDomainAvailable')
            ->willReturn(true);

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->guest_check($data);
    }

    public function testCheckDomainNotAvailableException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->willReturn(false);

        $this->guestApi->setService($serviceMock);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->guest_check($data);
    }

    public function testCanBeTransferred(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('canBeTransferred')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $result = $this->guestApi->guest_can_be_transferred($data);
        $this->assertTrue($result);
    }

    public function testCanBeTransferredTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('canBeTransferred')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->guest_can_be_transferred($data);
    }

    public function testCanBeTransferredCanNotBeTransferredException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('canBeTransferred')
            ->willReturn(false);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->guestApi->setDi($di);
        $this->guestApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->guestApi->guest_can_be_transferred($data);
    }
}
