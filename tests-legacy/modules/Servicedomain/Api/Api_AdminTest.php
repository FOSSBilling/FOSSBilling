<?php

namespace Box\Tests\Mod\Servicedomain\Api;

class Api_AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedomain\Api\Admin
     */
    protected $adminApi;

    public function setup(): void
    {
        $this->adminApi = new \Box\Mod\Servicedomain\Api\Admin();
    }

    public function testUpdate(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['updateDomain'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateDomain')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->update($data);

        $this->assertTrue($result);
    }

    public function testUpdateNameservers(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['updateNameservers'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateNameservers')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->update_nameservers($data);

        $this->assertTrue($result);
    }

    public function testUpdateContacts(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['updateContacts'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateContacts')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->update_contacts($data);

        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['enablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->enable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['disablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->disable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testGetTransferCode(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['getTransferCode'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getTransferCode')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->get_transfer_code($data);

        $this->assertTrue($result);
    }

    public function testLock(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['lock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->lock($data);

        $this->assertTrue($result);
    }

    public function testUnlock(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $adminApiMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Api\Admin::class)
            ->onlyMethods(['_getService'])->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['unlock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unlock')
            ->willReturn(true);

        $adminApiMock->setService($serviceMock);

        $data = [];
        $result = $adminApiMock->unlock($data);

        $this->assertTrue($result);
    }

    public function testTldGetList(): void
    {
        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['tldGetSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldGetSearchQuery')
            ->willReturn(['query', []]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->tld_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTldGet(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $result = $this->adminApi->tld_get($data);

        $this->assertIsArray($result);
    }

    public function testTldGetTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldToApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->adminApi->tld_get($data);
    }

    public function testTldDelete(): void
    {
        $tldMock = new \Model_Tld();
        $tldMock->loadBean(new \DummyBean());
        $tldMock->tld = '.com';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn($tldMock);
        $serviceMock->expects($this->atLeastOnce())->method('tldRm')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->once())->method('find')
            ->with($this->equalTo('ServiceDomain'), $this->equalTo('tld = :tld'), $this->equalTo([':tld' => $tldMock->tld]))
            ->willReturn([]); // No domains found

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);
        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $result = $this->adminApi->tld_delete($data);

        $this->assertTrue($result);
    }

    public function testTldDeleteTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldRm')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->adminApi->tld_delete($data);
    }

    public function testTldCreate(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldAlreadyRegistered')
            ->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())->method('tldCreate')
            ->willReturn(random_int(1, 100));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
            'tld_registrar_id' => random_int(1, 100),
            'price_registration' => random_int(1, 100),
            'price_renew' => random_int(1, 100),
            'price_transfer' => random_int(1, 100),
        ];

        $result = $this->adminApi->tld_create($data);
        $this->assertIsInt($result);
    }

    public function testTldCreateAlreadyRegisteredException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldAlreadyRegistered')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->tld_create($data);
        $this->assertIsInt($result);
    }

    public function testTldUpdate(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(new \Model_Tld());
        $serviceMock->expects($this->atLeastOnce())->method('tldUpdate')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];
        $result = $this->adminApi->tld_update($data);

        $this->assertTrue($result);
    }

    public function testTldUpdateTldNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn(null);
        $serviceMock->expects($this->never())->method('tldUpdate')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'tld' => '.com',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->adminApi->tld_update($data);
    }

    public function testRegistrarGetList(): void
    {
        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['registrarGetSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetSearchQuery')
            ->willReturn(['query', []]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->registrar_get_list($data);

        $this->assertIsArray($result);
    }

    public function testRegistrarGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetPairs')
            ->willReturn([]);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->registrar_get_pairs([]);

        $this->assertIsArray($result);
    }

    public function testRegistrarGetAvailable(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->willReturn([]);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->registrar_get_available([]);

        $this->assertIsArray($result);
    }

    public function testRegistrarInstall(): void
    {
        $registrars = [
            'ResellerClub', 'Custom',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->willReturn($registrars);
        $serviceMock->expects($this->atLeastOnce())->method('registrarCreate')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'code' => 'ResellerClub',
        ];
        $result = $this->adminApi->registrar_install($data);

        $this->assertTrue($result);
    }

    public function testRegistrarInstallRegistrarNotAvailableException(): void
    {
        $registrars = [
            'Custom',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->willReturn($registrars);
        $serviceMock->expects($this->never())->method('registrarCreate')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);
        $this->adminApi->setService($serviceMock);

        $data = [
            'code' => 'ResellerClub',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->registrar_install($data);

        $this->assertTrue($result);
    }

    public function testRegistrarDeleteIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('registrarRm')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->registrar_delete($data);

        $this->assertTrue($result);
    }

    public function testRegistrarCopy(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarCopy')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $this->adminApi->registrar_copy($data);

        $this->assertTrue($result);
    }

    public function testRegistrarCopyIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('registrarCopy')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->registrar_copy($data);

        $this->assertTrue($result);
    }

    public function testRegistrarGet(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarToApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $this->adminApi->registrar_get($data);

        $this->assertIsArray($result);
    }

    public function testRegistrarGetIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('registrarToApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->registrar_get($data);

        $this->assertIsArray($result);
    }

    public function testBatchSyncExpirationDates(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('batchSyncExpirationDates')
            ->willReturn(true);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_sync_expiration_dates([]);

        $this->assertTrue($result);
    }

    public function testRegistrarUpdate(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarUpdate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $this->adminApi->registrar_update($data);

        $this->assertTrue($result);
    }

    public function testRegistrarUpdateIdNotSetException(): void
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn($registrar);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('registrarUpdate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->registrar_update($data);

        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateDomain')
            ->willReturn(true);

        $this->adminApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $data = [
            'order_id' => random_int(1, 100),
        ];
        $result = $this->adminApi->update($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderIdMissingException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('updateDomain')
            ->willReturn(true);

        $this->adminApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->update($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivatedException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceMock->expects($this->never())->method('updateDomain')
            ->willReturn(true);

        $this->adminApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $orderService = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->onlyMethods(['getOrderService'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $data = [
            'order_id' => random_int(1, 100),
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->adminApi->update($data);

        $this->assertTrue($result);
    }
}
