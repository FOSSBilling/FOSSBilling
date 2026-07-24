<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

test('getDi returns dependency injection container', function (): void {
    $service = new Box\Mod\Client\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('approveClientEmailByHash returns true', function (): void {
    $service = new Box\Mod\Client\Service();

    $dbal = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbal->shouldReceive('fetchAssociative')->atLeast()->once()->andReturn(['client_id' => 2, 'id' => 1]);
    $dbal->shouldReceive('executeStatement')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['dbal'] = $dbal;

    $service->setDi($di);
    $result = $service->approveClientEmailByHash('');

    expect($result)->toBeTrue();
});

test('approveClientEmailByHash throws exception for invalid hash', function (): void {
    $service = new Box\Mod\Client\Service();

    $di = container();

    $service->setDi($di);

    $service->approveClientEmailByHash('');
})->throws(FOSSBilling\Exception::class, 'Invalid email confirmation link');

test('generateEmailConfirmationLink returns string', function (): void {
    $service = new Box\Mod\Client\Service();

    $model = createEntity(Box\Mod\Extension\Entity\ExtensionMeta::class);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('url')
        ->atLeast()->once()
        ->andReturn('fossbilling.org/index.php/client/confirm-email/');
    $toolsMock->shouldReceive('generatePassword')
        ->atLeast()->once()
        ->andReturn('randomhash123456789012345678901234567890');

    $di = container();
    $di['tools'] = $toolsMock;

    $service->setDi($di);

    $clientId = 1;
    $result = $service->generateEmailConfirmationLink($clientId);

    expect($result)->toBeString();
    expect(str_contains((string) $result, '/client/confirm-email/'))->toBeTrue();
});

test('onAfterClientSignUp returns true', function (): void {
    $service = new Box\Mod\Client\Service();
    $eventParams = [
        'password' => 'testPassword',
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($eventParams);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $emailService);
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => false]);

    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $service->setDi($di);
    $result = $service->onAfterClientSignUp($eventMock);

    expect($result)->toBeTrue();
});

test('onAfterClientSignUp with email confirmation required returns true', function (): void {
    $service = new Box\Mod\Client\Service();
    $eventMock = Mockery::mock('\Box_Event');
    $eventParams = [
        'password' => 'testPassword',
        'id' => 1,
        'require_email_confirmation' => true,
    ];

    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($eventParams);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class)->makePartial();
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
    $service = new Box\Mod\Client\Service();
    $eventParams = [
        'password' => 'testPassword',
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($eventParams);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('exception created in unit test'));

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $emailService);
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
        '(c.id = :client_id OR c.aid = :alt_client_id)',
        [':client_id' => 1, ':alt_client_id' => 1],
    ],
    [
        ['name' => 'test'],
        '(c.first_name LIKE :first_name or c.last_name LIKE :last_name )',
        [':first_name' => '%test%', ':last_name' => '%test%'],
    ],
    [
        ['email' => 'test@example.com'],
        'c.email LIKE :email',
        [':email' => '%test@example.com%'],
    ],
    [
        ['company' => 'LTD company'],
        'c.company LIKE :company',
        [':company' => '%LTD company%'],
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
        [':date_from' => 1355097600],
    ],
    [
        ['date_to' => '2012-12-11'],
        'UNIX_TIMESTAMP(c.created_at) <= :date_to',
        [':date_to' => 1355184000],
    ],
    [
        ['search' => '2'],
        '(c.id = :cid OR c.aid = :caid)',
        [':cid' => '2', ':caid' => '2'],
    ],
    [
        ['search' => 'Keyword'],
        "(c.company LIKE :s_company OR c.first_name LIKE :s_first_name OR c.last_name LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.first_name,  ' ', c.last_name ) LIKE  :full_name)",
        [':s_company' => '%Keyword%',
            ':s_first_name' => '%Keyword%',
            ':s_last_name' => '%Keyword%',
            ':s_email' => '%Keyword%',
            ':full_name' => '%Keyword%',
        ],
    ],
]);

test('getSearchQuery returns correct query and params', function ($data, $expectedStr, $expectedParams): void {
    $service = new Box\Mod\Client\Service();
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains((string) $result[0], (string) $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toEqual([]);
})->with('searchQueryData');

test('getSearchQuery with custom select statement', function (): void {
    $service = new Box\Mod\Client\Service();
    $data = [];
    $selectStmt = 'c.id, CONCAT(c.first_name, c.last_name) as full_name';
    $result = $service->getSearchQuery($data, $selectStmt);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains((string) $result[0], $selectStmt))->toBeTrue($result[0]);
});

test('getPairs returns array', function (): void {
    $service = new Box\Mod\Client\Service();
    $data = [];

    $di = container();

    $service->setDi($di);
    $result = $service->getPairs($data);
    expect($result)->toBeArray();
});

test('toSessionArray returns array with expected keys', function (): void {
    $service = new Box\Mod\Client\Service();
    $expectedArrayKeys = [
        'id' => 1,
        'email' => 'email@example.com',
        'name' => 'John Smith',
        'role' => 'admin',
    ];

    $model = createEntity(Box\Mod\Client\Entity\Client::class);
    $result = $service->toSessionArray($model);

    expect($result)->toBeArray();
    expect(array_diff_key($result, $expectedArrayKeys))->toEqual([]);
});

test('emailAlreadyRegistered returns boolean', function (): void {
    $service = new Box\Mod\Client\Service();
    $email = 'test@example.com';
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $di = container();

    $service->setDi($di);

    $result = $service->emailAlreadyRegistered($email);
    expect($result)->toBeBool();
});

test('emailAlreadyRegistered with model returns false for same email', function (): void {
    $service = new Box\Mod\Client\Service();
    $email = 'test@example.com';
    $model = createEntity(Box\Mod\Client\Entity\Client::class, ['email' => $email]);

    $result = $service->emailAlreadyRegistered($email, $model);
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('canChangeCurrency returns true when no invoices exist', function (): void {
    $service = new Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1, 'currency' => 'USD']);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Invoice::class)
        ->andReturnUsing(function () {
            $repo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
            $repo->shouldReceive('findOneBy')
                ->with(['clientId' => 1])
                ->andReturn(null);

            return $repo;
        });
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturnUsing(function () {
            $repo = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
            $repo->shouldReceive('findOneBy')
                ->with(['clientId' => 1])
                ->andReturn(null);

            return $repo;
        });

    $service->setDi($di);
    $result = $service->canChangeCurrency($model, $currency);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('canChangeCurrency returns true when model currency is not set', function (): void {
    $service = new Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')->never();

    $result = $service->canChangeCurrency($model, $currency);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('canChangeCurrency returns false when currencies are identical', function (): void {
    $service = new Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => $currency]);

    $database = Mockery::mock('\Box_Database');
    $database->shouldReceive('findOne')->never();

    $result = $service->canChangeCurrency($model, $currency);
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('canChangeCurrency throws exception when client has invoices', function (): void {
    $service = new Box\Mod\Client\Service();
    $currency = 'EUR';
    $model = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1, 'currency' => 'USD']);

    $di = container();
    $di['dbal']->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT 1 FROM invoice WHERE client_id = :client_id LIMIT 1', ['client_id' => 1])
        ->andReturn(1);

    $service->setDi($di);

    $service->canChangeCurrency($model, $currency);
})->throws(FOSSBilling\InformationException::class, 'Currency cannot be changed. Client already has invoices issued.');

dataset('searchBalanceQueryData', [
    [[], 'FROM client_balance as m', []],
    [
        ['id' => 1],
        'm.id = :id',
        [':id' => 1],
    ],
    [
        ['client_id' => 1],
        'm.client_id = :client_id',
        [':client_id' => 1],
    ],
    [
        ['date_from' => '2012-12-10'],
        'm.created_at >= :date_from',
        [':date_from' => 1355097600],
    ],
    [
        ['date_to' => '2012-12-11'],
        'm.created_at <= :date_to',
        [':date_to' => 1355184000],
    ],
]);

test('getBalanceSearchQuery returns correct query and params', function ($data, $expectedStr, $expectedParams): void {
    $service = new Box\Mod\Client\Service();
    $di = container();

    $clientBalanceService = new Box\Mod\Client\ServiceBalance();
    $clientBalanceService->setDi($di);
    [$sql, $params] = $clientBalanceService->getSearchQuery($data);
    expect($sql)->not->toBeEmpty();
    expect($sql)->toBeString();
    expect($params)->toBeArray();

    expect(str_contains((string) $sql, (string) $expectedStr))->toBeTrue($sql);
    expect(array_diff_key($params, $expectedParams))->toEqual([]);
})->with('searchBalanceQueryData');

test('addFunds returns true', function (): void {
    $service = new Box\Mod\Client\Service();
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $model = createEntity(Box\Mod\Client\Entity\ClientBalance::class);

    $amount = '2.22';
    $description = 'test description';

    $di = container();

    $service->setDi($di);

    $result = $service->addFunds($modelClient, $amount, $description);
    expect($result)->toBeTrue();
});

test('addFunds throws exception when currency is not defined', function (): void {
    $service = new Box\Mod\Client\Service();
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class);

    $amount = '2.22';
    $description = 'test description';

    $service->addFunds($modelClient, $amount, $description);
})->throws(FOSSBilling\Exception::class, "You must define the client's currency before adding funds.");

test('addFunds throws exception when amount is missing', function (): void {
    $service = new Box\Mod\Client\Service();
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $amount = null;
    $description = '';

    $service->addFunds($modelClient, $amount, $description);
})->throws(FOSSBilling\Exception::class, 'Funds amount is invalid');

test('addFunds throws exception when description is invalid', function (): void {
    $service = new Box\Mod\Client\Service();
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $amount = '2.22';
    $description = null;

    $service->addFunds($modelClient, $amount, $description);
})->throws(FOSSBilling\Exception::class, 'Funds description is invalid');

test('getExpiredPasswordReminders returns array', function (): void {
    $service = new Box\Mod\Client\Service();

    $di = container();

    $service->setDi($di);

    $result = $service->getExpiredPasswordReminders();
    expect($result)->toBeArray();
});

dataset('searchHistoryQueryData', [
    [[], 'SELECT ach.*, c.first_name, c.last_name, c.email', []],
    [
        ['search' => 'sameValue'],
        '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR c.email LIKE :email OR c.id LIKE :id)',
        [
            ':first_name' => '%sameValue%',
            ':last_name' => '%sameValue%',
            ':email' => '%sameValue%',
            ':id' => 'sameValue'],
    ],
    [
        ['client_id' => '1'],
        'ach.client_id = :client_id',
        [':client_id' => '1'],
    ],
]);

test('getHistorySearchQuery returns correct query and params', function ($data, $expectedStr, $expectedParams): void {
    $service = new Box\Mod\Client\Service();
    $di = container();

    $service->setDi($di);
    [$sql, $params] = $service->getHistorySearchQuery($data);
    expect($sql)->not->toBeEmpty();
    expect($sql)->toBeString();
    expect($params)->toBeArray();

    expect(str_contains((string) $sql, (string) $expectedStr))->toBeTrue($sql);
    expect(array_diff_key($params, $expectedParams))->toEqual([]);
})->with('searchHistoryQueryData');

test('counter returns array', function (): void {
    $service = new Box\Mod\Client\Service();

    $di = container();
    $di['em']->getRepository(Box\Mod\Client\Entity\Client::class)
        ->shouldReceive('getStatusCounts')
        ->once()
        ->andReturn(['active' => 0, 'suspended' => 0, 'canceled' => 0]);

    $service->setDi($di);

    $result = $service->counter();
    expect($result)->toBeArray();

    $expected = [
        'total' => 0,
        Box\Mod\Client\Entity\Client::ACTIVE => 0,
        Box\Mod\Client\Entity\Client::SUSPENDED => 0,
        Box\Mod\Client\Entity\Client::CANCELED => 0,
    ];

    expect($result)->toMatchArray($expected);
});

test('getGroupPairs returns array', function (): void {
    $service = new Box\Mod\Client\Service();

    $di = container();

    $service->setDi($di);

    $result = $service->getGroupPairs();
    expect($result)->toBeArray();
});

test('clientAlreadyExists returns true when client exists', function (): void {
    $service = new Box\Mod\Client\Service();
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $di = container();

    $service->setDi($di);

    $result = $service->clientAlreadyExists('email@example.com');
    expect($result)->toBeTrue();
});

test('getByLoginDetails returns Client', function (): void {
    $service = new Box\Mod\Client\Service();
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->with(['email' => 'email@example.com', 'pass' => 'password', 'status' => 'active'])
        ->andReturn($model);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->andReturn($clientRepoMock);

    $service->setDi($di);

    $result = $service->getByLoginDetails('email@example.com', 'password');
    expect($result)->toBeInstanceOf(Box\Mod\Client\Entity\Client::class);
});

dataset('getProvider', [
    ['id', 1],
    ['email', 'test@email.com'],
]);

test('get returns Client', function ($fieldName, $fieldValue): void {
    $service = new Box\Mod\Client\Service();

    $di = container();

    $service->setDi($di);

    $data = [$fieldName => $fieldValue];
    $result = $service->get($data);
    expect($result)->toBeInstanceOf(Box\Mod\Client\Entity\Client::class);
})->with('getProvider');

test('get throws exception when client not found', function (): void {
    $service = new Box\Mod\Client\Service();

    $repoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class)->shouldIgnoreMissing();
    $repoMock->shouldReceive('find')->andReturn(null);
    $repoMock->shouldReceive('findOneByEmail')->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($repoMock);

    $service->setDi($di);

    $data = ['id' => 0];
    $service->get($data);
})->throws(FOSSBilling\InformationException::class, 'Client not found');

test('getClientBalance returns numeric', function (): void {
    $service = new Box\Mod\Client\Service();
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $di = container();

    $service->setDi($di);

    $result = $service->getClientBalance($model);
    expect($result)->toBeNumeric();
});

test('remove wraps client cleanup and flush in one transaction', function (): void {
    $service = new Box\Mod\Client\Service();
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $legacyClient = Mockery::mock(Model_Client::class);
    $reset = createEntity(Box\Mod\Client\Entity\ClientPasswordReset::class, ['client_id' => 1]);

    $db = container()['db'];
    $db->shouldReceive('getExistingModelById')->once()->with('Client', 1)->andReturn($legacyClient);

    $services = [];
    foreach (['order', 'invoice', 'support', 'email'] as $module) {
        $moduleService = Mockery::mock();
        $moduleService->shouldReceive('rmByClient')->once()->with($legacyClient);
        $services[$module] = $moduleService;
    }

    $balanceService = Mockery::mock();
    $balanceService->shouldReceive('rmByClient')->once()->with($client);
    $services['client:balance'] = $balanceService;

    $activityService = Mockery::mock();
    $activityService->shouldReceive('rmByClient')->once()->with($client);
    $services['activity'] = $activityService;

    $di = container();
    $di['db'] = $db;
    $di['mod_service'] = $di->protect(moduleService($services));

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $query = Mockery::mock(Doctrine\DBAL\Query\QueryBuilder::class);
    $query->shouldReceive('delete')->once()->with('extension_meta')->andReturnSelf();
    $query->shouldReceive('where')->once()->with('client_id = :id')->andReturnSelf();
    $query->shouldReceive('setParameter')->once()->with('id', 1)->andReturnSelf();
    $query->shouldReceive('executeStatement')->once()->andReturn(1);
    $connection->shouldReceive('executeStatement')->once()
        ->with('DELETE FROM activity_client_history WHERE client_id = :id', ['id' => 1])
        ->andReturn(1);
    $connection->shouldReceive('createQueryBuilder')->once()->andReturn($query);

    $passwordRepository = Mockery::mock(Box\Mod\Client\Repository\ClientPasswordResetRepository::class);
    $passwordRepository->shouldReceive('findBy')->once()->with(['clientId' => 1])->andReturn([$reset]);

    $em = $di['em'];
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\ClientPasswordReset::class)->andReturn($passwordRepository);
    $em->shouldReceive('getConnection')->once()->andReturn($connection);
    $em->shouldReceive('beginTransaction')->once();
    $em->shouldReceive('remove')->once()->with($reset);
    $em->shouldReceive('remove')->once()->with($client);
    $em->shouldReceive('flush')->once();
    $em->shouldReceive('commit')->once();

    $service->setDi($di);
    $service->remove($client);
});

test('remove rolls back and rethrows cleanup failures', function (): void {
    $service = new Box\Mod\Client\Service();
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $legacyClient = Mockery::mock(Model_Client::class);
    $exception = new RuntimeException('cleanup failed');

    $db = container()['db'];
    $db->shouldReceive('getExistingModelById')->once()->with('Client', 1)->andReturn($legacyClient);

    $orderService = Mockery::mock();
    $orderService->shouldReceive('rmByClient')->once()->with($legacyClient)->andThrow($exception);

    $di = container();
    $di['db'] = $db;
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderService]));

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->once()->andReturnTrue();

    $em = $di['em'];
    $em->shouldReceive('getConnection')->once()->andReturn($connection);
    $em->shouldReceive('beginTransaction')->once();
    $em->shouldReceive('rollback')->once();
    $em->shouldReceive('commit')->never();

    $service->setDi($di);

    expect(fn () => $service->remove($client))->toThrow($exception);
});

test('toApiArray returns array', function (): void {
    $service = new Box\Mod\Client\Service();
    $model = createEntity(Box\Mod\Client\Entity\Client::class, [
        'client_group_id' => 1,
        'custom_1' => 'custom field',
        'billing_email' => 'billing@example.com',
    ]);

    $clientGroup = createEntity(Box\Mod\Client\Entity\ClientGroup::class, ['id' => 1, 'title' => 'Group Title']);

    $di = container();
    $clientGroupRepository = Mockery::mock(Box\Mod\Client\Repository\ClientGroupRepository::class);
    $clientGroupRepository->shouldReceive('find')->with(1)->andReturn($clientGroup);
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\ClientGroup::class)
        ->andReturn($clientGroupRepository);

    $service->setDi($di);

    $result = $service->toApiArray($model, true, createEntity(Box\Mod\Staff\Entity\Admin::class));
    expect($result)->toBeArray();
    expect($result['billing_email'])->toBe('billing@example.com');
    expect($result['group'])->toBe('Group Title');
    expect($result['client_group'])->toMatchArray(['id' => 1, 'title' => 'Group Title']);

    $publicResult = $service->toApiArray($model);
    expect($publicResult)->not->toHaveKey('billing_email');
});

test('toApiArray includes custom fields beyond the original cap of 10', function (): void {
    $service = new Box\Mod\Client\Service();
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $clientGroup = createEntity(Box\Mod\Client\Entity\ClientGroup::class, ['title' => 'Group Title']);

    $di = container();

    $service->setDi($di);

    $result = $service->toApiArray($model, true, createEntity(Box\Mod\Staff\Entity\Admin::class));
    expect($result)->toBeArray();
    expect($result['custom_1'])->toBeNull();
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

test('isClientTaxable returns correct value', function ($getParamValueReturn, $tax_exempt, $expected): void {
    $service = new Box\Mod\Client\Service();
    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn($getParamValueReturn);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $service->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['tax_exempt' => $tax_exempt]);

    $result = $service->isClientTaxable($client);
    expect($result)->toEqual($expected);
})->with('isClientTaxableProvider');

test('adminCreateClient returns int', function (): void {
    $service = new Box\Mod\Client\Service();

    $data = [
        'password' => uniqid(),
        'email' => 'test@unit.vm',
        'first_name' => 'test',
        'aid' => 'LEGACY-1001',
    ];

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->twice();

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->atLeast()->once()
        ->with($data['password']);

    $modMock = Mockery::mock(FOSSBilling\Module::class)->makePartial();
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['password'] = $passwordMock;

    $service->setDi($di);

    $result = $service->adminCreateClient($data);
    expect($result)->toBeInt();
});

test('deleteGroup returns true', function (): void {
    $service = new Box\Mod\Client\Service();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $model = createEntity(Box\Mod\Client\Entity\ClientGroup::class);
    $result = $service->deleteGroup($model);
    expect($result)->toBeTrue();
});

test('deleteGroup throws exception when group has clients', function (): void {
    $service = new Box\Mod\Client\Service();
    $clientEntity = new Box\Mod\Client\Entity\Client();

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('findOneBy')
        ->with(['clientGroupId' => 1])
        ->andReturn($clientEntity);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->andReturn($clientRepoMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $model = createEntity(Box\Mod\Client\Entity\ClientGroup::class, ['id' => 1]);

    $service->deleteGroup($model);
})->throws(FOSSBilling\Exception::class, 'Cannot remove groups with clients');

test('authorizeClient returns null when email not found', function (): void {
    $service = new Box\Mod\Client\Service();
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')
        ->atLeast()->once()
        ->with(null, $password)
        ->andReturn(null);

    $di = container();
    $di['auth'] = $authMock;

    $service->setDi($di);

    $result = $service->authorizeClient($email, $password);
    expect($result)->toBeNull();
});

test('authorizeClient returns Client', function (): void {
    $service = new Box\Mod\Client\Service();
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->with(['email' => $email, 'status' => 'active'])
        ->andReturn($clientModel);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')
        ->atLeast()->once()
        ->with($clientModel, $password)
        ->andReturn($clientModel);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->andReturn($clientRepoMock);
    $di['auth'] = $authMock;
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => false]);

    $service->setDi($di);

    $result = $service->authorizeClient($email, $password);
    expect($result)->toBeInstanceOf(Box\Mod\Client\Entity\Client::class);
});

test('authorizeClient with confirmed email returns Client', function (): void {
    $service = new Box\Mod\Client\Service();
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['email_approved' => 1]);

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->with(['email' => $email, 'status' => 'active'])
        ->andReturn($clientModel);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')
        ->atLeast()->once()
        ->with($clientModel, $password)
        ->andReturn($clientModel);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->andReturn($clientRepoMock);
    $di['auth'] = $authMock;
    $di['mod_config'] = $di->protect(fn ($name): array => ['require_email_confirmation' => true]);

    $service->setDi($di);

    $result = $service->authorizeClient($email, $password);
    expect($result)->toBeInstanceOf(Box\Mod\Client\Entity\Client::class);
});

test('canChangeEmail returns true', function (): void {
    $service = new Box\Mod\Client\Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);
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
    $service = new Box\Mod\Client\Service();
    $email = 'client@fossbilling.org';
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['email' => $email]);

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
    $service = new Box\Mod\Client\Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);
    $email = 'client@fossbilling.org';

    $config = [];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $service->setDi($di);

    $result = $service->canChangeEmail($clientModel, $email);
    expect($result)->toBeTrue();
});

test('canChangeEmail throws exception when email change is disabled', function (): void {
    $service = new Box\Mod\Client\Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);
    $email = 'client@fossbilling.org';

    $config = [
        'disable_change_email' => true,
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);
    $service->setDi($di);

    $service->canChangeEmail($clientModel, $email);
})->throws(FOSSBilling\Exception::class, 'Email address cannot be changed');

test('checkExtraRequiredFields throws exception for missing field', function (): void {
    $service = new Box\Mod\Client\Service();
    $required = ['id'];
    $data = [];

    $config['required'] = $required;
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $service->setDi($di);
    $service->checkExtraRequiredFields($data);
})->throws(FOSSBilling\Exception::class, 'Field Id cannot be empty');

test('checkCustomFields throws exception for required field', function (): void {
    $service = new Box\Mod\Client\Service();
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
})->throws(FOSSBilling\Exception::class, 'Field custom_field_title cannot be empty');

test('checkCustomFields returns null when field is not required', function (): void {
    $service = new Box\Mod\Client\Service();
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

test('resolveDocumentNumber returns first active custom field matching a document keyword', function (): void {
    $service = new Box\Mod\Client\Service();
    $config = [
        'custom_fields' => [
            'custom_1' => ['active' => true, 'title' => 'Identity Card'],
            'custom_2' => ['active' => true, 'title' => 'Passport Number'],
            'custom_3' => ['active' => true, 'title' => 'VAT Number'],
        ],
    ];
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['custom_1' => 'ID-1', 'custom_2' => 'P-2', 'custom_3' => 'VAT-3']);

    $service->setDi($di);
    expect($service->resolveDocumentNumber($client))->toBe('ID-1');
});

test('resolveDocumentNumber returns null when no custom field is active or matches a keyword', function (): void {
    $service = new Box\Mod\Client\Service();
    $config = [
        'custom_fields' => [
            'custom_1' => ['active' => true, 'title' => 'VAT Number'],
            'custom_2' => ['active' => false, 'title' => 'Passport Number'],
        ],
    ];
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['custom_1' => 'VAT-1', 'custom_2' => 'P-2']);

    $service->setDi($di);
    expect($service->resolveDocumentNumber($client))->toBeNull();
});

test('resolveDocumentNumber returns null when matching custom field value is empty', function (): void {
    $service = new Box\Mod\Client\Service();
    $config = [
        'custom_fields' => [
            'custom_1' => ['active' => true, 'title' => 'Document Number'],
        ],
    ];
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['custom_1' => null]);

    $service->setDi($di);
    expect($service->resolveDocumentNumber($client))->toBeNull();
});

test('resolveDocumentNumber matches a custom field beyond the original cap of 10', function (): void {
    $service = new Box\Mod\Client\Service();
    $config = [
        'custom_fields' => [
            'custom_15' => ['active' => true, 'title' => 'Passport Number'],
        ],
    ];
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => $config);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['custom_15' => 'P-15']);

    $service->setDi($di);
    expect($service->resolveDocumentNumber($client))->toBe('P-15');
});

test('resolveDocumentNumber returns null when no custom_fields config exists', function (): void {
    $service = new Box\Mod\Client\Service();
    $di = container();
    $di['mod_config'] = $di->protect(fn ($modName): array => []);

    $client = createEntity(Box\Mod\Client\Entity\Client::class);

    $service->setDi($di);
    expect($service->resolveDocumentNumber($client))->toBeNull();
});

test('i18n::validateTimezone returns null for null and empty input', function (): void {
    expect(FOSSBilling\i18n::validateTimezone(null))->toBeNull();
    expect(FOSSBilling\i18n::validateTimezone(''))->toBeNull();
});

test('i18n::validateTimezone returns the value when it is a known IANA identifier', function (): void {
    expect(FOSSBilling\i18n::validateTimezone('America/New_York'))->toBe('America/New_York');
    expect(FOSSBilling\i18n::validateTimezone('Europe/Berlin'))->toBe('Europe/Berlin');
    expect(FOSSBilling\i18n::validateTimezone('UTC'))->toBe('UTC');
});

test('i18n::validateTimezone throws InformationException for an unknown identifier', function (): void {
    expect(fn (): ?string => FOSSBilling\i18n::validateTimezone('Mars/Olympus_Mons'))->toThrow(FOSSBilling\InformationException::class);
});
