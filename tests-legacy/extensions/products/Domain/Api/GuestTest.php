<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Domain\Api\Tests;

use FOSSBilling\ProductType\Domain\Api;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
    }

    public function testTlds(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_Tld()]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $result = $this->api->guest_tlds([]);
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
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $result = $this->api->guest_pricing($data);
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
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->guest_pricing($data);
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

        $this->api->setService($serviceMock);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $result = $this->api->guest_check($data);
        $this->assertTrue($result);
    }

    public function testCheckSldNotValidException(): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(false);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->guest_check($data);
    }

    public function testCheckTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('isDomainAvailable')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->guest_check($data);
    }

    public function testCheckDomainNotAvailableException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->willReturn(false);

        $this->api->setService($serviceMock);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->guest_check($data);
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
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $result = $this->api->guest_can_be_transferred($data);
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
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->guest_can_be_transferred($data);
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
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'sld' => 'example',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->guest_can_be_transferred($data);
    }
}
