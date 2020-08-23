<?php
namespace Box\Tests\Mod\Servicedomain\Api;

class Api_AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedomain\Api\Admin
     */
    protected $adminApi = null;

    public function setup(): void
    {
        $this->adminApi = new \Box\Mod\Servicedomain\Api\Admin();
    }

    public function testUpdate()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('updateDomain'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateDomain')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->update($data);

        $this->assertTrue($result);
    }

    public function testUpdate_nameservers()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('updateNameservers'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateNameservers')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->update_nameservers($data);

        $this->assertTrue($result);
    }

    public function testUpdate_contacts()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('updateContacts'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateContacts')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->update_contacts($data);

        $this->assertTrue($result);
    }

    public function testEnable_privacy_protection()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('enablePrivacyProtection'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->enable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testDisable_privacy_protection()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('disablePrivacyProtection'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->disable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testGet_transfer_code()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('getTransferCode'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getTransferCode')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->get_transfer_code($data);

        $this->assertTrue($result);
    }

    public function testLock()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('lock'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->lock($data);

        $this->assertTrue($result);
    }

    public function testUnlock()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $adminApiMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Api\Admin')
            ->setMethods(array('_getService'))->getMock();
        $adminApiMock->expects($this->atLeastOnce())->method('_getService')
            ->will($this->returnValue($model));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('unlock'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unlock')
            ->will($this->returnValue(true));

        $adminApiMock->setService($serviceMock);

        $data   = array();
        $result = $adminApiMock->unlock($data);

        $this->assertTrue($result);
    }

    public function testTld_get_list()
    {
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array('list' => array())));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldGetSearchQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldGetSearchQuery')
            ->will($this->returnValue(array('query', array())));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->tld_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTld_get()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('tldToApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'tld' => '.com'
        );
        $result = $this->adminApi->tld_get($data);

        $this->assertIsArray($result);
    }

    public function testTld_getTldNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('tldToApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array(
            'tld' => '.com'
        );
        $this->expectException(\Box_Exception::class);
        $this->adminApi->tld_get($data);
    }

    public function testTld_delete()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('tldRm')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'tld' => '.com'
        );
        $result = $this->adminApi->tld_delete($data);

        $this->assertIsArray($result);
    }

    public function testTld_deleteTldNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('tldRm')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array(
            'tld' => '.com'
        );

        $this->expectException(\Box_Exception::class);
        $this->adminApi->tld_delete($data);
    }

    public function testTld_create()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldAlreadyRegistered')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->atLeastOnce())->method('tldCreate')
            ->will($this->returnValue(rand(1, 100)));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array(
            'tld'                => '.com',
            'tld_registrar_id'   => rand(1, 100),
            'price_registration' => rand(1, 100),
            'price_renew'        => rand(1, 100),
            'price_transfer'     => rand(1, 100),

        );

        $result = $this->adminApi->tld_create($data);
        $this->assertIsInt($result);
    }

    public function testTld_createAlreadyRegisteredException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldAlreadyRegistered')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array(
            'tld' => '.com'
        );

        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->tld_create($data);
        $this->assertIsInt($result);
    }


    public function testTld_update()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(new \Model_Tld()));
        $serviceMock->expects($this->atLeastOnce())->method('tldUpdate')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);


        $this->adminApi->setService($serviceMock);

        $data   = array(
            'tld' => '.com'
        );
        $result = $this->adminApi->tld_update($data);

        $this->assertIsArray($result);
    }

    public function testTld_updateTldNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->never())->method('tldUpdate')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);


        $this->adminApi->setService($serviceMock);

        $data = array(
            'tld' => '.com'
        );

        $this->expectException(\Box_Exception::class);
        $this->adminApi->tld_update($data);
    }

    public function testRegistrar_get_list()
    {
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array('list' => array())));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('registrarGetSearchQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetSearchQuery')
            ->will($this->returnValue(array('query', array())));


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array()));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->registrar_get_list($data);

        $this->assertIsArray($result);
    }

    public function testRegistrar_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetPairs')
            ->will($this->returnValue(array()));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->registrar_get_pairs(array());

        $this->assertIsArray($result);
    }

    public function testRegistrar_get_available()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->will($this->returnValue(array()));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->registrar_get_available(array());

        $this->assertIsArray($result);
    }

    public function testRegistrar_install()
    {
        $registrars = array(
            'ResellerClub', 'Custom'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->will($this->returnValue($registrars));
        $serviceMock->expects($this->atLeastOnce())->method('registrarCreate')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'code' => 'ResellerClub'
        );
        $result = $this->adminApi->registrar_install($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_installRegistrarNotAvailableException()
    {
        $registrars = array(
            'Custom'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetAvailable')
            ->will($this->returnValue($registrars));
        $serviceMock->expects($this->never())->method('registrarCreate')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);
        $this->adminApi->setService($serviceMock);

        $data   = array(
            'code' => 'ResellerClub'
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->registrar_install($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_delete()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarRm')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->registrar_delete($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_deleteIdNotSetException()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('registrarRm')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \Box_Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array();

        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->registrar_delete($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_copy()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarCopy')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->registrar_copy($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_copyIdNotSetException()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('registrarCopy')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \Box_Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array();

        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->registrar_copy($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_get()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarToApiArray')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->registrar_get($data);

        $this->assertIsArray($result);
    }

    public function testRegistrar_getIdNotSetException()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('registrarToApiArray')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \Box_Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array();

        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->registrar_get($data);

        $this->assertIsArray($result);
    }

    public function testBatch_sync_expiration_dates()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('batchSyncExpirationDates')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_sync_expiration_dates(array());

        $this->assertTrue($result);
    }

    public function testRegistrar_update()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarUpdate')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->registrar_update($data);

        $this->assertTrue($result);
    }

    public function testRegistrar_updateIdNotSetException()
    {
        $registrar = new \Model_TldRegistrar();
        $registrar->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->will($this->returnValue($registrar));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('registrarUpdate')
            ->will($this->returnValue(true));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \Box_Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = array();

        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->registrar_update($data);

        $this->assertTrue($result);
    }

    public function testGetService()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateDomain')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceDomain()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $data   = array(
            'order_id' => rand(1, 100)
        );
        $result = $this->adminApi->update($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderIdMissingException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('updateDomain')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('load')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceDomain()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \Box_Exception('Registrar ID is missing'));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $data   = array();
        
        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->update($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivatedException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->never())->method('updateDomain')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $orderService = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderService'))->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderService) {
            return $orderService;
        });
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willReturn(null);
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $data   = array(
            'order_id' => rand(1, 100)
        );
        
        $this->expectException(\Box_Exception::class);
        $result = $this->adminApi->update($data);

        $this->assertTrue($result);
    }


}
 