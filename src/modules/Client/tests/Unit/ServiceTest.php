<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('getDi returns dependency injection container', function (): void {
    $service = new \Box\Mod\Client\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('approveClientEmailByHash returns true', function (): void {
    $service = new \Box\Mod\Client\Service();
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('getRow')
        ->atLeast()->once()
        ->andReturn(['client_id' => 2, 'id' => 1]);
    $database->shouldReceive('exec')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);
    $result = $service->approveClientEmailByHash('');

    expect($result)->toBeTrue();
});

test('approveClientEmailByHash throws exception for invalid hash', function (): void {
    $service = new \Box\Mod\Client\Service();
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('getRow')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $service->approveClientEmailByHash('');
})->throws(\FOSSBilling\Exception::class, 'Invalid email confirmation link');

test('generateEmailConfirmationLink returns string', function (): void {
    $service = new \Box\Mod\Client\Service();

    $model = new \Model_ExtensionMeta();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $database->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('url')
        ->atLeast()->once()
        ->andReturn('fossbilling.org/index.php/client/confirm-email/');
    $toolsMock->shouldReceive('generatePassword')
        ->atLeast()->once()
        ->andReturn('randomhash123456789012345678901234567890');

    $di = container();
    $di['db'] = $database;
    $di['tools'] = $toolsMock;

    $service->setDi($di);

    $clientId = 1;
    $result = $service->generateEmailConfirmationLink($clientId);

    expect($result)->toBeString();
    expect(str_contains($result, '/client/confirm-email/'))->toBeTrue();
});

test('onAfterClientSignUp returns true', function (): void {
    $service = new \Box\Mod\Client\Service();
    $eventParams = [
        'password' => 'testPassword',
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($eventParams);

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $emailService);
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => false]);

    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->setDi($di);
    $result = $service->onAfterClientSignUp($eventMock);

    expect($result)->toBeTrue();
});

test('onAfterClientSignUp with email confirmation required returns true', function (): void {
    $service = new \Box\Mod\Client\Service();
    $eventMock = Mockery::mock('\Box_Event');
    $eventParams = [
        'password' => 'testPassword',
        'id' => 1,
        'require_email_confirmation' => true,
    ];

    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($eventParams);

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $clientServiceMock = Mockery::mock(\Box\Mod\Client\Service::class)->makePartial();
    $clientServiceMock->shouldReceive('generateEmailConfirmationLink')
        ->atLeast()->once()
        ->andReturn('Link_string');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailService, $clientServiceMock) {
        if ($serviceName == 'email') {
            return $emailService;
        }
        if ($serviceName == 'client') {
            return $clientServiceMock;
        }
    });
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => true]);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $result = $service->onAfterClientSignUp($eventMock);

    expect($result)->toBeTrue();
});

test('onAfterClientSignUp handles exception gracefully', function (): void {
    $service = new \Box\Mod\Client\Service();
    $eventParams = [
        'password' => 'testPassword',
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($eventParams);

    $emailService = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('exception created in unit test'));

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $emailService);
    $di['mod_config'] = $di->protect(function ($name): void {
        ['require_email_confirmation' => false];
    });
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->setDi($di);
    $result = $service->onAfterClientSignUp($eventMock);

    expect($result)->toBeTrue();
});

dataset('searchQueryData', [
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
        '(c.id = :cid OR c.aid = :caid)',
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
]);

test('getSearchQuery returns correct query and params', function ($data, $expectedStr, $expectedParams) {
    $service = new \Box\Mod\Client\Service();
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toEqual([]);
})->with('searchQueryData');

test('getSearchQuery with custom select statement', function (): void {
    $service = new \Box\Mod\Client\Service();
    $data = [];
    $selectStmt = 'c.id, CONCAT(c.first_name, c.last_name) as full_name';
    $result = $service->getSearchQuery($data, $selectStmt);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $selectStmt))->toBeTrue($result[0]);
});

test('getPairs returns array', function (): void {
    $service = new \Box\Mod\Client\Service();
    $data = [];

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);
    $result = $service->getPairs($data);
    expect($result)->toBeArray();
});

test('toSessionArray returns array with expected keys', function (): void {
    $service = new \Box\Mod\Client\Service();
    $expectedArrayKeys = [
        'id' => 1,
        'email' => 'email@example.com',
        'name' => 'John Smith',
        'role' => 'admin',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $result = $service->toSessionArray($model);

    expect($result)->toBeArray();
    expect(array_diff_key($result, $expectedArrayKeys))->toEqual([]);
});

test('emailAlreadyRegistered returns boolean', function (): void {
    $service = new \Box\Mod\Client\Service();
    $email = 'test@example.com';
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->emailAlreadyRegistered($email);
    expect($result)->toBeBool();
});

test('emailAlreadyRegistered with model returns false for same email', function (): void {
    $service = new \Box\Mod\Client\Service();
    $email = 'test@example.com';
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->email = $email;

    $result = $service->emailAlreadyRegistered($email, $model);
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('canChangeCurrency returns true when no invoices exist', function (): void {
    $service = new \Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->currency = 'USD';

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);
    $result = $service->canChangeCurrency($model, $currency);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('canChangeCurrency returns true when model currency is not set', function (): void {
    $service = new \Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')->never();

    $result = $service->canChangeCurrency($model, $currency);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('canChangeCurrency returns false when currencies are identical', function (): void {
    $service = new \Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->currency = $currency;

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')->never();

    $result = $service->canChangeCurrency($model, $currency);
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('canChangeCurrency throws exception when client has invoices', function (): void {
    $service = new \Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->currency = 'USD';

    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')
        ->once()
        ->andReturn($invoiceModel);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $service->canChangeCurrency($model, $currency);
})->throws(\FOSSBilling\Exception::class, 'Currency cannot be changed. Client already has invoices issued.');

dataset('searchBalanceQueryData', [
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
]);

test('getBalanceSearchQuery returns correct query and params', function ($data, $expectedStr, $expectedParams) {
    $service = new \Box\Mod\Client\Service();
    $di = container();

    $clientBalanceService = new \Box\Mod\Client\ServiceBalance();
    $clientBalanceService->setDi($di);
    [$sql, $params] = $clientBalanceService->getSearchQuery($data);
    expect($sql)->not->toBeEmpty();
    expect($sql)->toBeString();
    expect($params)->toBeArray();

    expect(str_contains($sql, $expectedStr))->toBeTrue($sql);
    expect(array_diff_key($params, $expectedParams))->toEqual([]);
})->with('searchBalanceQueryData');

test('addFunds returns true', function (): void {
    $service = new \Box\Mod\Client\Service();
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $model = new \Model_ClientBalance();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $amount = '2.22';
    $description = 'test description';

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $database->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->addFunds($modelClient, $amount, $description);
    expect($result)->toBeTrue();
});

test('addFunds throws exception when currency is not defined', function (): void {
    $service = new \Box\Mod\Client\Service();
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $amount = '2.22';
    $description = 'test description';

    $service->addFunds($modelClient, $amount, $description);
})->throws(\FOSSBilling\Exception::class, "You must define the client's currency before adding funds.");

test('addFunds throws exception when amount is missing', function (): void {
    $service = new \Box\Mod\Client\Service();
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $amount = null;
    $description = '';

    $service->addFunds($modelClient, $amount, $description);
})->throws(\FOSSBilling\Exception::class, 'Funds amount is invalid');

test('addFunds throws exception when description is invalid', function (): void {
    $service = new \Box\Mod\Client\Service();
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $amount = '2.22';
    $description = null;

    $service->addFunds($modelClient, $amount, $description);
})->throws(\FOSSBilling\Exception::class, 'Funds description is invalid');

test('getExpiredPasswordReminders returns array', function (): void {
    $service = new \Box\Mod\Client\Service();
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->getExpiredPasswordReminders();
    expect($result)->toBeArray();
});

dataset('searchHistoryQueryData', [
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
]);

test('getHistorySearchQuery returns correct query and params', function ($data, $expectedStr, $expectedParams) {
    $service = new \Box\Mod\Client\Service();
    $di = container();

    $service->setDi($di);
    [$sql, $params] = $service->getHistorySearchQuery($data);
    expect($sql)->not->toBeEmpty();
    expect($sql)->toBeString();
    expect($params)->toBeArray();

    expect(str_contains($sql, $expectedStr))->toBeTrue($sql);
    expect(array_diff_key($params, $expectedParams))->toEqual([]);
})->with('searchHistoryQueryData');

test('counter returns array', function (): void {
    $service = new \Box\Mod\Client\Service();
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->counter();
    expect($result)->toBeArray();

    $expected = [
        'total' => 0,
        \Model_Client::ACTIVE => 0,
        \Model_Client::SUSPENDED => 0,
        \Model_Client::CANCELED => 0,
    ];
});

test('getGroupPairs returns array', function (): void {
    $service = new \Box\Mod\Client\Service();
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->getGroupPairs();
    expect($result)->toBeArray();
});

test('clientAlreadyExists returns true when client exists', function (): void {
    $service = new \Box\Mod\Client\Service();
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->clientAlreadyExists('email@example.com');
    expect($result)->toBeTrue();
});

test('getByLoginDetails returns Model_Client', function (): void {
    $service = new \Box\Mod\Client\Service();
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $database;

    $service->setDi($di);

    $result = $service->getByLoginDetails('email@example.com', 'password');
    expect($result)->toBeInstanceOf(\Model_Client::class);
});

dataset('getProvider', [
    ['id', 1],
    ['email', 'test@email.com'],
]);

test('get returns Model_Client', function ($fieldName, $fieldValue) {
    $service = new \Box\Mod\Client\Service();
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $data = [$fieldName => $fieldValue];
    $result = $service->get($data);
    expect($result)->toBeInstanceOf(\Model_Client::class);
})->with('getProvider');

test('get throws exception when client not found', function (): void {
    $service = new \Box\Mod\Client\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $data = ['id' => 0];
    $service->get($data);
})->throws(\FOSSBilling\Exception::class, 'Client not found');

test('getClientBalance returns numeric', function (): void {
    $service = new \Box\Mod\Client\Service();
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1.0);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getClientBalance($model);
    expect($result)->toBeNumeric();
});

test('toApiArray returns array', function (): void {
    $service = new \Box\Mod\Client\Service();
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->custom_1 = 'custom field';

    $clientGroup = new \Model_ClientGroup();
    $clientGroup->loadBean(new \Tests\Helpers\DummyBean());
    $clientGroup->title = 'Group Title';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($clientGroup);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(\Box\Mod\Client\Service::class)->makePartial();
    $serviceMock->shouldReceive('getClientBalance')
        ->atLeast()->once();

    $serviceMock->setDi($di);

    $result = $serviceMock->toApiArray($model, true, new \Model_Admin());
    expect($result)->toBeArray();
});

dataset('isClientTaxableProvider', [
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
]);

test('isClientTaxable returns correct value', function ($getParamValueReturn, $tax_exempt, $expected) {
    $service = new \Box\Mod\Client\Service();
    $systemService = Mockery::mock(\Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn($getParamValueReturn);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemService);

    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->tax_exempt = $tax_exempt;

    $result = $service->isClientTaxable($client);
    expect($result)->toEqual($expected);
})->with('isClientTaxableProvider');

test('adminCreateClient returns int', function (): void {
    $service = new \Box\Mod\Client\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->id = 1;

    $data = [
        'password' => uniqid(),
        'email' => 'test@unit.vm',
        'first_name' => 'test',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($clientModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->twice();

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->atLeast()->once()
        ->with($data['password']);

    $modMock = Mockery::mock(\FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);
    $di['password'] = $passwordMock;

    $service->setDi($di);

    $result = $service->adminCreateClient($data);
    expect($result)->toBeInt();
});

test('deleteGroup returns true', function (): void {
    $service = new \Box\Mod\Client\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once();
    $dbMock->shouldReceive('trash')
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $model = new \Model_ClientGroup();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $result = $service->deleteGroup($model);
    expect($result)->toBeTrue();
});

test('deleteGroup throws exception when group has clients', function (): void {
    $service = new \Box\Mod\Client\Service();
    $clientModel = new \Model_Client();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($clientModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $model = new \Model_ClientGroup();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $service->deleteGroup($model);
})->throws(\FOSSBilling\Exception::class, 'Cannot remove groups with clients');

test('authorizeClient returns null when email not found', function (): void {
    $service = new \Box\Mod\Client\Service();
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->with('Client', Mockery::any(), Mockery::any())
        ->andReturn(null);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')
        ->atLeast()->once()
        ->with(null, $password)
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['auth'] = $authMock;

    $service->setDi($di);

    $result = $service->authorizeClient($email, $password);
    expect($result)->toBeNull();
});

test('authorizeClient returns Model_Client', function (): void {
    $service = new \Box\Mod\Client\Service();
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->with('Client', Mockery::any(), Mockery::any())
        ->andReturn($clientModel);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')
        ->atLeast()->once()
        ->with($clientModel, $password)
        ->andReturn($clientModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['auth'] = $authMock;
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => false]);

    $service->setDi($di);

    $result = $service->authorizeClient($email, $password);
    expect($result)->toBeInstanceOf(\Model_Client::class);
});

test('authorizeClient with confirmed email returns Model_Client', function (): void {
    $service = new \Box\Mod\Client\Service();
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->email_approved = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->with('Client', Mockery::any(), Mockery::any())
        ->andReturn($clientModel);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')
        ->atLeast()->once()
        ->with($clientModel, $password)
        ->andReturn($clientModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['auth'] = $authMock;
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => true]);

    $service->setDi($di);

    $result = $service->authorizeClient($email, $password);
    expect($result)->toBeInstanceOf(\Model_Client::class);
});

test('canChangeEmail returns true', function (): void {
    $service = new \Box\Mod\Client\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $email = 'client@fossbilling.org';

    $config = [
        'disable_change_email' => false,
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $service->setDi($di);

    $result = $service->canChangeEmail($clientModel, $email);
    expect($result)->toBeTrue();
});

test('canChangeEmail returns true when emails are the same', function (): void {
    $service = new \Box\Mod\Client\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $email = 'client@fossbilling.org';

    $clientModel->email = $email;

    $config = [
        'disable_change_email' => false,
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $service->setDi($di);

    $result = $service->canChangeEmail($clientModel, $email);
    expect($result)->toBeTrue();
});

test('canChangeEmail returns true with empty config', function (): void {
    $service = new \Box\Mod\Client\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $email = 'client@fossbilling.org';

    $config = [];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $service->setDi($di);

    $result = $service->canChangeEmail($clientModel, $email);
    expect($result)->toBeTrue();
});

test('canChangeEmail throws exception when email change is disabled', function (): void {
    $service = new \Box\Mod\Client\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $email = 'client@fossbilling.org';

    $config = [
        'disable_change_email' => true,
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $service->setDi($di);

    $service->canChangeEmail($clientModel, $email);
})->throws(\FOSSBilling\Exception::class, 'Email address cannot be changed');

test('checkExtraRequiredFields throws exception for missing field', function (): void {
    $service = new \Box\Mod\Client\Service();
    $required = ['id'];
    $data = [];

    $config['required'] = $required;
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $service->setDi($di);
    $service->checkExtraRequiredFields($data);
})->throws(\FOSSBilling\Exception::class, 'Field Id cannot be empty');

test('checkCustomFields throws exception for required field', function (): void {
    $service = new \Box\Mod\Client\Service();
    $custom_field = [
        'custom_field_name' => [
            'active' => true,
            'required' => true,
            'title' => 'custom_field_title',
        ],
    ];
    $config['custom_fields'] = $custom_field;
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $data = [];
    $service->setDi($di);
    $service->checkCustomFields($data);
})->throws(\FOSSBilling\Exception::class, 'Field custom_field_title cannot be empty');

test('checkCustomFields returns null when field is not required', function (): void {
    $service = new \Box\Mod\Client\Service();
    $custom_field = [
        'custom_field_name' => [
            'active' => true,
            'required' => false,
            'title' => 'custom_field_title',
        ],
    ];
    $config['custom_fields'] = $custom_field;
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $data = [];
    $service->setDi($di);
    $result = $service->checkCustomFields($data);
    expect($result)->toBeNull();
});
