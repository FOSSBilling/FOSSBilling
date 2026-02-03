<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Domain\Tests\Api;

use FOSSBilling\ProductType\Domain\Api;
use FOSSBilling\ProductType\Domain\Entity\Tld;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
        $this->api->setIdentity(new \Model_Admin());
    }

    public function testTldGetList(): void
    {
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldGetSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldGetSearchQuery')
            ->willReturn(['query', []]);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->admin_tld_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTldGet(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new Tld());
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $result = $this->api->admin_tld_get($data);

        $this->assertIsArray($result);
    }

    public function testTldDelete(): void
    {
        $tldMock = new Tld();
        $prop = new \ReflectionProperty(Tld::class, 'tld');
        $prop->setValue($tldMock, '.com');
        $propId = new \ReflectionProperty(Tld::class, 'id');
        $propId->setValue($tldMock, 1);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn($tldMock);
        $serviceMock->expects($this->atLeastOnce())->method('tldRm')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())->method('find')
            ->with($this->equalTo('ExtProductDomain'), $this->equalTo('tld = :tld'), $this->equalTo([':tld' => '.com']))
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $result = $this->api->admin_tld_delete($data);

        $this->assertTrue($result);
    }

    public function testTldCreate(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldAlreadyRegistered')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())->method('tldCreate')
            ->willReturn(1);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'price_registration' => 10,
            'price_renew' => 10,
        ];
        $result = $this->api->admin_tld_create($data);

        $this->assertEquals(1, $result);
    }

    public function testTldUpdate(): void
    {
        $tldMock = new Tld();
        $prop = new \ReflectionProperty(Tld::class, 'tld');
        $prop->setValue($tldMock, '.com');
        $propId = new \ReflectionProperty(Tld::class, 'id');
        $propId->setValue($tldMock, 1);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn($tldMock);
        $serviceMock->expects($this->atLeastOnce())->method('tldUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'price_registration' => 15,
        ];
        $result = $this->api->admin_tld_update($data);

        $this->assertTrue($result);
    }
}
