<?php

namespace Box\Tests\Mod\Client;

class ServiceTest extends \BBTestCase
{
    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testapproveClientEmailByHash(): void
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())
            ->method('getRow')->willReturn(['client_id' => 2, 'id' => 1]);

        $database->expects($this->atLeastOnce())->method('exec');

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->approveClientEmailByHash('');

        $this->assertTrue($result);
    }

    public function testapproveClientEmailByHashException(): void
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())
            ->method('getRow')->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invalid email confirmation link');
        $clientService->approveClientEmailByHash('');
    }

    public function testgenerateEmailConfirmationLink(): void
    {
        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())
            ->method('dispense')->willReturn($model);

        $database->expects($this->atLeastOnce())->method('store')
            ->willReturn(1);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('url')
            ->willReturn('fossbilling.org/index.php/client/confirm-email/');

        $di = new \Pimple\Container();
        $di['db'] = $database;
        $di['tools'] = $toolsMock;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $client_id = 1;
        $result = $clientService->generateEmailConfirmationLink($client_id);

        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, '/client/confirm-email/'));
    }

    public function testonAfterClientSignUp(): void
    {
        $eventParams = [
            'password' => 'testPassword',
            'id' => 1,
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('getParameters')->willReturn($eventParams);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);
        $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => false]);

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->onAfterClientSignUp($eventMock);

        $this->assertTrue($result);
    }

    public function testRequireEmailConfirmonAfterClientSignUp(): void
    {
        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventParams = [
            'password' => 'testPassword',
            'id' => 1,
            'require_email_confirmation' => true,
        ];

        $eventMock->expects($this->atLeastOnce())->
            method('getParameters')->willReturn($eventParams);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('sendTemplate')
            ->willReturn(true);

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->onlyMethods(['generateEmailConfirmationLink'])->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->
            method('generateEmailConfirmationLink')->willReturn('Link_string');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($service, $clientServiceMock) {
            if ($serviceName == 'email') {
                return $service;
            }
            if ($serviceName == 'client') {
                return $clientServiceMock;
            }
        });
        $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => true]);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->onAfterClientSignUp($eventMock);

        $this->assertTrue($result);
    }

    public function testExceptiononAfterClientSignUp(): void
    {
        $eventParams = [
            'password' => 'testPassword',
            'id' => 1,
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('getParameters')->willReturn($eventParams);

        $service = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)->getMock();
        $service->expects($this->atLeastOnce())->
            method('sendTemplate')->will($this->throwException(new \Exception('exception created in unit test')));

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);
        $di['mod_config'] = $di->protect(function ($name): void {
            ['require_email_confirmation' => false];
        });
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->onAfterClientSignUp($eventMock);

        $this->assertTrue($result);
    }

    public static function searchQueryData(): array
    {
        return [
            [[], 'SELECT c.*', []],
            [
                ['id' => 1],
                'c.id = :client_id or c.aid = :alt_client_id',
                [':client_id' => '', ':alt_client_id' => ''],
            ],
            [
                ['name' => 'test'],
                '(c.first_name LIKE :first_name or c.last_name LIKE :last_name )',
                [':first_name' => '', ':last_name' => ''],
            ],
            [
                ['email' => 'test@example.com'],
                'c.email LIKE :email',
                [':email' => 'test@example.com'],
            ],
            [
                ['company' => 'LTD company'],
                'c.company LIKE :company',
                [':company' => 'LTD company'],
            ],
            [
                ['status' => 'TEST status'],
                'c.status = :status',
                [':status' => 'TEST status'],
            ],
            [
                ['group_id' => '1'],
                'c.client_group_id = :group_id',
                [':group_id' => '1'],
            ],
            [
                ['created_at' => '2012-12-12'],
                "DATE_FORMAT(c.created_at, '%Y-%m-%d') = :created_at",
                [':created_at' => '2012-12-12'],
            ],
            [
                ['date_from' => '2012-12-10'],
                'UNIX_TIMESTAMP(c.created_at) >= :date_from',
                [':date_from' => '2012-12-10'],
            ],
            [
                ['date_to' => '2012-12-11'],
                'UNIX_TIMESTAMP(c.created_at) <= :date_from',
                [':date_to' => '2012-12-11'],
            ],
            [
                ['search' => '2'],
                'c.id = :cid or c.aid = :caid',
                [':cid' => '2', ':caid' => '2'],
            ],
            [
                ['search' => 'Keyword'],
                "c.company LIKE :s_company OR c.first_name LIKE :s_first_time OR c.last_name LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.first_name,  ' ', c.last_name ) LIKE  :full_name",
                [':s_company' => 'Keyword',
                    ':s_first_time' => 'Keyword',
                    ':s_last_name' => 'Keyword',
                    ':s_email' => 'Keyword',
                    ':full_name' => 'Keyword',
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueryData')]
    public function testgetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == []);
    }

    public function testgetSearchQueryChangeSelect(): void
    {
        $data = [];
        $selectStmt = 'c.id, CONCAT(c.first_name, c.last_name) as full_name';
        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->getSearchQuery($data, $selectStmt);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $selectStmt), $result[0]);
    }

    public function testgetPairs(): void
    {
        $data = [];

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('getAssoc')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->getPairs($data);
        $this->assertIsArray($result);
    }

    public function testtoSessionArray(): void
    {
        $expectedArrayKeys = [
            'id' => 1,
            'email' => 'email@example.com',
            'name' => 'John Smith',
            'role' => 'admin',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $clientService = new \Box\Mod\Client\Service();
        $result = $clientService->toSessionArray($model);

        $this->assertIsArray($result);
        $this->assertTrue(array_diff_key($result, $expectedArrayKeys) == []);
    }

    public function testemailAlreadyRegistered(): void
    {
        $email = 'test@example.com';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->emailAlreadyRegistered($email);
        $this->assertIsBool($result);
    }

    public function testEmailAlreadyRegWithModel(): void
    {
        $email = 'test@example.com';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->email = $email;

        $clientService = new \Box\Mod\Client\Service();

        $result = $clientService->emailAlreadyRegistered($email, $model);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testcanChangeCurrency(): void
    {
        $currency = 'EUR';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->currency = 'USD';

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);
        $result = $clientService->canChangeCurrency($model, $currency);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcanChangeCurrencyModelCurrencyNotSet(): void
    {
        $currency = 'EUR';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->never())->method('findOne');

        $clientService = new \Box\Mod\Client\Service();

        $result = $clientService->canChangeCurrency($model, $currency);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcanChangeCurrencyIdenticalCurrencies(): void
    {
        $currency = 'EUR';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->currency = $currency;

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->never())->method('findOne');

        $clientService = new \Box\Mod\Client\Service();

        $result = $clientService->canChangeCurrency($model, $currency);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testcanChangeCurrencyHasInvoice(): void
    {
        $currency = 'EUR';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);
        $model->currency = 'USD';

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->once())
                 ->method('findOne')
            ->willReturn($invoiceModel);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Currency cannot be changed. Client already has invoices issued.');
        $clientService->canChangeCurrency($model, $currency);
    }

    /* Disabled due to the random PHPUnit failures
    public function testcanChangeCurrencyHasOrder(): void
    {
        $currency = 'EUR';
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);
        $model->currency = 'USD';

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->exactly(2))->method('findOne')
            ->willReturnOnConsecutiveCalls(null, null);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $clientService->canChangeCurrency($model, $currency);
    }
    */

    public static function searchBalanceQueryData(): array
    {
        return [
            [[], 'FROM client_balance as m', []],
            [
                ['id' => 1],
                'm.id = :id',
                [':id' => '1'],
            ],
            [
                ['client_id' => 1],
                'm.client_id = :client_id',
                [':client_id' => '1'],
            ],
            [
                ['date_from' => '2012-12-10'],
                'm.created_at >= :date_from',
                [':date_from' => '2012-12-10'],
            ],
            [
                ['date_to' => '2012-12-11'],
                'm.created_at <= :date_to',
                [':date_to' => '2012-12-11'],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchBalanceQueryData')]
    public function testgetBalanceSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $clientBalanceService = new \Box\Mod\Client\ServiceBalance();
        $clientBalanceService->setDi($di);
        [$sql, $params] = $clientBalanceService->getSearchQuery($data);
        $this->assertNotEmpty($sql);
        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, $expectedStr), $sql);
        $this->assertTrue(array_diff_key($params, $expectedParams) == []);
    }

    public function testaddFunds(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());

        $amount = '2.22';
        $description = 'test description';

        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('dispense')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->addFunds($modelClient, $amount, $description);
        $this->assertTrue($result);
    }

    public function testaddFundsCurrencyNotDefined(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $amount = '2.22';
        $description = 'test description';

        $clientService = new \Box\Mod\Client\Service();

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('You must define the client\'s currency before adding funds.');
        $clientService->addFunds($modelClient, $amount, $description);
    }

    public function testaddFundsAmountMissing(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $amount = null;
        $description = '';

        $clientService = new \Box\Mod\Client\Service();

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Funds amount is invalid');
        $clientService->addFunds($modelClient, $amount, $description);
    }

    public function testaddFundsInvalidDescription(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $amount = '2.22';
        $description = null;

        $clientService = new \Box\Mod\Client\Service();

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Funds description is invalid');
        $result = $clientService->addFunds($modelClient, $amount, $description);
        $this->assertTrue($result);
    }

    public function testgetExpiredPasswordReminders(): void
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('find')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->getExpiredPasswordReminders();
        $this->assertIsArray($result);
    }

    public static function searchHistoryQueryData(): array
    {
        return [
            [[], 'SELECT ach.*, c.first_name, c.last_name, c.email', []],
            [
                ['search' => 'sameValue'],
                'c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR c.id LIKE :id',
                [
                    ':first_name' => '%sameValue%',
                    ':last_name' => '%sameValue%',
                    ':id' => 'sameValue'],
            ],
            [
                ['client_id' => '1'],
                'ach.client_id = :client_id',
                [':client_id' => '1'],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchHistoryQueryData')]
    public function testgetHistorySearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $clientService = new \Box\Mod\Client\Service();
        $di = new \Pimple\Container();

        $clientService->setDi($di);
        [$sql, $params] = $clientService->getHistorySearchQuery($data);
        $this->assertNotEmpty($sql);
        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, $expectedStr), $sql);
        $this->assertTrue(array_diff_key($params, $expectedParams) == []);
    }

    public function testcounter(): void
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('getAssoc')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->counter();
        $this->assertIsArray($result);

        $expected = [
            'total' => 0,
            \Model_Client::ACTIVE => 0,
            \Model_Client::SUSPENDED => 0,
            \Model_Client::CANCELED => 0,
        ];
    }

    public function testgetGroupPairs(): void
    {
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('getAssoc')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->getGroupPairs();
        $this->assertIsArray($result);
    }

    public function testclientAlreadyExists(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->clientAlreadyExists('email@example.com');
        $this->assertTrue($result);
    }

    public function testgetByLoginDetails(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $database = $this->getMockBuilder('\Box_Database')->getMock();
        $database->expects($this->atLeastOnce())->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $database;

        $clientService = new \Box\Mod\Client\Service();
        $clientService->setDi($di);

        $result = $clientService->getByLoginDetails('email@example.com', 'password');
        $this->assertInstanceOf('\Model_Client', $result);
    }

    public static function getProvider(): array
    {
        return [
            ['id', 1],
            ['email', 'test@email.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getProvider')]
    public function testget(string $fieldName, int|string $fieldValue): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $data = [$fieldName => $fieldValue];
        $result = $service->get($data);
        $this->assertInstanceOf('\Model_Client', $result);
    }

    public function testgetClientNotFound(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $data = ['id' => 0];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Client not found');
        $service->get($data);
    }

    public function testgetClientBalance(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')->willReturn(1.0);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->getClientBalance($model);
        $this->assertIsNumeric($result);
    }

    public function testtoApiArray(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $model->custom_1 = 'custom field';

        $clientGroup = new \Model_ClientGroup();
        $clientGroup->loadBean(new \DummyBean());
        $clientGroup->title = 'Group Title';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')->willReturn([]);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')->willReturn($clientGroup);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(['getClientBalance'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getClientBalance');

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public static function isClientTaxableProvider(): array
    {
        $self = new ServiceTest('ServiceTest');
        $self->assertTrue(true);

        return [
            [
                false,
                false,
                false,
            ],
            [
                true,
                true,
                false,
            ],
            [
                true,
                false,
                true,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('isClientTaxableProvider')]
    public function testIsClientTaxable(bool $getParamValueReturn, bool $tax_exempt, bool $expected): void
    {
        $service = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $service->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn($getParamValueReturn);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->tax_exempt = $tax_exempt;

        $result = $service->isClientTaxable($client);
        $this->assertEquals($expected, $result);
    }

    public function testadminCreateClient(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;

        $data = [
            'password' => uniqid(),
            'email' => 'test@unit.vm',
            'first_name' => 'test',
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->exactly(2))
            ->method('fire');

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventManagerMock;
        $di['logger'] = new \Box_Log();
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['password'] = $passwordMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->adminCreateClient($data);
        $this->assertIsInt($result);
    }

    public function testdeleteGroup(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne');
        $dbMock->expects($this->once())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());
        $result = $service->deleteGroup($model);
        $this->assertTrue($result);
    }

    public function testdeleteGroupGroupHasClients(): void
    {
        $clientModel = new \Model_Client();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Cannot remove groups with clients');
        $service->deleteGroup($model);
    }

    public function testauthorizeClientDidntFoundEmail(): void
    {
        $email = 'example@fossbilling.vm';
        $password = '123456';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn(null);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with(null, $password)
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertNull($result);
    }

    public function testauthorizeClient(): void
    {
        $email = 'example@fossbilling.vm';
        $password = '123456';

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn($clientModel);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->atLeastOnce())
            ->method('authorizeUser')
            ->with($clientModel, $password)
            ->willReturn($clientModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;
        $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => false]);
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertInstanceOf('\Model_Client', $result);
    }

    public function testauthorizeClientEmailRequiredConfirmed(): void
    {
        $email = 'example@fossbilling.vm';
        $password = '123456';

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->email_approved = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('Client')
            ->willReturn($clientModel);

        $authMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authMock->expects($this->any())
            ->method('authorizeUser')
            ->with($clientModel, $password)
            ->willReturn($clientModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['auth'] = $authMock;
        $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => true]);
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->authorizeClient($email, $password);
        $this->assertInstanceOf('\Model_Client', $result);
    }

    public function testcanChangeEmail(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $email = 'client@fossbilling.org';

        $config = [
            'disable_change_email' => false,
        ];

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->canChangeEmail($clientModel, $email);
        $this->assertTrue($result);
    }

    public function testcanChangeEmailEmailAreTheSame(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $email = 'client@fossbilling.org';

        $clientModel->email = $email;

        $config = [
            'disable_change_email' => false,
        ];

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->canChangeEmail($clientModel, $email);
        $this->assertTrue($result);
    }

    public function testcanChangeEmailEmptyConfig(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $email = 'client@fossbilling.org';

        $config = [];

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $result = $service->canChangeEmail($clientModel, $email);
        $this->assertTrue($result);
    }

    public function testcanChangeEmailCanntChangeEmail(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $email = 'client@fossbilling.org';

        $config = [
            'disable_change_email' => true,
        ];

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Email address cannot be changed');
        $service->canChangeEmail($clientModel, $email);
    }

    public function testcheckExtraRequiredFields(): void
    {
        $required = ['id'];
        $data = [];

        $config['required'] = $required;
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);

        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Field Id cannot be empty');
        $service->checkExtraRequiredFields($data);
    }

    public function testcheckCustomFields(): void
    {
        $custom_field = [
            'custom_field_name' => [
                'active' => true,
                'required' => true,
                'title' => 'custom_field_title',
            ],
        ];
        $config['custom_fields'] = $custom_field;
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);

        $data = [];
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Field custom_field_title cannot be empty');
        $service->checkCustomFields($data);
    }

    public function testcheckCustomFieldsNotRequired(): void
    {
        $custom_field = [
            'custom_field_name' => [
                'active' => true,
                'required' => false,
                'title' => 'custom_field_title',
            ],
        ];
        $config['custom_fields'] = $custom_field;
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($modName): array => $config);

        $data = [];
        $service = new \Box\Mod\Client\Service();
        $service->setDi($di);
        $result = $service->checkCustomFields($data);
        $this->assertNull($result);
    }
}
