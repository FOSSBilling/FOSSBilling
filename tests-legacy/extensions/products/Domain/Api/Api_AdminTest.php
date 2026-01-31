<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Domain\Api;


use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_AdminTest extends \BBTestCase
{
    protected ?\FOSSBilling\ProductType\Domain\Api\Api $api;

    public function setUp(): void
    {
        $this->api = new \FOSSBilling\ProductType\Domain\Api\Api();
        $this->api->setIdentity(new \Model_Admin());
    }

    public function testUpdate(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['updateDomain'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateDomain')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_update($data);

        $this->assertTrue($result);
    }

    public function testUpdateNameservers(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['updateNameservers'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateNameservers')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_update_nameservers($data);

        $this->assertTrue($result);
    }

    public function testUpdateContacts(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['updateContacts'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateContacts')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_update_contacts($data);

        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['enablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_enable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['disablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_disable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testGetTransferCode(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['getTransferCode'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getTransferCode')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_get_transfer_code($data);

        $this->assertTrue($result);
    }

    public function testLock(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['lock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_lock($data);

        $this->assertTrue($result);
    }

    public function testUnlock(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\Api\Api::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['unlock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unlock')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->admin_unlock($data);

        $this->assertTrue($result);
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
            ->willReturn(new \Model_Tld());
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

    public function testTldGetTldNotFoundException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->admin_tld_get($data);
    }

    public function testTldDelete(): void
    {
        $tldMock = new \Model_Tld();
        $tldMock->loadBean(new \DummyBean());
        $tldMock->tld = '.com';

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn($tldMock);
        $serviceMock->expects($this->atLeastOnce())->method('tldRm')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())->method('find')
            ->with($this->equalTo('ServiceDomain'), $this->equalTo('tld = :tld'), $this->equalTo([':tld' => $tldMock->tld]))
            ->willReturn([]); // No domains found

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

    public function testTldDeleteTldNotFoundException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldRm')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->admin_tld_delete($data);
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
            'tld_registrar_id' => 1,
            'price_registration' => 1,
            'price_renew' => 1,
            'price_transfer' => 1,
        ];

        $result = $this->api->admin_tld_create($data);
        $this->assertIsInt($result);
    }

    public function testTldCreateAlreadyRegisteredException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldAlreadyRegistered')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->admin_tld_create($data);
        $this->assertIsInt($result);
    }

    public function testTldUpdate(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('tldUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $result = $this->api->admin_tld_update($data);

        $this->assertTrue($result);
    }

    public function testTldUpdateTldNotFoundException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->api->admin_tld_update($data);
    }

    public function testRegistrarGetList(): void
    {
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['registrarGetSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetSearchQuery')
            ->willReturn(['query', []]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->admin_registrar_get_list($data);

        $this->assertIsArray($result);
    }

    public function testRegistrarGetPairs(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_registrar_get_pairs([]);

        $this->assertIsArray($result);
    }

    public function testRegistrarGetAvailable(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_registrar_get_available([]);

        $this->assertIsArray($result);
    }

    public function testRegistrarInstall(): void
    {
        $registrars = [
            'ResellerClub', 'Custom',
        ];

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->willReturn($registrars);
        $serviceMock->expects($this->atLeastOnce())->method('registrarCreate')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'code' => 'ResellerClub',
        ];
        $result = $this->api->admin_registrar_install($data);

        $this->assertTrue($result);
    }

    public function testRegistrarInstallRegistrarNotAvailableException(): void
    {
        $registrars = [
            'Custom',
        ];

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->willReturn($registrars);
        $serviceMock->expects($this->never())->method('registrarCreate')
            ->willReturn(true);

        $di = $this->getDi();
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data = [
            'code' => 'ResellerClub',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->admin_registrar_install($data);

        $this->assertTrue($result);
    }

    public function testRegistrarDeleteIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('registrarRm')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($this->api, 'registrar_delete', $data);
        $result = $this->api->admin_registrar_delete($data);

        $this->assertTrue($result);
    }

    public function testRegistrarCopy(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarCopy')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->api->admin_registrar_copy($data);

        $this->assertTrue($result);
    }

    public function testRegistrarCopyIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('registrarCopy')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($this->api, 'registrar_copy', $data);
        $result = $this->api->admin_registrar_copy($data);

        $this->assertTrue($result);
    }

    public function testRegistrarGet(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->api->admin_registrar_get($data);

        $this->assertIsArray($result);
    }

    public function testRegistrarGetIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('registrarToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($this->api, 'registrar_get', $data);
        $result = $this->api->admin_registrar_get($data);

        $this->assertIsArray($result);
    }

    public function testBatchSyncExpirationDates(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('batchSyncExpirationDates')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_batch_sync_expiration_dates([]);

        $this->assertTrue($result);
    }

    public function testRegistrarUpdate(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('registrarUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->api->admin_registrar_update($data);

        $this->assertTrue($result);
    }

    public function testRegistrarUpdateIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('registrarUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($this->api, 'registrar_update', $data);
        $result = $this->api->admin_registrar_update($data);

        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('updateDomain')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $orderService);
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $data = [
            'order_id' => 1,
        ];
        $result = $this->api->admin_update($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderIdMissingException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('updateDomain')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $orderService);
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($this->api, 'update', $data);
        $result = $this->api->admin_update($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivatedException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('updateDomain')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $orderService);
        $di['validator'] = new \FOSSBilling\Validate();

        $this->api->setDi($di);

        $data = [
            'order_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->admin_update($data);

        $this->assertTrue($result);
    }
}
