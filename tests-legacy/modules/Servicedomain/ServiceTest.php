<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Servicedomain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?\FOSSBilling\ProductType\Domain\DomainHandler $service;

    public function setUp(): void
    {
        $this->service = new \FOSSBilling\ProductType\Domain\DomainHandler();
    }

    public function testDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public static function getCartProductTitleProvider(): array
    {
        return [
            [
                [
                    'action' => 'register',
                    'register_tld' => '.com',
                    'register_sld' => 'example',
                ],
                'Domain example.com registration',
            ],
            [
                [
                    'action' => 'transfer',
                    'transfer_tld' => '.com',
                    'transfer_sld' => 'example',
                ],
                'Domain example.com transfer',
            ],
            [
                [],
                'Example.com Registration',
            ],
        ];
    }

    #[DataProvider('getCartProductTitleProvider')]
    public function testGetCartProductTitle(array $data, string $expected): void
    {
        $product = new \Model_CartProduct();
        $product->loadBean(new \DummyBean());
        $product->title = 'Example.com Registration';

        $result = $this->service->getCartProductTitle($product, $data);

        $this->assertEquals($result, $expected);
    }

    public static function validateOrderDataExceptionsProvider(): array
    {
        return [
            [
                [
                    'action' => 'NonExistingAction',
                ],
            ],
        ];
    }

    #[DataProvider('validateOrderDataExceptionsProvider')]
    public function testValidateOrderDataExceptions(array $data): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->validateOrderData($data);
        $this->assertNull($result);
    }

    public static function validateOrderDateTransferExceptionsProvider(): array
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld = '.com';

        return [
            [
                [
                    'action' => 'transfer',
                    'transfer_sld' => 'example',
                    'transfer_tld' => '.com',
                ],
                [ // isSldValidArr
                    'called' => 'atLeastOnce',
                    'returns' => true,
                ],
                [ // tldFindOneByTldArr
                    'called' => 'atLeastOnce',
                    'returns' => null,
                ],
                [ // canBeTransferred
                    'called' => 'never',
                    'returns' => true,
                ],
            ],
            [
                [
                    'action' => 'transfer',
                    'transfer_sld' => 'example',
                    'transfer_tld' => '.com',
                ],
                [ // isSldValidArr
                    'called' => 'atLeastOnce',
                    'returns' => true,
                ],
                [ // tldFindOneByTldArr
                    'called' => 'atLeastOnce',
                    'returns' => $tldModel,
                ],
                [ // canBeTransferred
                    'called' => 'atLeastOnce',
                    'returns' => false,
                ],
            ],
        ];
    }

    #[DataProvider('validateOrderDateTransferExceptionsProvider')]
    public function testValidateOrderDateTransferExceptions(array $data, array $isSldValidArr, array $tldFindOneByTldArr, array $canBeTransferred): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->{$isSldValidArr['called']}())->method('isSldValid')
            ->willReturn($isSldValidArr['returns']);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->{$tldFindOneByTldArr['called']}())->method('tldFindOneByTld')
            ->willReturn($tldFindOneByTldArr['returns']);
        $serviceMock->expects($this->{$canBeTransferred['called']}())->method('canBeTransferred')
            ->willReturn($canBeTransferred['returns']);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $serviceMock->validateOrderData($data);
        $this->assertNull($result);
    }

    public static function validateOrderDateRegisterExceptionsProvider(): array
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld = '.com';
        $tldModel->min_years = 2;

        return [
            [
                [
                    'action' => 'register',
                    'register_sld' => 'example',
                    'register_years' => 2,
                    'register_tld' => '.com',
                ],
                [ // isSldValidArr
                    'called' => 'atLeastOnce',
                    'returns' => true,
                ],
                [ // tldFindOneByTldArr
                    'called' => 'atLeastOnce',
                    'returns' => null,
                ],
                [ // isDomainAvailable
                    'called' => 'never',
                    'returns' => true,
                ],
            ],
            [
                [
                    'action' => 'register',
                    'register_sld' => 'example',
                    'register_years' => $tldModel->min_years - 1, // less years than required
                    'register_tld' => '.com',
                ],
                [ // isSldValidArr
                    'called' => 'atLeastOnce',
                    'returns' => true,
                ],
                [ // tldFindOneByTldArr
                    'called' => 'atLeastOnce',
                    'returns' => $tldModel,
                ],
                [ // isDomainAvailable
                    'called' => 'never',
                    'returns' => true,
                ],
            ],
            [
                [
                    'action' => 'register',
                    'register_sld' => 'example',
                    'register_tld' => '.com',
                    'register_years' => 2,
                ],
                [ // isSldValidArr
                    'called' => 'atLeastOnce',
                    'returns' => true,
                ],
                [ // tldFindOneByTldArr
                    'called' => 'atLeastOnce',
                    'returns' => $tldModel,
                ],
                [ // isDomainAvailable
                    'called' => 'atLeastOnce',
                    'returns' => false,
                ],
            ],
        ];
    }

    #[DataProvider('validateOrderDateRegisterExceptionsProvider')]
    public function testValidateOrderDateRegisterExceptions(array $data, array $isSldValidArr, array $tldFindOneByTldArr, array $isDomainAvailable): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->{$isSldValidArr['called']}())->method('isSldValid')
            ->willReturn($isSldValidArr['returns']);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'isDomainAvailable'])->getMock();
        $serviceMock->expects($this->{$tldFindOneByTldArr['called']}())->method('tldFindOneByTld')
            ->willReturn($tldFindOneByTldArr['returns']);
        $serviceMock->expects($this->{$isDomainAvailable['called']}())->method('isDomainAvailable')
            ->willReturn($isDomainAvailable['returns']);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $serviceMock->validateOrderData($data);
    }

    public function testActionCreate(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld_registrar_id = 1;

        $data = [
            'action' => 'register',
            'register_sld' => 'example',
            'register_tld' => '.com',
            'register_years' => 2,
        ];

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getConfig'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getConfig')
            ->willReturn($data);

        $nameservers = [
            'nameserver_1' => 'ns1.example.com',
            'nameserver_2' => 'ns2.example.com',
            'nameserver_3' => 'ns3.example.com',
            'nameserver_4' => 'ns4.example.com',
        ];
        $systemServiceMock = $this->getMockBuilder(\Box\Mod\System\Service::class)
            ->onlyMethods(['getNameservers'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getNameservers')
            ->willReturn($nameservers);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'validateOrderData'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('tldFindOneByTld')
            ->willReturn($tldModel);
        $serviceMock->expects($this->atLeastOnce())->method('validateOrderData')
        ;

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->first_name = 'first_name';
        $client->last_name = 'last_name';
        $client->email = 'email';
        $client->company = 'company';
        $client->address_1 = 'address_1';
        $client->address_2 = 'address_2';
        $client->country = 'country';
        $client->city = 'city';
        $client->state = 'state';
        $client->postcode = 'postcode';
        $client->phone_cc = 'phone_cc';
        $client->phone = 'phone';

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($client);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($serviceDomainModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
            if ($name == 'order') {
                return $orderServiceMock;
            }

            return $systemServiceMock;
        });
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;

        $result = $serviceMock->create($order);
        $this->assertInstanceOf('Model_ServiceDomain', $result);
    }

    public function testActionCreateNameserversException(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld_registrar_id = 1;

        $data = [
            'action' => 'register',
            'register_sld' => 'example',
            'register_tld' => '.com',
            'register_years' => 2,
        ];

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getConfig'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getConfig')
            ->willReturn($data);

        $systemServiceMock = $this->getMockBuilder(\Box\Mod\System\Service::class)
            ->onlyMethods(['getNameservers'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getNameservers')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['validateOrderData'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('validateOrderData')
        ;

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
            if ($name == 'order') {
                return $orderServiceMock;
            }

            return $systemServiceMock;
        });
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $this->expectException(\FOSSBilling\Exception::class);
        $serviceMock->create($order);
    }

    public static function actionActivateProvider(): array
    {
        return [
            [
                'register',
                'atLeastOnce',
                'never',
            ],
            [
                'transfer',
                'never',
                'atLeastOnce',
            ],
        ];
    }

    #[DataProvider('actionActivateProvider')]
    public function testActionActivate(string $action, string $registerDomainCalled, string $transferDomainCalled): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld_registrar_id = 1;

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \DummyBean());
        $domainModel->tld_registrar_id = 1;
        $domainModel->action = $action;

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn($domainModel);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->$registerDomainCalled())->method('registerDomain')
        ;
        $registrarAdapterMock->expects($this->$transferDomainCalled())->method('transferDomain')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD', 'syncWhois'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);
        $serviceMock->expects($this->atLeastOnce())->method('syncWhois')
            ->willReturn(null);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $result = $serviceMock->activate($order);
        $this->assertInstanceOf('Model_ServiceDomain', $result);
    }

    public function testActionActivateServiceNotFoundException(): void
    {
        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->activate($order);
    }

    public function testActionRenew(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld_registrar_id = 1;

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \DummyBean());
        $domainModel->tld_registrar_id = 1;
        $domainModel->action = 'register';

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn($domainModel);

        $registrarDomainMock = $this->getMockBuilder('Registrar_Domain')->disableOriginalConstructor()
            ->getMock();
        $registrarDomainMock->expects($this->atLeastOnce())->method('getContactRegistrar')
            ->willReturn(new \Registrar_Domain_Contact());
        $registrarDomainMock->expects($this->atLeastOnce())->method('getLocked')
            ->willReturn(true);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('renewDomain')
        ;
        $registrarAdapterMock->expects($this->atLeastOnce())->method('getDomainDetails')
            ->willReturn($registrarDomainMock);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $result = $serviceMock->renew($order);

        $this->assertTrue($result);
    }

    public function testActionRenewServiceNotFoundException(): void
    {
        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->client_id = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->renew($order);

        $this->assertTrue($result);
    }

    public function testActionSuspend(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $result = $this->service->suspend($order);
        $this->assertTrue($result);
    }

    public function testActionUnsuspend(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $result = $this->service->unsuspend($order);
        $this->assertTrue($result);
    }

    public function testActionCancel(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld_registrar_id = 1;

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \DummyBean());
        $domainModel->tld_registrar_id = 1;
        $domainModel->action = 'register';

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn($domainModel);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('deleteDomain')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $result = $serviceMock->cancel($order);

        $this->assertTrue($result);
    }

    public function testActionCancelServiceNotFoundException(): void
    {
        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->client_id = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->cancel($order);

        $this->assertTrue($result);
    }

    public function testActionUncancel(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['activate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('activate')
            ->willReturn(null);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $result = $serviceMock->uncancel($order);

        $this->assertTrue($result);
    }

    public function testActionDelete(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->tld_registrar_id = 1;

        $domainModel = new \Model_ServiceDomain();
        $domainModel->loadBean(new \DummyBean());
        $domainModel->tld_registrar_id = 1;
        $domainModel->action = 'register';

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn($domainModel);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('deleteDomain')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $result = $serviceMock->delete($order);

        $this->assertNull($result);
    }

    public function testUpdateNameservers(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('modifyNs')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $data = [
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
            'ns3' => 'ns3.example.com',
            'ns4' => 'ns4.example.com',
        ];

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->updateNameservers($serviceDomainModel, $data);

        $this->assertTrue($result);
    }

    public static function updateNameserversExceptionProvider(): array
    {
        return [
            [
                ['ns2' => 'ns2.example.com'], // ns1 is missing
            ],
            [
                ['ns1' => 'ns1.example.com'], // ns2 is missing
            ],
        ];
    }

    #[DataProvider('updateNameserversExceptionProvider')]
    public function testUpdateNameserversException(array $data): void
    {
        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->updateNameservers($serviceDomainModel, $data);
    }

    public function testUpdateContacts(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('modifyContact')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $data = [
            'contact' => [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email' => 'email',
                'company' => 'company',
                'address1' => 'address1',
                'address2' => 'address2',
                'country' => 'country',
                'city' => 'city',
                'state' => 'state',
                'postcode' => 'postcode',
                'phone_cc' => 'phone_cc',
                'phone' => 'phone',
            ],
        ];
        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->updateContacts($serviceDomainModel, $data);

        $this->assertTrue($result);
    }

    public function testGetTransferCode(): void
    {
        $epp = 'EPPCODE';

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('getEpp')
            ->willReturn($epp);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->getTransferCode($serviceDomainModel);

        $this->assertIsString($epp);
        $this->assertEquals($result, $epp);
    }

    public function testLock(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('lock')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->lock($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testUnlock(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('unlock')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->unlock($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->enablePrivacyProtection($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
        ;

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $result = $serviceMock->disablePrivacyProtection($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testCanBeTransferred(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('isDomaincanBeTransferred')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['registrarGetRegistrarAdapter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapter')
            ->willReturn($registrarAdapterMock);

        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \DummyBean());
        $tldRegistrar->tld_registrar_id = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($tldRegistrar);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->allow_transfer = true;
        $tld->tld = '.com';
        $tld->tld_registrar_id = 1;

        $result = $serviceMock->canBeTransferred($tld, 'example');

        $this->assertTrue($result);
    }

    public function testCanBeTransferredEmptySldException(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->canBeTransferred(new \Model_Tld(), '');
    }

    public function testCanBeTransferredNotAllowedException(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $tldModel->allow_transfer = false;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->canBeTransferred($tldModel, 'example');
    }

    public function testIsDomainAvailable(): void
    {
        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('isDomainAvailable')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['registrarGetRegistrarAdapter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapter')
            ->willReturn($registrarAdapterMock);

        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \DummyBean());
        $tldRegistrar->tld_registrar_id = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($tldRegistrar);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $tld = new \Model_Tld();
        $tld->loadBean(new \DummyBean());
        $tld->allow_register = true;
        $tld->tld = '.com';
        $tld->tld_registrar_id = 1;

        $result = $serviceMock->isDomainAvailable($tld, 'example');

        $this->assertTrue($result);
    }

    public function testIsDomainAvailableEmptySldException(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->isDomainAvailable($tldModel, '');
    }

    public function testIsDomainAvailableSldNotValidException(): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(false);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->isDomainAvailable($tldModel, 'example');
    }

    public function testIsDomainAvailableSldNotAllowedToRegisterException(): void
    {
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \DummyBean());
        $model->allow_register = false;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->isDomainAvailable($model, 'example');
    }

    public function testSyncExpirationDate(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());
        $result = $this->service->syncExpirationDate($model);

        $this->assertNull($result);
    }

    public static function toApiArrayProvider(): array
    {
        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());

        return [
            [
                $model,
                'atLeastOnce',
            ],
            [
                null,
                'never',
            ],
        ];
    }

    #[DataProvider('toApiArrayProvider')]
    public function testToApiArray(?\Model_Admin $identity, string $dbLoadCalled): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $model->sld = 'sld';
        $model->tld = 'tld';
        $model->ns1 = 'ns1.example.com';
        $model->ns2 = 'ns2.example.com';
        $model->ns3 = 'ns3.example.com';
        $model->ns4 = 'ns4.example.com';
        $model->period = 'period';
        $model->privacy = 'privacy';
        $model->locked = 'locked';
        $model->registered_at = date('Y-m-d H:i:s');
        $model->expires_at = date('Y-m-d H:i:s');

        $model->contact_first_name = 'first_name';
        $model->contact_last_name = 'last_name';
        $model->contact_email = 'email';
        $model->contact_company = 'company';
        $model->contact_address1 = 'address1';
        $model->contact_address2 = 'address2';
        $model->contact_country = 'country';
        $model->contact_city = 'city';
        $model->contact_state = 'state';
        $model->contact_postcode = 'postcode';
        $model->contact_phone_cc = 'phone_cc';
        $model->contact_phone = 'phone';
        $model->transfer_code = 'EPPCODE';
        $model->tld_registrar_id = 1;

        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \DummyBean());
        $tldRegistrar->name = 'ResellerClub';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->$dbLoadCalled())
            ->method('load')
            ->willReturn($tldRegistrar);

        $di = $this->getDi();
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

    public function testOnBeforeAdminCronRun(): void
    {
        $di = $this->getDi();
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('batchSyncExpirationDates')
            ->willReturn(true);
        $di['mod_service'] = $di->protect(fn ($serviceName): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $boxEventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $boxEventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $this->service->onBeforeAdminCronRun($boxEventMock);

        $this->assertTrue($result);
    }

    public function testBatchSyncExpirationDates(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['syncExpirationDate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('syncExpirationDate')
        ;

        $systemServiceMock = $this->getMockBuilder(\Box\Mod\System\Service::class)
            ->onlyMethods(['getParamValue', 'setParamValue'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue')
            ->willReturn(null);
        $systemServiceMock->expects($this->atLeastOnce())->method('setParamValue')
        ;

        $domains = [
            'domain1.com',
            'domain2.com',
            'domain3.com',
            'domain4.com',
        ];
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn($domains);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
        $di['db'] = $dbMock;
        $di['logger'] = $di['logger'] = $this->createMock('Box_Log');
        $serviceMock->setDi($di);

        $result = $serviceMock->batchSyncExpirationDates();

        $this->assertTrue($result);
    }

    public function testBatchSyncExpirationDatesReturnsFalse(): void
    {
        $systemServiceMock = $this->getMockBuilder(\Box\Mod\System\Service::class)
            ->onlyMethods(['getParamValue', 'setParamValue'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue')
            ->willReturn(date('Y-m-d H:i:s'));
        $systemServiceMock->expects($this->never())->method('setParamValue')
        ;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('find')
            ->willReturn([]);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->batchSyncExpirationDates();

        $this->assertFalse($result);
    }

    public static function tldGetSearchQueryProvider(): array
    {
        return [
            [
                [],
                'SELECT * FROM tld ORDER BY id ASC',
                [],
            ],
            [
                [
                    'hide_inactive' => true,
                ],
                'SELECT * FROM tld WHERE active = 1 ORDER BY id ASC',
                [],
            ],
            [
                [
                    'allow_register' => true,
                ],
                'SELECT * FROM tld WHERE allow_register = 1 ORDER BY id ASC',
                [],
            ],
            [
                [
                    'allow_transfer' => true,
                ],
                'SELECT * FROM tld WHERE allow_transfer = 1 ORDER BY id ASC',
                [],
            ],
            [
                [
                    'hide_inactive' => true,
                    'allow_register' => true,
                    'allow_transfer' => true,
                ],
                'SELECT * FROM tld WHERE active = 1 AND allow_register = 1 AND allow_transfer = 1 ORDER BY id ASC',
                [],
            ],
        ];
    }

    #[DataProvider('tldGetSearchQueryProvider')]
    public function testTldGetSearchQuery(array $data, string $expectedQuery, array $expectedBindings): void
    {
        $di = $this->getDi();

        $this->service->setDi($di);
        [$query, $bindings] = $this->service->tldGetSearchQuery($data);

        $this->assertEquals($query, $expectedQuery);

        $this->assertIsArray($bindings);
        $this->assertEquals($bindings, $expectedBindings);
    }

    public function testTldFindAllActive(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindAllActive();

        $this->assertIsArray($result);
    }

    public function testTldFindOneActiveById(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($tldModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindOneActiveById(1);

        $this->assertInstanceOf('Model_Tld', $result);
    }

    public function testTldGetPairs(): void
    {
        $returns = [
            0 => '.com',
        ];
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($returns);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldGetPairs();

        $this->assertIsArray($result);
        $this->assertEquals($result, $returns);
    }

    public function testTldAlreadyRegisteredExists(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($tldModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldAlreadyRegistered('.com');

        $this->assertTrue($result);
    }

    public function testTldAlreadyRegisteredDoesNotExist(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldAlreadyRegistered('.com');

        $this->assertFalse($result);
    }

    public function testTldRm(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $result = $this->service->tldRm($model);

        $this->assertTrue($result);
    }

    public function testTldToApiArray(): void
    {
        $tldRegistrar = new \Model_TldRegistrar();
        $tldRegistrar->loadBean(new \DummyBean());
        $tldRegistrar->name = 'ResellerClub';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($tldRegistrar);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \DummyBean());
        $model->tld = '.com';
        $model->price_registration = 1;
        $model->price_renew = 1;
        $model->price_transfer = 1;
        $model->active = 1;
        $model->allow_register = 1;
        $model->allow_transfer = 1;
        $model->min_years = 2;
        $model->tld_registrar_id = 1;

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

    public function testTldFindOneByTld(): void
    {
        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($tldModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindOneByTld('.com');

        $this->assertInstanceOf('Model_Tld', $result);
    }

    public function testRegistrarGetSearchQuery(): void
    {
        [$query, $bindings] = $this->service->registrarGetSearchQuery([]);

        $this->assertEquals('SELECT * FROM tld_registrar ORDER BY name ASC', $query);
        $this->assertIsArray($bindings);
        $this->assertSame([], $bindings);
    }

    public function testRegistrarGetAvailable(): void
    {
        $registrars = [
            'Resellerclub' => 'Reseller Club',
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($registrars);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->registrarGetAvailable();
        $this->assertIsArray($result);
    }

    public function testRegistrarGetPairs(): void
    {
        $registrars = [
            1 => 'Resellerclub',
            2 => 'Email',
            3 => 'Custom',
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($registrars);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->registrarGetPairs();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testRegistrarGetActiveRegistrar(): void
    {
        $tldRegistrarModel = new \Model_TldRegistrar();
        $tldRegistrarModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($tldRegistrarModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->registrarGetActiveRegistrar();

        $this->assertInstanceOf('Model_TldRegistrar', $result);
    }

    public function testRegistrarGetConfiguration(): void
    {
        $config = [
            'config_param' => 'config_value',
        ];

        $di = $this->getDi();
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->config = json_encode($config);

        $result = $this->service->registrarGetConfiguration($model);

        $this->assertIsArray($result);
        $this->assertEquals($result, $config);
    }

    public function testRegistrarGetRegistrarAdapterConfig(): void
    {
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->registrar = 'Custom';

        $result = $this->service->registrarGetRegistrarAdapterConfig($model);
        $this->assertIsArray($result);
    }

    public function testRegistrarGetRegistrarAdapterConfigClassNotExistsException(): void
    {
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->registrar = 'Non-Existing';

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->registrarGetRegistrarAdapterConfig($model);
        $this->assertIsArray($result);
    }

    public function testRegistrarGetRegistrarAdapterConfigRegistrarNotExistException(): void
    {
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->registrar = 'Non-Existing';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->registrarGetRegistrarAdapterConfig($model);
    }

    public function testRegistrarGetRegistrarAdapter(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['registrarGetConfiguration'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->willReturn([]);
        $di = $this->getDi();
        $di['logger'] = $this->createMock(\Box_Log::class);
        $serviceMock->setDi($di);
        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->registrar = 'Custom';

        $result = $serviceMock->registrarGetRegistrarAdapter($model);

        $this->assertInstanceOf('Registrar_Adapter_' . $model->registrar, $result);
    }

    public function testRegistrarGetRegistrarAdapterNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['registrarGetConfiguration'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->willReturn([]);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->registrar = 'Non-Existing';

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $serviceMock->registrarGetRegistrarAdapter($model);
        $this->assertInstanceOf('Registrar_Adapter_' . $model->registrar, $result);
    }

    public function testRegistrarRm(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->name = 'ResellerClub';

        $result = $this->service->registrarRm($model);

        $this->assertTrue($result);
    }

    public function testRegistrarRmHasDomainsException(): void
    {
        $serviceDomainModel = new \Model_ServiceDomain();
        $serviceDomainModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$serviceDomainModel]);
        $dbMock->expects($this->never())
            ->method('trash')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->registrarRm($model);
    }

    public function testRegistrarToApiArray(): void
    {
        $config = [
            'label' => 'Label',
            'form' => 1,
        ];

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['registrarGetRegistrarAdapterConfig', 'registrarGetConfiguration'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapterConfig')
            ->willReturn($config);
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->willReturn(['param1' => 'value1']);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->name = 'ResellerClub';
        $model->test_mode = true;

        $serviceMock->registrarToApiArray($model);
    }

    public function testTldCreate(): void
    {
        $data = [];
        $data['tld'] = '.com';
        $data['tld_registrar_id'] = 1;
        $data['price_registration'] = 1;
        $data['price_renew'] = 1;
        $data['price_transfer'] = 1;
        $data['min_years'] = random_int(1, 5);
        $data['allow_register'] = 1;
        $data['allow_transfer'] = 1;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['created_at'] = date('Y-m-d H:i:s');

        $randId = 1;

        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($tldModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->tldCreate($data);

        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTldUpdate(): void
    {
        $data = [];
        $data['tld'] = '.com';
        $data['tld_registrar_id'] = 1;
        $data['price_registration'] = 1;
        $data['price_renew'] = 1;
        $data['price_transfer'] = 1;
        $data['min_years'] = random_int(1, 5);
        $data['allow_register'] = true;
        $data['allow_transfer'] = true;
        $data['active'] = true;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['created_at'] = date('Y-m-d H:i:s');

        $randId = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');

        $this->service->setDi($di);

        $model = new \Model_Tld();
        $model->loadBean(new \DummyBean());
        $model->tld = '.com';

        $result = $this->service->tldUpdate($model, $data);

        $this->assertTrue($result);
    }

    public function testRegistrarCreate(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $result = $this->service->registrarCreate('ResellerClub');

        $this->assertTrue($result);
    }

    public function testRegistrarCopy(): void
    {
        $newId = 1;
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->name = 'ResellerClub';
        $model->registrar = 'ResellerClub';
        $model->test_mode = 1;

        $result = $this->service->registrarCopy($model);

        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testRegistrarUpdate(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');

        $this->service->setDi($di);

        $data = [
            'title' => 'ResellerClub',
            'test_mode' => 1,
            'config' => [
                'param1' => 'value1',
            ],
        ];

        $model = new \Model_TldRegistrar();
        $model->loadBean(new \DummyBean());
        $model->registrar = 'Custom';

        $result = $this->service->registrarUpdate($model, $data);

        $this->assertTrue($result);
    }

    public function testUpdateDomain(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');

        $this->service->setDi($di);

        $data = [
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
            'ns3' => 'ns3.example.com',
            'ns4' => 'ns4.example.com',
            'period' => 1,
            'privacy' => 1,
            'locked' => 1,
            'transfer_code' => 'EPPCODE',
        ];

        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());
        $model->id = 1;

        $result = $this->service->updateDomain($model, $data);

        $this->assertTrue($result);
    }
}
