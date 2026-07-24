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
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $di = container();
    $adminClient->setDi($di);
    $getDi = $adminClient->getDi();
    expect($getDi)->toEqual($di);
});

test('getList returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $identity = new Model_Admin();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $adminClient->setIdentity($identity);
    $queryBuilder = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $simpleResultArr = [
        'list' => [
            ['id' => 1, 'group' => null],
        ],
    ];

    $repository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $repository->shouldReceive('getSearchQueryBuilder')
        ->once()
        ->with([])
        ->andReturn($queryBuilder);
    $repository->shouldReceive('getListContext')
        ->once()
        ->with([1])
        ->andReturn([1 => ['balance' => 10.0, 'group' => 'VIP']]);

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('getClientRepository')
    ->once()
    ->andReturn($repository);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();

    $pagerMock
    ->shouldReceive('paginateDoctrineQuery')
    ->once()
    ->with($queryBuilder, Mockery::type(FOSSBilling\PaginationOptions::class), Mockery::type(Model_Admin::class))
    ->andReturn($simpleResultArr);

    $di = container();
    $di['pager'] = $pagerMock;

    $adminClient->setService($serviceMock);
    $adminClient->setDi($di);
    $data = [];

    $result = $adminClient->get_list($data);
    expect($result['list'])->toBe([['id' => 1, 'group' => 'VIP', 'balance' => 10.0]]);
});

test('getPairs returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('getPairs')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));

    $adminClient->setDi($di);

    $data = ['id' => 1];
    $result = $adminClient->get_pairs($data);
    expect($result)->toBeArray();
});

test('get returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('get')->atLeast()->once()->andReturn($model);
    $serviceMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $adminClient->setService($serviceMock);

    $result = $adminClient->get([]);
    expect($result)->toBeArray();
});

test('login returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());

    $sessionArray = [
        'id' => 1,
        'email' => 'email@example.com',
        'name' => 'John Smith',
        'role' => 'client',
    ];
    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('toSessionArray')->atLeast()->once()->andReturn($sessionArray);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('set')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));
    $di['session'] = $sessionMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);

    $data = ['id' => 1];
    $result = $adminClient->login($data);
    expect($result)->toBeArray();
});

test('create returns int', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'email' => 'email@example.com',
        'first_name' => 'John', 'password' => 'StrongPass123',
    ];

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('emailAlreadyRegistered')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('adminCreateClient')->atLeast()->once()->andReturn(1);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['tools'] = $toolsMock;

    $adminClient->setDi($di);
    $adminClient->setService($serviceMock);

    $result = $adminClient->create($data);

    expect($result)->toBeInt();
});

test('create throws exception when email is already registered', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'email' => 'email@example.com',
        'first_name' => 'John', 'password' => 'StrongPass123',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('emailAlreadyRegistered')->atLeast()->once()->andReturn(true);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();

    $di = container();
    $di['tools'] = $toolsMock;

    $adminClient->setDi($di);
    $adminClient->setService($serviceMock);

    $adminClient->create($data);
})->throws(FOSSBilling\Exception::class, 'This email address is already registered.');

test('delete returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = ['id' => 1];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class)->makePartial();
    $serviceMock->shouldReceive('remove')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);
    $adminClient->setService($serviceMock);
    $result = $adminClient->delete($data);
    expect($result)->toBeTrue();
});

test('update returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'id' => 1,
        'first_name' => 'John', 'password' => 'StrongPass123',
        'last_name' => 'Smith',
        'aid' => '0',
        'gender' => 'male',
        'birthday' => '1999-01-01',
        'company' => 'LTD Testing',
        'company_vat' => 'VAT0007',
        'address_1' => 'United States',
        'address_2' => 'Utah',
        'phone_cc' => '+1',
        'phone' => '555-345-345',
        'notes' => 'none',
        'country' => 'US',
        'postcode' => 'IL-11123',
        'city' => 'Chicago',
        'state' => 'IL',
        'currency' => 'USD',
        'tax_exempt' => 'N/A',
        'created_at' => '2012-05-10',
        'email' => 'test@example.com',
        'group_id' => 1,
        'status' => 'test status',
        'company_number' => '1234',
        'type' => '',
        'lang' => 'en',
        'custom_1' => '',
        'custom_2' => '',
        'custom_3' => '',
        'custom_4' => '',
        'custom_5' => '',
        'custom_6' => '',
        'custom_7' => '',
        'custom_8' => '',
        'custom_9' => '',
        'custom_10' => '',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('emailAlreadyRegistered')->atLeast()->once()->andReturn(false);
    $serviceMock->shouldReceive('canChangeCurrency')->atLeast()->once()->andReturn(true);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['tools'] = $toolsMock;

    $adminClient->setDi($di);
    $result = $adminClient->update($data);
    expect($result)->toBeTrue();
});

test('update validates and assigns client_group_id through the group repository', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1, 'client_group_id' => 3]);
    $group = createEntity(Box\Mod\Client\Entity\ClientGroup::class, ['id' => 7]);

    $clientRepository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepository->shouldReceive('find')->once()->with(1)->andReturn($client);
    $groupRepository = Mockery::mock(Box\Mod\Client\Repository\ClientGroupRepository::class);
    $groupRepository->shouldReceive('find')->once()->with(7)->andReturn($group);

    $di = container();
    $em = $di['em'];
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepository);
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\ClientGroup::class)->andReturn($groupRepository);
    $em->shouldReceive('persist')->once()->with($client);
    $em->shouldReceive('flush')->once();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(moduleService(['client' => Mockery::mock(Box\Mod\Client\Service::class)]));

    $adminClient->setDi($di);

    expect($adminClient->update(['id' => 1, 'client_group_id' => '7']))->toBeTrue()
        ->and($client->getClientGroupId())->toBe(7);
});

test('update clears client_group_id when the alias is empty', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1, 'client_group_id' => 3]);

    $clientRepository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepository->shouldReceive('find')->once()->with(1)->andReturn($client);

    $di = container();
    $em = $di['em'];
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepository);
    $em->shouldReceive('persist')->once()->with($client);
    $em->shouldReceive('flush')->once();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(moduleService(['client' => Mockery::mock(Box\Mod\Client\Service::class)]));

    $adminClient->setDi($di);

    expect($adminClient->update(['id' => 1, 'client_group_id' => '']))->toBeTrue()
        ->and($client->getClientGroupId())->toBeNull();
});

test('update rejects a non-integer client_group_id alias', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientRepository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepository->shouldReceive('find')->once()->with(1)->andReturn($client);

    $di = container();
    $em = $di['em'];
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepository);
    $di['mod_service'] = $di->protect(moduleService(['client' => Mockery::mock(Box\Mod\Client\Service::class)]));

    $adminClient->setDi($di);

    expect(fn () => $adminClient->update(['id' => 1, 'client_group_id' => 'invalid']))
        ->toThrow(FOSSBilling\InformationException::class, 'Invalid client group ID');
});

test('update throws exception when email is already registered', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'id' => 1,
        'first_name' => 'John', 'password' => 'StrongPass123',
        'last_name' => 'Smith',
        'aid' => '0',
        'gender' => 'male',
        'birthday' => '1999-01-01',
        'company' => 'LTD Testing',
        'company_vat' => 'VAT0007',
        'address_1' => 'United States',
        'address_2' => 'Utah',
        'phone_cc' => '+1',
        'phone' => '555-345-345',
        'document_type' => 'doc',
        'document_nr' => '1',
        'notes' => 'none',
        'country' => 'Moon',
        'postcode' => 'IL-11123',
        'city' => 'Chicago',
        'state' => 'IL',
        'currency' => 'USD',
        'tax_exempt' => 'N/A',
        'created_at' => '2012-05-10',
        'email' => 'test@example.com',
        'group_id' => 1,
        'status' => 'test status',
        'company_number' => '1234',
        'type' => '',
        'lang' => 'en',
        'custom_1' => '',
        'custom_2' => '',
        'custom_3' => '',
        'custom_4' => '',
        'custom_5' => '',
        'custom_6' => '',
        'custom_7' => '',
        'custom_8' => '',
        'custom_9' => '',
        'custom_10' => '',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('emailAlreadyRegistered')->atLeast()->once()->andReturn(true);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['validator'] = new FOSSBilling\Validate();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;

    $adminClient->setDi($di);

    $adminClient->update($data);
})->throws(FOSSBilling\Exception::class, 'This email address is already registered.');

test('update throws exception when id is not passed', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [];

    $di = container();

    $di['validator'] = new FOSSBilling\Validate();
    $adminClient->setDi($di);

    // Validate required parameters before calling update
    $validator = $di['validator'];
    $validator->checkRequiredParamsForArray(['id' => 'Client ID was not passed'], $data);

    $adminClient->update($data);
})->throws(FOSSBilling\Exception::class, 'Client ID was not passed');

test('changePassword returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'id' => 1,
        'password' => 'strongPass',
        'password_confirm' => 'strongPass',
    ];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once()->with($data['password']);

    $profileService = Mockery::mock(Box\Mod\Profile\Service::class);
    $profileService->shouldReceive('invalidateSessions')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = $passwordMock;
    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;
    $di['mod_service'] = $di->protect(moduleService(['profile' => $profileService]));

    $adminClient->setDi($di);

    $result = $adminClient->change_password($data);
    expect($result)->toBeTrue();
});

test('changePassword throws exception when passwords do not match', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'id' => 1,
        'password' => 'strongPass',
        'password_confirm' => 'NotIdentical',
    ];

    $validatorStub = new FOSSBilling\Validate();

    $di = container();
    $di['validator'] = $validatorStub;
    $di['mod_service'] = $di->protect(moduleService());
    $adminClient->setDi($di);

    $adminClient->change_password($data);
})->throws(FOSSBilling\Exception::class, 'Passwords do not match');

test('balanceGetList returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $simpleResultArr = [
        'list' => [
            [
                'id' => 1,
                'description' => 'Testing',
                'amount' => '1.00',
                'currency' => 'USD',
                'created_at' => date('Y:m:d H:i:s'),
            ],
        ],
    ];

    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\Client\ServiceBalance::class);
    $serviceMock
    ->shouldReceive('getSearchQuery')
    ->atLeast()->once()
    ->andReturn(['String', []]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();

    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($simpleResultArr);

    $model = createEntity(Box\Mod\Client\Entity\ClientBalance::class);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client:balance' => $serviceMock, 'client' => $serviceMock]));
    $di['pager'] = $pagerMock;

    $adminClient->setDi($di);

    $result = $adminClient->balance_get_list($data);
    expect($result)->toBeArray();
});

test('balanceDelete returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'id' => 1,
    ];

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);

    $result = $adminClient->balance_delete($data);
    expect($result)->toBeTrue();
});

test('balanceAddFunds returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [
        'id' => 1,
        'amount' => '1.00',
        'description' => 'testDescription',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('addFunds')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));

    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);

    $result = $adminClient->balance_add_funds($data);
    expect($result)->toBeTrue();
});

test('batchExpirePasswordReminders returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $expiredArr = [
        createEntity(Box\Mod\Client\Entity\ClientPasswordReset::class),
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('getExpiredPasswordReminders')->atLeast()->once()->andReturn($expiredArr);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));
    $di['logger'] = new Tests\Helpers\TestLogger();

    $adminClient->setDi($di);

    $result = $adminClient->batch_expire_password_reminders();
    expect($result)->toBeTrue();
});

test('loginHistoryGetList returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data = [];
    $pagerResultSet = [
        'list' => [],
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('getHistorySearchQuery')
    ->atLeast()->once()
    ->andReturn(['String', []]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();

    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($pagerResultSet);

    $di = container();
    $di['pager'] = $pagerMock;

    $adminClient->setDi($di);
    $adminClient->setService($serviceMock);

    $result = $adminClient->login_history_get_list($data);
    expect($result)->toBeArray();
});

test('getStatuses returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('counter')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));

    $adminClient->setDi($di);

    $result = $adminClient->get_statuses([]);
    expect($result)->toBeArray();
});

test('groupGetPairs returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('getGroupPairs')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));

    $adminClient->setDi($di);

    $result = $adminClient->group_get_pairs([]);
    expect($result)->toBeArray();
});

test('groupCreate returns int', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data['title'] = 'test Group';

    $newGroupId = 1;
    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('createGroup')->atLeast()->once()->andReturn($newGroupId);

    $di = container();
    $adminClient->setService($serviceMock);
    $adminClient->setDi($di);
    $result = $adminClient->group_create($data);

    expect($result)->toBeInt();
    expect($result)->toEqual($newGroupId);
});

test('groupUpdate returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data['id'] = '2';
    $data['title'] = 'test Group updated';

    $di = container();

    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);

    $result = $adminClient->group_update($data);

    expect($result)->toBeTrue();
});

test('groupDelete returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data['id'] = '2';

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class)->makePartial();
    $serviceMock
    ->shouldReceive('deleteGroup')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);
    $adminClient->setService($serviceMock);

    $result = $adminClient->group_delete($data);

    expect($result)->toBeTrue();
});

test('groupGet returns array', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $data['id'] = '2';

    $di = container();
    $validatorStub = $this->createStub(FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $adminClient->setDi($di);

    $result = $adminClient->group_get($data);

    expect($result)->toBeArray();
});

test('batchDelete returns true', function (): void {
    $adminClient = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $activityMock = Mockery::mock(Box\Mod\Client\Api\Admin::class)->makePartial();
    $activityMock->shouldReceive('delete')->atLeast()->once()->andReturn(true);

    $validatorStub = $this->createStub(FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorStub;
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});
