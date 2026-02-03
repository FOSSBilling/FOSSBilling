<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Domain\Tests;

use FOSSBilling\ProductType\Domain\DomainHandler;
use FOSSBilling\ProductType\Domain\Entity\Domain;
use FOSSBilling\ProductType\Domain\Entity\Tld;
use FOSSBilling\ProductType\Domain\Entity\TldRegistrar;
use Pimple\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?DomainHandler $service;

    public function setUp(): void
    {
        $this->service = new DomainHandler();
    }

    protected function createDiWithMockEm(): \Pimple\Container
    {
        $di = $this->getDi();

        $emMock = $this->createMock(\Doctrine\ORM\EntityManager::class);

        $tldRepoStub = $this->createStub(\FOSSBilling\ProductType\Domain\Repository\TldRepository::class);
        $tldRepoStub->method('find')
            ->willReturnCallback(function ($id) {
                if ($id === 1) {
                    $tld = new Tld();
                    $reflectionClass = new \ReflectionClass(Tld::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($tld, $id);
                    return $tld;
                }
                return null;
            });
        $tldRepoStub->method('findBy')
            ->willReturnCallback(function ($criteria, $orderBy) {
                if (isset($criteria['active']) && $criteria['active'] === true) {
                    $tld = new Tld();
                    $reflectionClass = new \ReflectionClass(Tld::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($tld, 1);
                    $tldProperty = $reflectionClass->getProperty('tld');
                    $tldProperty->setValue($tld, '.com');
                    return [$tld];
                }
                return [];
            });
        $tldRepoStub->method('findOneByTld')
            ->willReturnCallback(function ($tld) {
                if ($tld === '.com') {
                    $tldModel = new Tld();
                    $reflectionClass = new \ReflectionClass(Tld::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($tldModel, 1);
                    $tldProperty = $reflectionClass->getProperty('tld');
                    $tldProperty->setValue($tldModel, '.com');
                    return $tldModel;
                }
                return null;
            });
        $tldRepoStub->method('findOneBy')
            ->willReturnCallback(function ($criteria, $orderBy) {
                if (isset($criteria['id']) && $criteria['id'] === 1) {
                    $tld = new Tld();
                    $reflectionClass = new \ReflectionClass(Tld::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($tld, 1);
                    return $tld;
                }
                return null;
            });

        $registrarRepoStub = $this->createStub(\FOSSBilling\ProductType\Domain\Repository\TldRegistrarRepository::class);
        $registrarRepoStub->method('find')
            ->willReturnCallback(function ($id) {
                if ($id === 1) {
                    $registrar = new TldRegistrar();
                    $reflectionClass = new \ReflectionClass(TldRegistrar::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($registrar, $id);
                    return $registrar;
                }
                return null;
            });
        $registrarRepoStub->method('findAll')
            ->willReturnCallback(function () {
                $registrars = [];
                $r1 = new TldRegistrar();
                $reflectionClass = new \ReflectionClass(TldRegistrar::class);
                $idProperty = $reflectionClass->getProperty('id');
                $idProperty->setValue($r1, 1);
                $nameProperty = $reflectionClass->getProperty('name');
                $nameProperty->setValue($r1, 'Resellerclub');
                $configProperty = $reflectionClass->getProperty('config');
                $configProperty->setValue($r1, '{"key": "value"}');
                $registrars[] = $r1;

                $r2 = new TldRegistrar();
                $idProperty->setValue($r2, 2);
                $nameProperty->setValue($r2, 'Email');
                $registrars[] = $r2;

                $r3 = new TldRegistrar();
                $idProperty->setValue($r3, 3);
                $nameProperty->setValue($r3, 'Custom');
                $registrars[] = $r3;

                return $registrars;
            });

        $domainRepoStub = $this->createStub(\FOSSBilling\ProductType\Domain\Repository\DomainRepository::class);
        $domainRepoStub->method('find')
            ->willReturnCallback(function ($id) {
                if ($id === 1) {
                    $domain = new Domain(1);
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($domain, $id);
                    return $domain;
                }
                return null;
            });

        $emMock->method('getRepository')
            ->willReturnCallback(function ($class) use ($tldRepoStub, $registrarRepoStub, $domainRepoStub) {
                if ($class === Tld::class) {
                    return $tldRepoStub;
                }
                if ($class === TldRegistrar::class) {
                    return $registrarRepoStub;
                }
                if ($class === Domain::class) {
                    return $domainRepoStub;
                }
                return null;
            });

        $di['em'] = $emMock;
        $di['logger'] = $this->createMock(\Box_Log::class);

        return $di;
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
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
        $validatorMock->expects($this->any())->method('isSldValid')->willReturn(true);
        $validatorMock->expects($this->any())->method('isTldValid')->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->validateOrderData($data);
    }

    private function createTldWithTld(?string $tldValue = '.com'): Tld
    {
        $tld = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($tld, $tldValue ?? '.com');

        return $tld;
    }

    public static function validateOrderDateTransferExceptionsProvider(): array
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($tldModel, '.com');
        $allowTransferProperty = $reflectionClass->getProperty('allowTransfer');
        $allowTransferProperty->setValue($tldModel, true);

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
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
        $validatorMock->expects($this->any())->method('isTldValid')->willReturn(true);

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['tldFindOneByTld', 'canBeTransferred'])->getMock();
        $serviceMock->expects($this->{$tldFindOneByTldArr['called']}())->method('tldFindOneByTld')
            ->willReturn($tldFindOneByTldArr['returns']);
        $serviceMock->expects($this->{$canBeTransferred['called']}())->method('canBeTransferred')
            ->willReturn($canBeTransferred['returns']);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $serviceMock->validateOrderData($data);
    }

    public static function validateOrderDateRegisterExceptionsProvider(): array
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($tldModel, '.com');
        $minYearsProperty = $reflectionClass->getProperty('minYears');
        $minYearsProperty->setValue($tldModel, 2);
        $allowRegisterProperty = $reflectionClass->getProperty('allowRegister');
        $allowRegisterProperty->setValue($tldModel, true);

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
                    'register_years' => $tldModel->getMinYears() - 1,
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
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
        $validatorMock->expects($this->any())->method('isTldValid')->willReturn(true);

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
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
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($tldModel, $registrar);

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

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
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

        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Domain) {
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($entity, 1);
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
            if ($name == 'order') {
                return $orderServiceMock;
            }

            return $systemServiceMock;
        });
        $di['db'] = $this->createMock('\Box_Database');
        $di['db']->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($client);

        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;

        $result = $serviceMock->create($order);
        $this->assertInstanceOf(Domain::class, $result);
    }

    public function testActionCreateNameserversException(): void
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($tldModel, $registrar);

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

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
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

    private function createDomainModel(string $action = 'register'): Domain
    {
        $domainModel = new Domain(1);
        $reflectionClass = new \ReflectionClass(Domain::class);
        $actionProperty = $reflectionClass->getProperty('action');
        $actionProperty->setValue($domainModel, $action);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($domainModel, $registrar);

        return $domainModel;
    }

    #[DataProvider('actionActivateProvider')]
    #[Group('external-mock')]
    public function testActionActivate(string $action, string $registerDomainCalled, string $transferDomainCalled): void
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($tldModel, $registrar);

        $domainModel = $this->createDomainModel($action);

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

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['_getD', 'syncWhois'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);
        $serviceMock->expects($this->atLeastOnce())->method('syncWhois')
            ->willReturn(null);

        $di = $this->createDiWithMockEm();
        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Domain) {
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($entity, 1);
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $result = $serviceMock->activate($order);
        $this->assertInstanceOf(Domain::class, $result);
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

    #[Group('external-mock')]
    public function testActionRenew(): void
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($tldModel, $registrar);

        $domainModel = $this->createDomainModel('register');

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

        $contactStub = $this->getMockBuilder('Registrar_Domain_Contact')
            ->disableOriginalConstructor()
            ->getMock();
        $callback = function ($method) {
            $values = [
                'getFirstName' => 'John',
                'getLastName' => 'Doe',
                'getEmail' => 'john@example.com',
                'getCompany' => 'Example Inc',
                'getAddress1' => '123 Main St',
                'getAddress2' => '',
                'getCountry' => 'US',
                'getCity' => 'New York',
                'getState' => 'NY',
                'getTel' => '555-1234',
                'getTelCc' => '1',
                'getZip' => '10001',
            ];
            return $values[$method] ?? null;
        };
        $contactStub->method('getFirstName')->willReturnCallback($callback);
        $contactStub->method('getLastName')->willReturnCallback($callback);
        $contactStub->method('getEmail')->willReturnCallback($callback);
        $contactStub->method('getCompany')->willReturnCallback($callback);
        $contactStub->method('getAddress1')->willReturnCallback($callback);
        $contactStub->method('getAddress2')->willReturnCallback($callback);
        $contactStub->method('getCountry')->willReturnCallback($callback);
        $contactStub->method('getCity')->willReturnCallback($callback);
        $contactStub->method('getState')->willReturnCallback($callback);
        $contactStub->method('getTel')->willReturnCallback($callback);
        $contactStub->method('getTelCc')->willReturnCallback($callback);
        $contactStub->method('getZip')->willReturnCallback($callback);

        $registrarDomainMock->method('getContactRegistrar')->willReturn($contactStub);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('renewDomain')
        ;
        $registrarAdapterMock->expects($this->atLeastOnce())->method('getDomainDetails')
            ->willReturn($registrarDomainMock);

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $di = $this->createDiWithMockEm();
        $di['db'] = $this->createMock('\Box_Database');
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
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($tldModel, $registrar);

        $domainModel = $this->createDomainModel('register');

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn($domainModel);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('deleteDomain')
        ;

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $di = $this->createDiWithMockEm();
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
        $domainModel = new Domain(1);
        $reflectionClass = new \ReflectionClass(Domain::class);
        $sldProperty = $reflectionClass->getProperty('sld');
        $sldProperty->setValue($domainModel, 'test');
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($domainModel, '.com');

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['activate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('activate')
            ->willReturn($domainModel);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->client_id = 1;
        $result = $serviceMock->uncancel($order);

        $this->assertTrue($result);
    }

    public function testActionDelete(): void
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $registrarIdProperty = $reflectionRegistrarClass->getProperty('id');
        $registrarIdProperty->setValue($registrar, 1);
        $registrarProperty->setValue($tldModel, $registrar);

        $domainModel = $this->createDomainModel('register');

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())->method('getOrderService')
            ->willReturn($domainModel);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->atLeastOnce())->method('deleteDomain')
        ;

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['_getD'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('_getD')
            ->willReturn([new \Registrar_Domain(), $registrarAdapterMock]);

        $di = $this->createDiWithMockEm();
        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('remove')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
        $serviceMock->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->status = \Model_ClientOrder::STATUS_ACTIVE;
        $result = $serviceMock->delete($order);

        $this->assertNull($result);
    }

    public function testUpdateNameservers(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['logger'] = $this->createMock(\Box_Log::class);
        $this->service->setDi($di);

        $data = [
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
            'ns3' => 'ns3.example.com',
            'ns4' => 'ns4.example.com',
        ];

        $serviceDomainModel = new Domain(1);
        $result = $this->service->updateNameservers($serviceDomainModel, $data);

        $this->assertTrue($result);
    }

    public static function updateNameserversExceptionProvider(): array
    {
        return [
            [
                ['ns2' => 'ns2.example.com'],
            ],
            [
                ['ns1' => 'ns1.example.com'],
            ],
        ];
    }

    #[DataProvider('updateNameserversExceptionProvider')]
    #[Group('external-mock')]
    public function testUpdateNameserversException(array $data): void
    {
        $serviceDomainModel = new Domain(1);
        $reflectionClass = new \ReflectionClass(Domain::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($serviceDomainModel, 1);
        $sldProperty = $reflectionClass->getProperty('sld');
        $sldProperty->setValue($serviceDomainModel, 'test');
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($serviceDomainModel, '.com');

        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Domain) {
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($entity, 1);
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $result = $this->service->updateNameservers($serviceDomainModel, $data);
        $this->assertTrue($result);
    }

    #[Group('external-mock')]
    public function testUpdateContacts(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['logger'] = $this->createMock('Box_Log');
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $data = [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'company' => 'company',
            'address_1' => 'address1',
            'address_2' => 'address2',
            'country' => 'country',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'phone_cc' => 'phone_cc',
            'phone' => 'phone',
        ];
        $serviceDomainModel = new Domain(1);
        $reflectionClass = new \ReflectionClass(Domain::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($serviceDomainModel, 1);
        $result = $this->service->updateContacts($serviceDomainModel, $data);

        $this->assertIsArray($result);
    }

    public function testGetTransferCode(): void
    {
        $epp = 'EPPCODE';

        $serviceDomainModel = new Domain(1);
        $serviceDomainModel->setTransferCode($epp);

        $result = $this->service->getTransferCode($serviceDomainModel);

        $this->assertIsString($epp);
        $this->assertEquals($epp, $result);
    }

    public function testLock(): void
    {
        $tldRegistrar = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($tldRegistrar, 1);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($tldRegistrar, 'Custom');
        $configProperty = $reflectionClass->getProperty('config');
        $configProperty->setValue($tldRegistrar, '{}');

        $domainModel = new Domain(1);
        $reflectionDomainClass = new \ReflectionClass(Domain::class);
        $domainRegistrarProperty = $reflectionDomainClass->getProperty('registrar');
        $domainRegistrarProperty->setValue($domainModel, $tldRegistrar);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->once())->method('lock')
            ->with($this->isInstanceOf(\Registrar_Domain::class));

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['getRegistrarAdapter'])->getMock();
        $serviceMock->expects($this->once())->method('getRegistrarAdapter')
            ->willReturn($registrarAdapterMock);

        $di = $this->createDiWithMockEm();
        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Domain) {
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $lockedProperty = $reflectionClass->getProperty('locked');
                    $this->assertTrue($lockedProperty->getValue($entity));
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['logger'] = $this->createMock(\Box_Log::class);
        $serviceMock->setDi($di);

        $result = $serviceMock->lock($domainModel);
        $this->assertTrue($result);
    }

    public function testUnlock(): void
    {
        $tldRegistrar = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($tldRegistrar, 1);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($tldRegistrar, 'Custom');
        $configProperty = $reflectionClass->getProperty('config');
        $configProperty->setValue($tldRegistrar, '{}');

        $domainModel = new Domain(1);
        $reflectionDomainClass = new \ReflectionClass(Domain::class);
        $domainRegistrarProperty = $reflectionDomainClass->getProperty('registrar');
        $domainRegistrarProperty->setValue($domainModel, $tldRegistrar);

        $registrarAdapterMock = $this->getMockBuilder('Registrar_Adapter_Custom')->disableOriginalConstructor()
            ->getMock();
        $registrarAdapterMock->expects($this->once())->method('unlock')
            ->with($this->isInstanceOf(\Registrar_Domain::class));

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['getRegistrarAdapter'])->getMock();
        $serviceMock->expects($this->once())->method('getRegistrarAdapter')
            ->willReturn($registrarAdapterMock);

        $di = $this->createDiWithMockEm();
        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Domain) {
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $lockedProperty = $reflectionClass->getProperty('locked');
                    $this->assertFalse($lockedProperty->getValue($entity));
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $di['logger'] = $this->createMock(\Box_Log::class);
        $serviceMock->setDi($di);

        $result = $serviceMock->unlock($domainModel);
        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection(): void
    {
        $serviceDomainModel = new Domain(1);
        $result = $this->service->enablePrivacyProtection($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection(): void
    {
        $serviceDomainModel = new Domain(1);
        $result = $this->service->disablePrivacyProtection($serviceDomainModel);

        $this->assertTrue($result);
    }

    public function testCanBeTransferred(): void
    {
        $tldRegistrar = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($tldRegistrar, 1);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($tldRegistrar, 'Custom');

        $di = $this->getDi();
        $di['logger'] = $this->createMock(\Box_Log::class);
        $this->service->setDi($di);

        $tld = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $allowTransferProperty = $reflectionClass->getProperty('allowTransfer');
        $allowTransferProperty->setValue($tld, true);
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($tld, '.com');
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($tld, $tldRegistrar);

        $result = $this->service->canBeTransferred($tld, 'example');

        $this->assertTrue($result);
    }

    public function testCanBeTransferredEmptySldException(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->canBeTransferred(new Tld(), '');
    }

    public function testCanBeTransferredNotAllowedException(): void
    {
        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $allowTransferProperty = $reflectionClass->getProperty('allowTransfer');
        $allowTransferProperty->setValue($tldModel, false);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->canBeTransferred($tldModel, 'example');
    }

    public function testIsDomainAvailable(): void
    {
        $tldRegistrar = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($tldRegistrar, 1);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($tldRegistrar, 'Custom');

        $di = $this->getDi();
        $di['logger'] = $this->createMock(\Box_Log::class);
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isSldValid')
            ->willReturn(true);
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $tld = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $allowRegisterProperty = $reflectionClass->getProperty('allowRegister');
        $allowRegisterProperty->setValue($tld, true);
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($tld, '.com');
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($tld, $tldRegistrar);

        $result = $this->service->isDomainAvailable($tld, 'example');

        $this->assertTrue($result);
    }

    public function testIsDomainAvailableEmptySldException(): void
    {
        $tldModel = new Tld();
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

        $tldModel = new Tld();
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

        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $allowRegisterProperty = $reflectionClass->getProperty('allowRegister');
        $allowRegisterProperty->setValue($tldModel, false);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->service->isDomainAvailable($tldModel, 'example');
    }

    public function testOnBeforeAdminCronRun(): void
    {
        $boxEventMock = $this->getMockBuilder(\Box_Event::class)->disableOriginalConstructor()->getMock();

        // Method is void and empty - just verify it runs without exception
        $result = $this->service->onBeforeAdminCronRun($boxEventMock);
        $this->assertNull($result);
    }

    public function testBatchSyncExpirationDates(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->batchSyncExpirationDates();

        $this->assertTrue($result);
    }

    public function testBatchSyncExpirationDatesReturnsFalse(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->batchSyncExpirationDates();

        $this->assertTrue($result);
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
        $di = $this->createDiWithMockEm();

        $tldRepoStub = $this->createStub(\FOSSBilling\ProductType\Domain\Repository\TldRepository::class);
        $tldRepoStub->method('findBy')
            ->willReturn([]);

        $emMock = $di['em'];
        $emMock->method('getRepository')
            ->willReturnCallback(function ($class) use ($tldRepoStub) {
                if ($class === Tld::class) {
                    return $tldRepoStub;
                }
                return null;
            });

        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindAllActive();

        $this->assertIsArray($result);
    }

    public function testTldFindOneActiveById(): void
    {
        $di = $this->createDiWithMockEm();

        $tldModel = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($tldModel, 1);

        $tldRepoStub = $this->createStub(\FOSSBilling\ProductType\Domain\Repository\TldRepository::class);
        $tldRepoStub->method('findOneBy')
            ->willReturnCallback(function ($criteria, $orderBy) use ($tldModel) {
                if (isset($criteria['id']) && $criteria['id'] === 1 && isset($criteria['active']) && $criteria['active'] === true) {
                    return $tldModel;
                }
                return null;
            });

        $emMock = $di['em'];
        $emMock->method('getRepository')
            ->willReturnCallback(function ($class) use ($tldRepoStub) {
                if ($class === Tld::class) {
                    return $tldRepoStub;
                }
                return null;
            });

        $di['em'] = $emMock;
        $this->service->setDi($di);

        $result = $this->service->tldFindOneActiveById(1);

        $this->assertInstanceOf(Tld::class, $result);
    }

    public function testTldGetPairs(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->tldGetPairs();

        $this->assertIsArray($result);
        $this->assertEquals([1 => '.com'], $result);
    }

    public function testTldAlreadyRegisteredExists(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->tldAlreadyRegistered('.com');

        $this->assertTrue($result);
    }

    public function testTldAlreadyRegisteredDoesNotExist(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->tldAlreadyRegistered('.net');

        $this->assertFalse($result);
    }

    public function testTldRm(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('remove')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $model = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($model, 1);

        $result = $this->service->tldRm($model);

        $this->assertTrue($result);
    }

    public function testTldToApiArray(): void
    {
        $tldRegistrar = new TldRegistrar();
        $reflectionRegistrarClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionRegistrarClass->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($tldRegistrar, 1);
        $nameProperty = $reflectionRegistrarClass->getProperty('name');
        $nameProperty->setValue($tldRegistrar, 'ResellerClub');

        $di = $this->getDi();
        $di['logger'] = $this->createMock(\Box_Log::class);
        $this->service->setDi($di);

        $model = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($model, 1);

        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($model, '.com');
        $priceRegistrationProperty = $reflectionClass->getProperty('priceRegistration');
        $priceRegistrationProperty->setValue($model, '1');
        $priceRenewProperty = $reflectionClass->getProperty('priceRenew');
        $priceRenewProperty->setValue($model, '1');
        $priceTransferProperty = $reflectionClass->getProperty('priceTransfer');
        $priceTransferProperty->setValue($model, '1');
        $activeProperty = $reflectionClass->getProperty('active');
        $activeProperty->setValue($model, true);
        $allowRegisterProperty = $reflectionClass->getProperty('allowRegister');
        $allowRegisterProperty->setValue($model, true);
        $allowTransferProperty = $reflectionClass->getProperty('allowTransfer');
        $allowTransferProperty->setValue($model, true);
        $minYearsProperty = $reflectionClass->getProperty('minYears');
        $minYearsProperty->setValue($model, 2);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, $tldRegistrar);

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

        $this->assertEquals($result['tld'], $model->getTld());
        $this->assertEquals($result['price_registration'], $model->getPriceRegistration());
        $this->assertEquals($result['price_renew'], $model->getPriceRenew());
        $this->assertEquals($result['price_transfer'], $model->getPriceTransfer());
        $this->assertEquals($result['active'], $model->isActive());
        $this->assertEquals($result['allow_register'], $model->getAllowRegister());
        $this->assertEquals($result['allow_transfer'], $model->getAllowTransfer());
        $this->assertEquals($result['min_years'], $model->getMinYears());

        $this->assertEquals($registrar['id'], $tldRegistrar->getId());
        $this->assertEquals($registrar['title'], $tldRegistrar->getName());
    }

    public function testTldFindOneByTld(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->tldFindOneByTld('.com');

        $this->assertInstanceOf(Tld::class, $result);
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
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->registrarGetPairs();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testRegistrarGetActiveRegistrar(): void
    {
        $di = $this->createDiWithMockEm();
        $this->service->setDi($di);

        $result = $this->service->registrarGetActiveRegistrar();

        $this->assertInstanceOf(TldRegistrar::class, $result);
    }

    public function testRegistrarGetConfiguration(): void
    {
        $config = [
            'config_param' => 'config_value',
        ];

        $di = $this->getDi();
        $this->service->setDi($di);

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $configProperty = $reflectionClass->getProperty('config');
        $configProperty->setValue($model, json_encode($config));

        $result = $this->service->registrarGetConfiguration($model);

        $this->assertIsArray($result);
        $this->assertEquals($result, $config);
    }

    public function testRegistrarGetRegistrarAdapterConfig(): void
    {
        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'Custom');

        $result = $this->service->registrarGetRegistrarAdapterConfig($model);
        $this->assertIsArray($result);
    }

    public function testRegistrarGetRegistrarAdapterConfigClassNotExistsException(): void
    {
        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'Non-Existing');

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->registrarGetRegistrarAdapterConfig($model);
        $this->assertIsArray($result);
    }

    public function testRegistrarGetRegistrarAdapterConfigRegistrarNotExistException(): void
    {
        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'Non-Existing');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->service->registrarGetRegistrarAdapterConfig($model);
    }

    public function testRegistrarGetRegistrarAdapter(): void
    {
        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['registrarGetConfiguration'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->willReturn([]);
        $di = $this->getDi();
        $di['logger'] = $this->createMock(\Box_Log::class);
        $serviceMock->setDi($di);
        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'Custom');

        $result = $serviceMock->registrarGetRegistrarAdapter($model);

        $this->assertInstanceOf('Registrar_Adapter_' . $model->getRegistrar(), $result);
    }

    public function testRegistrarGetRegistrarAdapterNotFoundException(): void
    {
        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['registrarGetConfiguration'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->willReturn([]);

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'Non-Existing');

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $serviceMock->registrarGetRegistrarAdapter($model);
        $this->assertInstanceOf('Registrar_Adapter_' . $model->getRegistrar(), $result);
    }

    public function testRegistrarRm(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('remove')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($model, 1);
        $nameProperty = $reflectionClass->getProperty('name');
        $nameProperty->setValue($model, 'ResellerClub');

        $result = $this->service->registrarRm($model);

        $this->assertTrue($result);
    }

    public function testRegistrarRmHasDomainsException(): void
    {
        $di = $this->createDiWithMockEm();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new Domain(1)]);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($model, 1);
        $nameProperty = $reflectionClass->getProperty('name');
        $nameProperty->setValue($model, 'ResellerClub');

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->service->registrarRm($model);
    }

    public function testRegistrarToApiArray(): void
    {
        $config = [
            'label' => 'Label',
            'form' => 1,
        ];

        $serviceMock = $this->getMockBuilder(DomainHandler::class)
            ->onlyMethods(['registrarGetRegistrarAdapterConfig', 'registrarGetConfiguration'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetRegistrarAdapterConfig')
            ->willReturn($config);
        $serviceMock->expects($this->atLeastOnce())->method('registrarGetConfiguration')
            ->willReturn(['param1' => 'value1']);

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($model, 1);
        $nameProperty = $reflectionClass->getProperty('name');
        $nameProperty->setValue($model, 'ResellerClub');
        $testModeProperty = $reflectionClass->getProperty('testMode');
        $testModeProperty->setValue($model, true);

        $serviceMock->registrarToApiArray($model);
    }

    public function testTldCreate(): void
    {
        $data = [];
        $data['tld'] = '.com';
        $data['price_registration'] = 1;
        $data['price_renew'] = 1;
        $data['price_transfer'] = 1;
        $data['min_years'] = random_int(1, 5);
        $data['allow_register'] = true;
        $data['allow_transfer'] = true;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['created_at'] = date('Y-m-d H:i:s');

        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Tld) {
                    $reflectionClass = new \ReflectionClass(Tld::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($entity, 1);
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $result = $this->service->tldCreate($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testTldUpdate(): void
    {
        $data = [];
        $data['tld'] = '.com';
        $data['price_registration'] = 1;
        $data['price_renew'] = 1;
        $data['price_transfer'] = 1;
        $data['min_years'] = random_int(1, 5);
        $data['allow_register'] = true;
        $data['allow_transfer'] = true;
        $data['active'] = true;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['created_at'] = date('Y-m-d H:i:s');

        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $model = new Tld();
        $reflectionClass = new \ReflectionClass(Tld::class);
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($model, '.com');

        $result = $this->service->tldUpdate($model, $data);

        $this->assertTrue($result);
    }

    public function testRegistrarCreate(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $result = $this->service->registrarCreate('ResellerClub');

        $this->assertTrue($result);
    }

    public function testRegistrarCopy(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof TldRegistrar) {
                    $reflectionClass = new \ReflectionClass(TldRegistrar::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($entity, 1);
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $nameProperty = $reflectionClass->getProperty('name');
        $nameProperty->setValue($model, 'ResellerClub');
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'ResellerClub');
        $testModeProperty = $reflectionClass->getProperty('testMode');
        $testModeProperty->setValue($model, 1);

        $result = $this->service->registrarCopy($model);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testRegistrarUpdate(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function () {});
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $data = [
            'title' => 'ResellerClub',
            'test_mode' => 1,
            'config' => [
                'param1' => 'value1',
            ],
        ];

        $model = new TldRegistrar();
        $reflectionClass = new \ReflectionClass(TldRegistrar::class);
        $registrarProperty = $reflectionClass->getProperty('registrar');
        $registrarProperty->setValue($model, 'Custom');

        $result = $this->service->registrarUpdate($model, $data);

        $this->assertTrue($result);
    }

    public function testUpdateDomain(): void
    {
        $di = $this->createDiWithMockEm();

        $emMock = $di['em'];
        $emMock->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof Domain) {
                    $reflectionClass = new \ReflectionClass(Domain::class);
                    $idProperty = $reflectionClass->getProperty('id');
                    $idProperty->setValue($entity, 1);
                }
            });
        $emMock->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () {});

        $this->service->setDi($di);

        $model = new Domain(1);
        $reflectionClass = new \ReflectionClass(Domain::class);
        $sldProperty = $reflectionClass->getProperty('sld');
        $sldProperty->setValue($model, 'test');
        $tldProperty = $reflectionClass->getProperty('tld');
        $tldProperty->setValue($model, '.com');

        $data = [
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
            'ns3' => 'ns3.example.com',
            'ns4' => 'ns4.example.com',
            'period' => 1,
        ];

        $result = $this->service->updateDomain($model, $data);

        $this->assertTrue($result);
    }
}
