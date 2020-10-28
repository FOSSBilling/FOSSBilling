<?php

namespace Box\Tests\Mod\Servicedomain;


class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedomain\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Servicedomain\Service();
    }

    public function testDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function getCartProductTitleProvider()
    {
        return array(
            array(
                array(
                    'action'       => 'register',
                    'register_tld' => '.com',
                    'register_sld' => 'example'
                ),
                "Domain example.com registration"
            ),
            array(
                array(
                    'action'       => 'transfer',
                    'transfer_tld' => '.com',
                    'transfer_sld' => 'example'
                ),
                "Domain example.com transfer"
            ),
            array(
                array(),
                "Example.com Registration"
            )
        );
    }

    /**
     * @dataProvider getCartProductTitleProvider
     */
    public function testGetCartProductTitle($data, $expected)
    {
        $product = new \Model_CartProduct();
        $product->loadBean(new \RedBeanPHP\OODBBean());
        $product->title = "Example.com Registration";

        $result = $this->service->getCartProductTitle($product, $data);

        $this->assertEquals($result, $expected);
    }

    public function validateOrderDataProvider()
    {
        return array(
            array(
                array(
                    'action'        => 'owndomain',
                    'owndomain_sld' => 'example',
                    'owndomain_tld' => '.com'
                ),
                $this->never(),
                $this->never(),
                $this->never()
            ),
            array(
                array(
                    'action'       => 'transfer',
                    'transfer_sld' => 'example',
                    'transfer_tld' => '.com'
                ),
                $this->atLeastOnce(),
                $this->atLeastOnce(),
                $this->never()
            ),
            array(
                array(
                    'action'         => 'register',
                    'register_sld'   => 'example',
                    'register_tld'   => '.com',
                    'register_years' => '2'
                ),
                $this->atLeastOnce(),
                $this->never(),
                $this->atLeastOnce()
            )
        );
    }

    /**
     * @dataProvider validateOrderDataProvider
     */
    public function testValidateOrderData($data, $finOneByTldCalled, $canBeTransferedCalled, $isDomainAvailableCalled)
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(true));

        $tld = new \Model_Tld();
        $tld->loadBean(new \RedBeanPHP\OODBBean());
        $tld->tld       = '.com';
        $tld->min_years = 2;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'canBeTransfered', 'isDomainAvailable'))->getMock();

        $serviceMock->expects($finOneByTldCalled)->method('tldFindOneByTld')
            ->will($this->returnValue($tld));
        $serviceMock->expects($canBeTransferedCalled)->method('canBeTransfered')
            ->will($this->returnValue(true));
        $serviceMock->expects($isDomainAvailableCalled)->method('isDomainAvailable')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->validateOrderData($data);
        $this->assertNull($result);
    }

    public function validateOrderDataExceptionsProvider()
    {
        return array(
            array(
                array(
                    'action' => 'NonExistingAction'
                ),
            )
        );
    }

    /**
     * @dataProvider validateOrderDataExceptionsProvider
     */
    public function testValidateOrderDataExceptions($data)
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $this->service->validateOrderData($data);
        $this->assertNull($result);
    }

    public function validateOrderDateOwndomainExceptionsProvider()
    {
        return array(
            array(
                array( //"owndomain_sld" is missing
                    'action'        => 'owndomain',
                    'owndomain_tld' => '.com'
                ),
                $this->never(),
                true,

            ),
            array(
                array(
                    'action'        => 'owndomain',
                    'owndomain_sld' => 'example',
                    'owndomain_tld' => '.com'
                ),
                $this->atLeastOnce(),
                false ////"isSldValid" returns false
            ),
        );
    }

    /**
     * @dataProvider validateOrderDateOwndomainExceptionsProvider
     */
    public function testValidateOrderDateOwndomainOwndomain($data, $isSldValidCalled, $isSldValidReturn)
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($isSldValidCalled)->method('isSldValid')
            ->will($this->returnValue($isSldValidReturn));
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $this->service->validateOrderData($data);
        $this->assertNull($result);
    }


    public function validateOrderDateTransferExceptionsProvider()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld = '.com';

        return array(
            array(
                array(
                    'action'       => 'transfer',
                    'transfer_sld' => 'example',
                    'transfer_tld' => '.com',
                ),
                array( //isSldValidArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => true
                ),
                array( //tldFindOneByTldArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => null
                ),
                array( //canBeTransfered
                    'called'  => $this->never(),
                    'returns' => true
                )
            ),
            array(
                array(
                    'action'       => 'transfer',
                    'transfer_sld' => 'example',
                    'transfer_tld' => '.com',
                ),
                array( //isSldValidArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => true
                ),
                array( //tldFindOneByTldArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => $tldModel
                ),
                array( //canBeTransfered
                    'called'  => $this->atLeastOnce(),
                    'returns' => false
                )
            )
        );
    }

    /**
     * @dataProvider validateOrderDateTransferExceptionsProvider
     */
    public function testValidateOrderDateTransferExceptions($data, $isSldValidArr, $tldFindOneByTldArr, $canBeTransfered)
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($isSldValidArr['called'])->method('isSldValid')
            ->will($this->returnValue($isSldValidArr['returns']));
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'canBeTransfered'))->getMock();
        $serviceMock->expects($tldFindOneByTldArr['called'])->method('tldFindOneByTld')
            ->will($this->returnValue($tldFindOneByTldArr['returns']));
        $serviceMock->expects($canBeTransfered['called'])->method('canBeTransfered')
            ->will($this->returnValue($canBeTransfered['returns']));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $serviceMock->validateOrderData($data);
        $this->assertNull($result);
    }

    public function validateOrderDateRegisterExceptionsProvider()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld       = '.com';
        $tldModel->min_years = 2;

        return array(
            array(
                array(
                    'action'         => 'register',
                    'register_sld'   => 'example',
                    'register_years' => 2,
                    'register_tld'   => '.com',
                ),
                array( //isSldValidArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => true
                ),
                array( //tldFindOneByTldArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => null
                ),
                array( //isDomainAvailable
                    'called'  => $this->never(),
                    'returns' => true
                )
            ),
            array(
                array(
                    'action'         => 'register',
                    'register_sld'   => 'example',
                    'register_years' => $tldModel->min_years - 1, //less years than required
                    'register_tld'   => '.com',
                ),
                array( //isSldValidArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => true
                ),
                array( //tldFindOneByTldArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => $tldModel
                ),
                array( //isDomainAvailable
                    'called'  => $this->never(),
                    'returns' => true
                )
            ),
            array(
                array(
                    'action'         => 'register',
                    'register_sld'   => 'example',
                    'register_tld'   => '.com',
                    'register_years' => 2,
                ),
                array( //isSldValidArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => true
                ),
                array( //tldFindOneByTldArr
                    'called'  => $this->atLeastOnce(),
                    'returns' => $tldModel
                ),
                array( //isDomainAvailable
                    'called'  => $this->atLeastOnce(),
                    'returns' => false
                )
            )
        );
    }

    /**
     * @dataProvider validateOrderDateRegisterExceptionsProvider
     */
    public function testValidateOrderDateRegisterExceptions($data, $isSldValidArr, $tldFindOneByTldArr, $canBeTransfered)
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($isSldValidArr['called'])->method('isSldValid')
            ->will($this->returnValue($isSldValidArr['returns']));
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'isDomainAvailable'))->getMock();
        $serviceMock->expects($tldFindOneByTldArr['called'])->method('tldFindOneByTld')
            ->will($this->returnValue($tldFindOneByTldArr['returns']));
        $serviceMock->expects($canBeTransfered['called'])->method('isDomainAvailable')
            ->will($this->returnValue($canBeTransfered['returns']));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $serviceMock->validateOrderData($data);
        $this->assertNull($result);
    }

    public function testActionCreate()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld_registrar_id = rand(1, 100);

        $data = array(
            'action'         => 'register',
            'register_sld'   => 'example',
            'register_tld'   => '.com',
            'register_years' => 2,
        );

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getConfig'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getConfig')
            ->will($this->returnValue($data));

        $nameservers       = array(
            'nameserver_1' => 'ns1.example.com',
            'nameserver_2' => 'ns2.example.com',
            'nameserver_3' => 'ns3.example.com',
            'nameserver_4' => 'ns4.example.com',
        );
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')
            ->setMethods(array('getNameservers'))->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getNameservers')
            ->will($this->returnValue($nameservers));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('tldFindOneByTld', 'validateOrderData'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->will($this->returnValue($tldModel));
        $serviceMock->expects($this->atLeastOnce())->method('validateOrderData')
            ->will($this->returnValue(null));

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->first_name = 'first_name';
        $client->last_name  = 'last_name';
        $client->email      = 'email';
        $client->company    = 'company';
        $client->address_1  = 'address_1';
        $client->address_2  = 'address_2';
        $client->country    = 'country';
        $client->city       = 'city';
        $client->state      = 'state';
        $client->postcode   = 'postcode';
        $client->phone_cc   = 'phone_cc';
        $client->phone      = 'phone';

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($client));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($serviceDomainModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
            if ($name == 'order') {
                return $orderServiceMock;
            } else {
                return $systemServiceMock;
            }
        });
        $di['db']          = $dbMock;
        $di['array_get']   = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);


        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);

        $result = $serviceMock->action_create($order);
        $this->assertInstanceOf('Model_ServiceDomain', $result);
    }

    public function testActionCreateNameserversException()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld_registrar_id = rand(1, 100);

        $data = array(
            'action'         => 'register',
            'register_sld'   => 'example',
            'register_tld'   => '.com',
            'register_years' => 2,
        );

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getConfig'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getConfig')
            ->will($this->returnValue($data));

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')
            ->setMethods(array('getNameservers'))->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getNameservers')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('validateOrderData'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('validateOrderData')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
            if ($name == 'order') {
                return $orderServiceMock;
            } else {
                return $systemServiceMock;
            }
        });
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $this->expectException(\Box_Exception::class);
        $serviceMock->action_create($order);
    }

    public function actionActivateProvider()
    {
        return array(
            array(
                'register',
                $this->atLeastOnce(),
                $this->never()
            ),
            array(
                'transfer',
                $this->never(),
                $this->atLeastOnce()
            )
        );
    }

    /**
     * @dataProvider actionActivateProvider
     */
    public function testActionActivate($action, $registerDomainCalled, $transferDomainCalled)
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld_registrar_id = rand(1, 100);

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \RedBeanPHP\OODBBean());
        $domainModel->tld_registrar_id = rand(1, 100);
        $domainModel->action           = $action;

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue($domainModel));

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($registerDomainCalled)->method('registerDomain')
            ->will($this->returnValue(null));
        $registrarAdapterMock->expects($transferDomainCalled)->method('transferDomain')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD', 'syncWhois'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));
        $serviceMock->expects($this->atLeastOnce())->method('syncWhois')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $di['db']          = $dbMock;
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $result           = $serviceMock->action_activate($order);
        $this->assertInstanceOf('Model_ServiceDomain', $result);
    }

    public function testActionActivateServiceNotFoundException()
    {
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        
        $this->expectException(\Box_Exception::class);
        $this->service->action_activate($order);
    }

    public function testActionRenew()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld_registrar_id = rand(1, 100);

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \RedBeanPHP\OODBBean());
        $domainModel->tld_registrar_id = rand(1, 100);
        $domainModel->action           = 'register';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue($domainModel));

        $registrarDomainMock = $this->getMockBuilder('Registrar_Domain')->disableOriginalConstructor()
            ->getMock();
        $registrarDomainMock->expects($this->atLeastOnce())->method('getContactRegistrar')
            ->will($this->returnValue(new \Registrar_Domain_Contact()));
        $registrarDomainMock->expects($this->atLeastOnce())->method('getLocked')
            ->will($this->returnValue(true));

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('renewDomain')
            ->will($this->returnValue(null));
        $registrarAdapterMock->expects($this->atLeastOnce())->method('getDomainDetails')
            ->will($this->returnValue($registrarDomainMock));


        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $result           = $serviceMock->action_renew($order);

        $this->assertTrue($result);
    }

    public function testActionRenewServiceNotFoundException()
    {
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->id        = rand(1, 100);
        $order->client_id = rand(1, 100);
        
        $this->expectException(\Box_Exception::class);
        $result           = $this->service->action_renew($order);

        $this->assertTrue($result);
    }

    public function testActionSuspend()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->action_suspend($order);
        $this->assertTrue($result);
    }

    public function testActionUnsuspend()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->action_unsuspend($order);
        $this->assertTrue($result);
    }

    public function testActionCancel()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld_registrar_id = rand(1, 100);

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \RedBeanPHP\OODBBean());
        $domainModel->tld_registrar_id = rand(1, 100);
        $domainModel->action           = 'register';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue($domainModel));

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('deleteDomain')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));


        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $result           = $serviceMock->action_cancel($order);

        $this->assertTrue($result);
    }

    public function testActionCancelServiceNotFoundException()
    {
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->id        = rand(1, 100);
        $order->client_id = rand(1, 100);
        
        $this->expectException(\Box_Exception::class);
        $result           = $this->service->action_cancel($order);

        $this->assertTrue($result);
    }

    public function testActionUncancel()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('action_activate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('action_activate')
            ->will($this->returnValue(null));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->client_id = rand(1, 100);
        $result           = $serviceMock->action_uncancel($order);

        $this->assertTrue($result);
    }

    public function testActionDelete()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->tld_registrar_id = rand(1, 100);

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \RedBeanPHP\OODBBean());
        $domainModel->tld_registrar_id = rand(1, 100);
        $domainModel->action           = 'register';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderService'))->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->will($this->returnValue($domainModel));

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('deleteDomain')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock) {
            return $orderServiceMock;
        });
        $di['db']          = $dbMock;
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $result        = $serviceMock->action_delete($order);

        $this->assertNull($result);
    }

    public function testUpdateNameservers()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('modifyNs')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $data = array(
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
            'ns3' => 'ns3.example.com',
            'ns4' => 'ns4.example.com',
        );

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->updateNameservers($serviceDomainModel, $data);

        $this->assertTrue($result);
    }

    public function updateNameserversExceptionProvider()
    {
        return array(
            array(
                array('ns2' => 'ns2.example.com') //ns1 is missing
            ),
            array(
                array('ns1' => 'ns1.example.com') //ns2 is missing
            ),
        );
    }

    /**
     * @dataProvider updateNameserversExceptionProvider
     */
    public function testUpdateNameserversException($data)
    {
        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        
        $this->expectException(\Box_Exception::class);
        $this->service->updateNameservers($serviceDomainModel, $data);
    }

    public function testUpdateContacts()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('modifyContact')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray')
            ->will($this->returnValue(true));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);


        $data               = array(
            'contact' => array(
                'first_name' => 'first_name',
                'last_name'  => 'last_name',
                'email'      => 'email',
                'company'    => 'company',
                'address1'   => 'address1',
                'address2'   => 'address2',
                'country'    => 'country',
                'city'       => 'city',
                'state'      => 'state',
                'postcode'   => 'postcode',
                'phone_cc'   => 'phone_cc',
                'phone'      => 'phone',
            )
        );
        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->updateContacts($serviceDomainModel, $data);

        $this->assertTrue($result);
    }

    public function testGetTransferCode()
    {
        $epp = 'EPPCODE';

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('getEpp')
            ->will($this->returnValue($epp));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->getTransferCode($serviceDomainModel);

        $this->assertIsString($epp);
        $this->assertEquals($result, $epp);
    }

    public function testLock()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('lock')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->lock($serviceDomainModel);

        $this->assertTrue($result);
    }


    public function testUnlock()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('unlock')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->unlock($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->enablePrivacyProtection($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('_getD'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->will($this->returnValue(array(new \Registrar_Domain(), $registrarAdapterMock)));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $serviceMock->disablePrivacyProtection($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testCanBeTransfered()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('isDomainCanBeTransfered')
            ->will($this->returnValue(true));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('registrarGetRegistrarAdapter'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapter')
            ->will($this->returnValue($registrarAdapterMock));

        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \RedBeanPHP\OODBBean());
        $tldRegistrar->tld_registrar_id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($tldRegistrar));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $tld = new \Model_Tld();
        $tld->loadBean(new \RedBeanPHP\OODBBean());
        $tld->allow_transfer   = true;
        $tld->tld              = '.com';
        $tld->tld_registrar_id = rand(1, 100);

        $result = $serviceMock->canBeTransfered($tld, 'example');

        $this->assertTrue($result);
    }

    public function testCanBeTransferedEmptySldException()
    {
        $this->expectException(\Box_Exception::class);
        $this->service->canBeTransfered(new \Model_Tld(), '');
    }

    public function testCanBeTransferedNotAllowedException()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $tldModel->allow_transfer = false;

        $this->expectException(\Box_Exception::class);
        $this->service->canBeTransfered($tldModel, 'example');
    }

    public function testIsDomainAvailable()
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->will($this->returnValue(true));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('registrarGetRegistrarAdapter'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapter')
            ->will($this->returnValue($registrarAdapterMock));

        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \RedBeanPHP\OODBBean());
        $tldRegistrar->tld_registrar_id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($tldRegistrar));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $tld = new \Model_Tld();
        $tld->loadBean(new \RedBeanPHP\OODBBean());
        $tld->allow_register   = true;
        $tld->tld              = '.com';
        $tld->tld_registrar_id = rand(1, 100);

        $result = $serviceMock->isDomainAvailable($tld, 'example');

        $this->assertTrue($result);
    }

    public function testIsDomainAvailableEmptySldException()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $this->expectException(\Box_Exception::class);
        $this->service->isDomainAvailable($tldModel, '');
    }

    public function testIsDomainAvailableSldNotValidException()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(false));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $this->expectException(\Box_Exception::class);
        $this->service->isDomainAvailable($tldModel, 'example');
    }

    public function testIsDomainAvailableSldNotAllowedToRegisterException()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->allow_register = false;

        $this->expectException(\Box_Exception::class);
        $this->service->isDomainAvailable($model, 'example');
    }

    public function testSyncExpirationDate()
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->syncExpirationDate($model);

        $this->assertNull($result);
    }

    public function toApiArrayProvider()
    {
        $model = new \Model_Admin();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        return array(
            array(
                $model,
                $this->atLeastOnce()
            ),
            array(
                null,
                $this->never()
            )
        );
    }

    /**
     * @dataProvider toApiArrayProvider
     */
    public function testToApiArray($identity, $dbLoadCalled)
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $model->sld           = 'sld';
        $model->tld           = 'tld';
        $model->ns1           = 'ns1.example.com';
        $model->ns2           = 'ns2.example.com';
        $model->ns3           = 'ns3.example.com';
        $model->ns4           = 'ns4.example.com';
        $model->period        = 'period';
        $model->privacy       = 'privacy';
        $model->locked        = 'locked';
        $model->registered_at = date('Y-m-d H:i:s');
        $model->expires_at    = date('Y-m-d H:i:s');

        $model->contact_first_name = 'first_name';
        $model->contact_last_name  = 'last_name';
        $model->contact_email      = 'email';
        $model->contact_company    = 'company';
        $model->contact_address1   = 'address1';
        $model->contact_address2   = 'address2';
        $model->contact_country    = 'country';
        $model->contact_city       = 'city';
        $model->contact_state      = 'state';
        $model->contact_postcode   = 'postcode';
        $model->contact_phone_cc   = 'phone_cc';
        $model->contact_phone      = 'phone';
        $model->transfer_code      = 'EPPCODE';
        $model->tld_registrar_id   = rand(1, 100);

        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \RedBeanPHP\OODBBean());
        $tldRegistrar->name = 'ResellerClub';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($dbLoadCalled)
            ->method('load')
            ->will($this->returnValue($tldRegistrar));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->toApiArray($model, true, $identity);

        $this->assertIsArray($result);

        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('sld', $result);
        $this->assertArrayHasKey('tld', $result);
        $this->assertArrayHasKey('ns1', $result);
        $this->assertArrayHasKey('ns2', $result);
        $this->assertArrayHasKey('ns3', $result);
        $this->assertArrayHasKey('ns4', $result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('privacy', $result);
        $this->assertArrayHasKey('locked', $result);
        $this->assertArrayHasKey('registered_at', $result);
        $this->assertArrayHasKey('expires_at', $result);

        $this->assertArrayHasKey('contact', $result);
        $contact = $result['contact'];
        $this->assertIsArray($contact);
        $this->assertArrayHasKey('first_name', $contact);
        $this->assertArrayHasKey('last_name', $contact);
        $this->assertArrayHasKey('email', $contact);
        $this->assertArrayHasKey('company', $contact);
        $this->assertArrayHasKey('address1', $contact);
        $this->assertArrayHasKey('address2', $contact);
        $this->assertArrayHasKey('country', $contact);
        $this->assertArrayHasKey('city', $contact);
        $this->assertArrayHasKey('state', $contact);
        $this->assertArrayHasKey('postcode', $contact);
        $this->assertArrayHasKey('phone_cc', $contact);
        $this->assertArrayHasKey('phone', $contact);

        $this->assertEquals($result['domain'], $model->sld . $model->tld);
        $this->assertEquals($result['sld'], $model->sld);
        $this->assertEquals($result['tld'], $model->tld);
        $this->assertEquals($result['ns1'], $model->ns1);
        $this->assertEquals($result['ns2'], $model->ns2);
        $this->assertEquals($result['ns3'], $model->ns3);
        $this->assertEquals($result['ns4'], $model->ns4);
        $this->assertEquals($result['period'], $model->period);
        $this->assertEquals($result['privacy'], $model->privacy);
        $this->assertEquals($result['locked'], $model->locked);
        $this->assertEquals($result['registered_at'], $model->registered_at);
        $this->assertEquals($result['expires_at'], $model->expires_at);

        $this->assertEquals($contact['first_name'], $model->contact_first_name);
        $this->assertEquals($contact['last_name'], $model->contact_last_name);
        $this->assertEquals($contact['email'], $model->contact_email);
        $this->assertEquals($contact['company'], $model->contact_company);
        $this->assertEquals($contact['address1'], $model->contact_address1);
        $this->assertEquals($contact['address2'], $model->contact_address2);
        $this->assertEquals($contact['country'], $model->contact_country);
        $this->assertEquals($contact['city'], $model->contact_city);
        $this->assertEquals($contact['state'], $model->contact_state);
        $this->assertEquals($contact['postcode'], $model->contact_postcode);
        $this->assertEquals($contact['phone_cc'], $model->contact_phone_cc);
        $this->assertEquals($contact['phone'], $model->contact_phone);

        if ($identity instanceof \Model_Admin) {
            $this->assertArrayHasKey('transfer_code', $result);
            $this->assertArrayHasKey('registrar', $result);

            $this->assertEquals($result['transfer_code'], $model->transfer_code);
            $this->assertEquals($result['registrar'], $tldRegistrar->name);
        }
    }

    public function testOnBeforeAdminCronRun()
    {
        $di          = new \Box_Di();
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('batchSyncExpirationDates')
            ->willReturn(true);
        $di['mod_service'] = $di->protect(function ($serviceName) use ($serviceMock) {
            return $serviceMock;
        });

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $this->service->onBeforeAdminCronRun($boxEventMock);

        $this->assertTrue($result);
    }

    public function testBatchSyncExpirationDates()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('syncExpirationDate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('syncExpirationDate')
            ->will($this->returnValue(null));


        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')
            ->setMethods(array('getParamValue', 'setParamValue'))->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue')
            ->will($this->returnValue(null));
        $systemServiceMock->expects($this->atLeastOnce())->method('setParamValue')
            ->will($this->returnValue(null));

        $domains = array(
            'domain1.com',
            'domain2.com',
            'domain3.com',
            'domain4.com',
        );
        $dbMock  = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue($domains));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($systemServiceMock) {
            return $systemServiceMock;
        });
        $di['db']          = $dbMock;
        $di['logger']      = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);


        $result = $serviceMock->batchSyncExpirationDates();

        $this->assertTrue($result);
    }

    public function testBatchSyncExpirationDatesReturnsFalse()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')
            ->setMethods(array('getParamValue', 'setParamValue'))->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue')
            ->will($this->returnValue(date('Y-m-d H:i:s')));
        $systemServiceMock->expects($this->never())->method('setParamValue')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('find')
            ->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use ($systemServiceMock) {
            return $systemServiceMock;
        });
        $di['db']          = $dbMock;
        $this->service->setDi($di);


        $result = $this->service->batchSyncExpirationDates();

        $this->assertFalse($result);
    }

    public function tldGetSearchQueryProvider()
    {
        return array(
            array(
                array(),
                'SELECT * FROM tld ORDER BY id ASC',
                array()
            ),
            array(
                array(
                    'hide_inactive' => true
                ),
                'SELECT * FROM tld WHERE active = 1 ORDER BY id ASC',
                array()
            ),
            array(
                array(
                    'allow_register' => true
                ),
                'SELECT * FROM tld WHERE allow_register = 1 ORDER BY id ASC',
                array()
            ),
            array(
                array(
                    'allow_transfer' => true
                ),
                'SELECT * FROM tld WHERE allow_transfer = 1 ORDER BY id ASC',
                array()
            ),
            array(
                array(
                    'hide_inactive'  => true,
                    'allow_register' => true,
                    'allow_transfer' => true
                ),
                'SELECT * FROM tld WHERE active = 1 AND allow_register = 1 AND allow_transfer = 1 ORDER BY id ASC',
                array()
            ),

        );
    }

    /**
     * @dataProvider tldGetSearchQueryProvider
     */
    public function testTldGetSearchQuery($data, $expectedQuery, $expectedBindings)
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);
        list($query, $bindings) = $this->service->tldGetSearchQuery($data);

        $this->assertEquals($query, $expectedQuery);

        $this->assertIsArray($bindings);
        $this->assertEquals($bindings, $expectedBindings);

    }

    public function testTldFindAllActive()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindAllActive();

        $this->assertIsArray($result);
    }

    public function testTldFindOneActiveById()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($tldModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindOneActiveById(rand(1, 100));

        $this->assertInstanceOf('Model_Tld', $result);
    }

    public function testTldGetPairs()
    {
        $returns = array(
            0 => '.com'
        );
        $dbMock  = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($returns));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldGetPairs();

        $this->assertIsArray($result);
        $this->assertEquals($result, $returns);
    }

    public function testTldAlreadyRegisteredExists()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($tldModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldAlreadyRegistered('.com');

        $this->assertTrue($result);
    }

    public function testTldAlreadyRegisteredDoesNotExist()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldAlreadyRegistered('.com');

        $this->assertFalse($result);
    }

    public function testTldRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $result = $this->service->tldRm($model);

        $this->assertTrue($result);
    }

    public function testTldToApiArray()
    {
        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \RedBeanPHP\OODBBean());
        $tldRegistrar->name = 'ResellerClub';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($tldRegistrar));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->tld                = '.com';
        $model->price_registration = rand(1, 100);
        $model->price_renew        = rand(1, 100);
        $model->price_transfer     = rand(1, 100);
        $model->active             = 1;
        $model->allow_register     = 1;
        $model->allow_transfer     = 1;
        $model->min_years          = 2;
        $model->tld_registrar_id   = rand(1, 100);


        $result = $this->service->tldToApiArray($model);
        $this->assertIsArray($result);

        $this->assertArrayHasKey('tld', $result);
        $this->assertArrayHasKey('price_registration', $result);
        $this->assertArrayHasKey('price_renew', $result);
        $this->assertArrayHasKey('price_transfer', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('allow_register', $result);
        $this->assertArrayHasKey('allow_transfer', $result);
        $this->assertArrayHasKey('min_years', $result);
        $this->assertArrayHasKey('registrar', $result);

        $registrar = $result['registrar'];
        $this->assertIsArray($registrar);
        $this->assertArrayHasKey('id', $registrar);
        $this->assertArrayHasKey('title', $registrar);

        $this->assertEquals($result['tld'], $model->tld);
        $this->assertEquals($result['price_registration'], $model->price_registration);
        $this->assertEquals($result['price_renew'], $model->price_renew);
        $this->assertEquals($result['price_transfer'], $model->price_transfer);
        $this->assertEquals($result['active'], $model->active);
        $this->assertEquals($result['allow_register'], $model->allow_register);
        $this->assertEquals($result['allow_transfer'], $model->allow_transfer);
        $this->assertEquals($result['min_years'], $model->min_years);

        $this->assertEquals($registrar['id'], $model->tld_registrar_id);
        $this->assertEquals($registrar['title'], $tldRegistrar->name);
    }


    public function testTldFindOneByTld()
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($tldModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindOneByTld('.com');

        $this->assertInstanceOf('Model_Tld', $result);
    }

    public function testRegistrarGetSearchQuery()
    {
        list($query, $bindings) = $this->service->registrarGetSearchQuery(array());

        $this->assertEquals('SELECT * FROM tld_registrar ORDER BY name ASC', $query);
        $this->assertIsArray($bindings);
        $this->assertEquals(array(), $bindings);
    }

    public function testRegistrarGetAvailable()
    {
        $registrars = array(
            'Resellerclub' => 'Reseller Club'
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($registrars));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->registrarGetAvailable();
        $this->assertIsArray($result);
    }

    public function testRegistrarGetPairs()
    {
        $registrars = array(
            1 => 'Resellerclub',
            2 => 'Email',
            3 => 'Custom'
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($registrars));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->registrarGetPairs();

        $this->assertIsArray($result);
        $this->assertEquals(count($result), 3);

    }

    public function testRegistrarGetActiveRegistrar()
    {
        $tldRegistrarModel = new \Model_TldRegistrar();
        $tldRegistrarModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($tldRegistrarModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->registrarGetActiveRegistrar();

        $this->assertInstanceOf('Model_TldRegistrar', $result);

    }

    public function testRegistrarGetConfiguration()
    {
        $config = array(
            'config_param' => 'config_value'
        );

        $toolsMock = $this->getMockBuilder('Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue($config));

        $di          = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->config = json_encode($config);

        $result = $this->service->registrarGetConfiguration($model);

        $this->assertIsArray($result);
        $this->assertEquals($result, $config);
    }

    public function testRegistrarGetRegistrarAdapterConfig()
    {
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->registrar = 'Custom';

        $result = $this->service->registrarGetRegistrarAdapterConfig($model);
        $this->assertIsArray($result);
    }

    public function testRegistrarGetRegistrarAdapterConfigClassNotExistsException()
    {
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->registrar = 'Non-Existing';

        $this->expectException(\Box_Exception::class);
        $result = $this->service->registrarGetRegistrarAdapterConfig($model);
        $this->assertIsArray($result);
    }

    public function testRegistrarGetRegistrarAdapterConfigRegistrarNotExistException()
    {
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->registrar = 'Non-Existing';

        $this->expectException(\Box_Exception::class);
        $this->service->registrarGetRegistrarAdapterConfig($model);
    }

    public function testRegistrarGetRegistrarAdapter()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('registrarGetConfiguration'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->will($this->returnValue(array()));
        $di           = new \Box_Di();
        $di['logger'] = $this->getMockBuilder(\Box_Log::class)->getMock();
        $serviceMock->setDi($di);
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->registrar = 'Custom';

        $result = $serviceMock->registrarGetRegistrarAdapter($model);

        $this->assertInstanceOf('Registrar_Adapter_' . $model->registrar, $result);
    }

    public function testRegistrarGetRegistrarAdapterNotFoundException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('registrarGetConfiguration'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->will($this->returnValue(array()));

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->registrar = 'Non-Existing';

        $this->expectException(\Box_Exception::class);
        $result = $serviceMock->registrarGetRegistrarAdapter($model);
        $this->assertInstanceOf('Registrar_Adapter_' . $model->registrar, $result);
    }

    public function testRegistrarRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array()));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id   = rand(1, 100);
        $model->name = 'ResellerClub';

        $result = $this->service->registrarRm($model);

        $this->assertTrue($result);
    }

    public function testRegistrarRmHasDomainsException()
    {
        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($serviceDomainModel)));
        $dbMock->expects($this->never())
            ->method('trash')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $this->expectException(\Box_Exception::class);
        $this->service->registrarRm($model);

    }

    public function testRegistrarToApiArray()
    {
        $config = array(
            'label' => 'Label',
            'form'  => rand(1, 100)
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')
            ->setMethods(array('registrarGetRegistrarAdapterConfig', 'registrarGetConfiguration'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapterConfig')
            ->will($this->returnValue($config));
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->will($this->returnValue(array('param1' => 'value1')));


        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id        = rand(1, 100);
        $model->name      = 'ResellerClub';
        $model->test_mode = true;

        $serviceMock->registrarToApiArray($model);
    }

    public function testTldCreate()
    {
        $data                       = array();
        $data['tld']                = '.com';
        $data['tld_registrar_id']   = rand(1, 100);
        $data['price_registration'] = rand(1, 10);
        $data['price_renew']        = rand(1, 10);
        $data['price_transfer']     = rand(1, 10);
        $data['min_years']          = rand(1, 5);
        $data['allow_register']     = 1;
        $data['allow_transfer']     = 1;
        $data['updated_at']         = date('Y-m-d H:i:s');
        $data['created_at']         = date('Y-m-d H:i:s');

        $randId = rand(1, 100);

        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($tldModel));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->tldCreate($data);

        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);

    }

    public function testTldUpdate()
    {
        $data                       = array();
        $data['tld']                = '.com';
        $data['tld_registrar_id']   = rand(1, 100);
        $data['price_registration'] = rand(1, 10);
        $data['price_renew']        = rand(1, 10);
        $data['price_transfer']     = rand(1, 10);
        $data['min_years']          = rand(1, 5);
        $data['allow_register']     = true;
        $data['allow_transfer']     = true;
        $data['active']             = true;
        $data['updated_at']         = date('Y-m-d H:i:s');
        $data['created_at']         = date('Y-m-d H:i:s');

        $randId = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->tld = '.com';

        $result = $this->service->tldUpdate($model, $data);

        $this->assertTrue($result);

    }

    public function testRegistrarCreate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $result = $this->service->registrarCreate('ResellerClub');

        $this->assertTrue($result);
    }

    public function testRegistrarCopy()
    {
        $newId  = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newId));

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->name      = 'ResellerClub';
        $model->registrar = 'ResellerClub';
        $model->test_mode = 1;


        $result = $this->service->registrarCopy($model);

        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testRegistrarUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'title'     => 'ResellerClub',
            'test_mode' => 1,
            'config'    => array(
                'param1' => 'value1'
            )
        );

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->registrar = 'Custom';

        $result = $this->service->registrarUpdate($model, $data);

        $this->assertTrue($result);
    }

    public function testUpdateDomain()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'ns1'           => 'ns1.example.com',
            'ns2'           => 'ns2.example.com',
            'ns3'           => 'ns3.example.com',
            'ns4'           => 'ns4.example.com',
            'period'        => rand(1, 10),
            'privacy'       => 1,
            'locked'        => 1,
            'transfer_code' => 'EPPCODE'
        );

        $model = new \Model_ServiceDomain();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $result = $this->service->updateDomain($model, $data);

        $this->assertTrue($result);
    }

}