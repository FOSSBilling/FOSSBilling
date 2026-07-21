<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminGroup;
use Box\Mod\Staff\Entity\AdminGroupMember;
use Box\Mod\Staff\Repository\AdminGroupMemberRepository;
use Box\Mod\Staff\Repository\AdminGroupRepository;
use Box\Mod\Staff\Repository\AdminRepository;
use Box\Mod\Staff\Service;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Repository\HelpdeskRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

class StaffPdoMock extends PDO
{
    public function __construct()
    {
    }
}

class StaffPdoStatementMock extends PDOStatement
{
    public function __construct()
    {
    }
}

function staffServiceWithGroupPermissions(array $groups = [], bool $isSuperAdministrator = false, array $modulePermissions = []): Service
{
    $adminRepository = Mockery::mock(AdminRepository::class)->shouldIgnoreMissing();
    $groupRepository = Mockery::mock(AdminGroupRepository::class)->shouldIgnoreMissing();
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->andReturn($isSuperAdministrator);
    $groupMemberRepository->shouldReceive('findGroupsForAdmin')->andReturn($groups);
    $groupMemberRepository->shouldReceive('getPermissionsForAdmin')->andReturnUsing(function () use ($groups): array {
        $permissions = [];
        foreach ($groups as $group) {
            foreach ($group->getPermissions() as $module => $modulePermissions) {
                $permissions[$module] ??= [];
                foreach ($modulePermissions as $key => $value) {
                    $permissions[$module][$key] = !empty($permissions[$module][$key]) || !empty($value);
                }
            }
        }

        return $permissions;
    });

    $extensionServiceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $extensionServiceMock->shouldReceive('getSpecificModulePermissions')->andReturn($modulePermissions);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository, $adminRepository);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $extensionServiceMock);

    $service = new Service();
    $service->setDi($di);

    return $service;
}

function staffSetEntityId(object $entity, int $id): void
{
    $property = new ReflectionProperty($entity, 'id');
    $property->setValue($entity, $id);
}

function staffHierarchyBypassAdmin(): Admin
{
    $admin = createEntity(Admin::class, ['id' => 99, 'system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON]);

    return $admin;
}

function staffRegularAdmin(): Admin
{
    $admin = createEntity(Admin::class, ['id' => 10]);

    return $admin;
}

function staffEntityManager(?object $groupRepository = null, ?object $groupMemberRepository = null, ?object $adminRepository = null): object
{
    $adminRepository ??= Mockery::mock(AdminRepository::class)->shouldIgnoreMissing();
    $groupRepository ??= Mockery::mock(AdminGroupRepository::class)->shouldIgnoreMissing();
    $groupMemberRepository ??= Mockery::mock(AdminGroupMemberRepository::class)->shouldIgnoreMissing();

    return new class($groupRepository, $groupMemberRepository, $adminRepository) {
        public array $persisted = [];
        public array $removed = [];

        public function __construct(
            private readonly object $groupRepository,
            private readonly object $groupMemberRepository,
            private readonly object $adminRepository,
        ) {
        }

        public function getRepository(string $class): object
        {
            return match ($class) {
                Admin::class => $this->adminRepository,
                AdminGroup::class => $this->groupRepository,
                default => $this->groupMemberRepository,
            };
        }

        public function persist(object $entity): void
        {
            if ($entity instanceof AdminGroup && $entity->getId() === null) {
                staffSetEntityId($entity, 1);
            }
            if ($entity instanceof Admin && $entity->getId() === null) {
                staffSetEntityId($entity, 1);
            }

            $this->persisted[] = $entity;
        }

        public function remove(object $entity): void
        {
            $this->removed[] = $entity;
        }

        public function flush(): void
        {
        }
    };
}

test('login returns admin details on successful login', function (): void {
    $email = 'email@domain.com';
    $password = 'pass';
    $ip = '127.0.0.1';

    $admin = createEntity(Admin::class, ['id' => 1, 'email' => $email, 'name' => 'Admin']);

    $adminRepoMock = Mockery::mock(Box\Mod\Staff\Repository\AdminRepository::class);
    $adminRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['email' => $email, 'status' => Admin::STATUS_ACTIVE])
        ->andReturn($admin);

    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')->atLeast()->once()
        ->andReturn(true);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('regenerateId')->atLeast()->once();
    $sessionMock->shouldReceive('set')->atLeast()->once();

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')->atLeast()->once()
        ->with($admin, $password)
        ->andReturn($admin);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Admin::class)
        ->andReturn($adminRepoMock);
    $di['events_manager'] = $emMock;
    $di['session'] = $sessionMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->login($email, $password, $ip);

    $expected = [
        'id' => 1,
        'email' => $email,
        'name' => 'Admin',
    ];

    expect($result)->toBe($expected);
});

test('login throws exception when credentials are invalid', function (): void {
    $email = 'email@domain.com';
    $password = 'pass';
    $ip = '127.0.0.1';

    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')->atLeast()->once()
        ->andReturn(true);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')->atLeast()->once()
        ->with(null, $password)
        ->andReturn(null);

    $di = container();
    $di['events_manager'] = $emMock;
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    expect(fn (): array => $service->login($email, $password, $ip))
        ->toThrow(FOSSBilling\Exception::class, 'Check your login details');
});

test('hasPermission returns true for super administrator group member', function (): void {
    $member = createEntity(Admin::class, ['id' => 1]);

    $service = staffServiceWithGroupPermissions(isSuperAdministrator: true);

    $result = $service->hasPermission($member, 'example');
    expect($result)->toBeTrue();
});

test('hasPermission does not allow staff without group permissions', function (): void {
    $member = createEntity(Admin::class, ['id' => 1]);

    $service = staffServiceWithGroupPermissions();

    $result = $service->hasPermission($member, 'example');
    expect($result)->toBeFalse();
});

test('hasPermission falls back to cron admin only within cron context', function (): void {
    $cronAdmin = createEntity(Admin::class, ['system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON]);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getCronAdmin')
        ->once()
        ->andReturn($cronAdmin);

    $auth = Mockery::mock(Box_Authorization::class);
    $auth->shouldReceive('isAdminLoggedIn')
        ->once()
        ->andReturn(false);

    $di = container();
    $di['auth'] = $auth;
    $di['is_cron'] = true;
    $service->setDi($di);

    expect($service->hasPermission(null, 'order'))->toBeTrue();
});

test('hasPermission stays fail-closed outside cron context when no admin is logged in', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldNotReceive('getCronAdmin');

    $auth = Mockery::mock(Box_Authorization::class);
    $auth->shouldReceive('isAdminLoggedIn')
        ->andReturn(false);

    $di = container();
    $di['auth'] = $auth;
    $di['loggedin_admin'] = function (): never {
        throw new FOSSBilling\Security\AuthenticationRequiredException('admin');
    };
    $service->setDi($di);

    expect(fn () => $service->hasPermission(null, 'order'))
        ->toThrow(FOSSBilling\Security\AuthenticationRequiredException::class);
});

test('hasPermission returns false for staff without groups', function (): void {
    $member = createEntity(Admin::class, ['id' => 1]);

    $service = staffServiceWithGroupPermissions();

    $result = $service->hasPermission($member, 'example');
    expect($result)->toBeFalse();
});

test('hasPermission returns true for staff with group permission', function (): void {
    $member = createEntity(Admin::class, ['id' => 1]);

    $group = (new AdminGroup())->setPermissions([
        'example' => [
            'access' => true,
            'get_list' => true,
        ],
    ]);

    $service = staffServiceWithGroupPermissions([$group]);

    $result = $service->hasPermission($member, 'example', 'get_list');
    expect($result)->toBeTrue();
});

test('hasPermission returns false for staff without method permission', function (): void {
    $member = createEntity(Admin::class, ['id' => 1]);

    $group = (new AdminGroup())->setPermissions([
        'example' => [
            'access' => true,
        ],
    ]);

    $service = staffServiceWithGroupPermissions([$group]);

    $result = $service->hasPermission($member, 'example', 'get_list');
    expect($result)->toBeFalse();
});

test('onAfterClientReplyTicket sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');
    $ticketId = 42;
    $clientId = 7;
    $ticketModel = (new Box\Mod\Support\Entity\SupportTicket())
        ->setClientId($clientId)
        ->setPriority(25);
    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, [
        'id' => $clientId,
        'email' => 'client@example.com',
        'firstName' => 'Example',
        'lastName' => 'Client',
    ]);
    $clientDetails = [
        'id' => $clientId,
        'email' => 'client@example.com',
        'first_name' => 'Example',
        'last_name' => 'Client',
    ];

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->once()
        ->with($ticketId)
        ->andReturn($ticketModel);
    $supportServiceMock->shouldReceive('toApiArray')->once()
        ->with($ticketModel, true)
        ->andReturn(['subject' => 'Example ticket']);

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('get')->once()
        ->with(['id' => $clientId])
        ->andReturn($clientModel);
    $clientServiceMock->shouldReceive('toApiArray')->once()
        ->with($clientModel)
        ->andReturn($clientDetails);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()
        ->with([
            'to_staff' => true,
            'code' => 'mod_staff_ticket_reply',
            'ticket' => [
                'subject' => 'Example ticket',
                'priority' => 25,
                'client' => $clientDetails,
            ],
        ]);

    $eventMock->shouldReceive('getParameters')->once()
        ->andReturn(['id' => $ticketId]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $clientServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'client') {
            return $clientServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientReplyTicket($eventMock);
});

test('onAfterClientReplyTicket still sends when its client no longer exists', function (): void {
    $eventMock = Mockery::mock('\\Box_Event');
    $ticketId = 42;
    $clientId = 7;
    $ticketModel = (new Box\Mod\Support\Entity\SupportTicket())
        ->setClientId($clientId)
        ->setPriority(25);

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->once()
        ->with($ticketId)
        ->andReturn($ticketModel);
    $supportServiceMock->shouldReceive('toApiArray')->once()
        ->with($ticketModel, true)
        ->andReturn(['subject' => 'Example ticket']);

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('get')->once()
        ->with(['id' => $clientId])
        ->andThrow(new FOSSBilling\InformationException('Client not found'));

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()
        ->with([
            'to_staff' => true,
            'code' => 'mod_staff_ticket_reply',
            'ticket' => [
                'subject' => 'Example ticket',
                'priority' => 25,
            ],
        ]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => match ($name) {
        'support' => $supportServiceMock,
        'client' => $clientServiceMock,
        'email' => $emailServiceMock,
    });

    $eventMock->shouldReceive('getParameters')->once()->andReturn(['id' => $ticketId]);
    $eventMock->shouldReceive('getDi')->once()->andReturn($di);

    Service::onAfterClientReplyTicket($eventMock);
});

test('onAfterClientReplyTicket handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->andThrow(new Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientReplyTicket($eventMock);
});

test('onAfterClientCloseTicket sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_staff_ticket_close'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientCloseTicket($eventMock);
});

test('onAfterClientCloseTicket handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->andThrow(new Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientCloseTicket($eventMock);
});

test('onAfterClientOpenTicket sends guest email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_staff_ticket_open'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientOpenTicket($eventMock);
});

test('onAfterClientOpenTicket handles guest email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->andThrow(new Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientOpenTicket($eventMock);
});

test('onAfterClientReplyTicket sends guest email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_staff_ticket_reply'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientReplyTicket($eventMock);
});

test('onAfterClientReplyTicket handles guest email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->andThrow(new Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientReplyTicket($eventMock);
});

test('onAfterClientSignUp sends sanitized client details in the email variables', function (): void {
    $eventMock = Mockery::mock('\Box_Event');
    $clientId = 42;
    $client = createEntity(Client::class);
    $clientDetails = [
        'id' => $clientId,
        'email' => 'new-client@example.com',
        'first_name' => 'New',
        'last_name' => 'Client',
    ];

    $clientMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientMock->shouldReceive('get')->once()
        ->with(['id' => $clientId])
        ->andReturn($client);
    $clientMock->shouldReceive('toApiArray')->once()
        ->with($client)
        ->andReturn($clientDetails);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()
        ->with([
            'to_staff' => true,
            'code' => 'mod_staff_client_signup',
            'c' => $clientDetails,
        ]);

    $eventMock->shouldReceive('getParameters')->once()
        ->andReturn(['id' => $clientId]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($clientMock, $emailServiceMock) {
        if ($name == 'client') {
            return $clientMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientSignUp($eventMock);
});

test('onAfterClientSignUp handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');
    $clientId = 42;
    $client = createEntity(Client::class);

    $clientMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientMock->shouldReceive('get')->once()
        ->with(['id' => $clientId])
        ->andReturn($client);
    $clientMock->shouldReceive('toApiArray')->once()
        ->with($client)
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()
        ->andThrow(new Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive('getParameters')->once()
        ->andReturn(['id' => $clientId]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($clientMock, $emailServiceMock) {
        if ($name == 'client') {
            return $clientMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientSignUp($eventMock);
});

test('onAfterClientCloseTicket handles guest email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->andThrow(new Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if (strtolower($name) == 'support') {
            return $supportServiceMock;
        }
        if (strtolower($name) == 'email') {
            return $emailServiceMock;
        }
    });
    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientCloseTicket($eventMock);
});

test('onAfterClientOpenTicket sends mod_staff_ticket_open email', function (): void {
    $di = container();

    $ticketModel = new Box\Mod\Support\Entity\SupportTicket();
    \Tests\Helpers\setEntityId($ticketModel, 1);
    $helpdesk = new Helpdesk();
    \Tests\Helpers\setEntityId($helpdesk, 1);
    $ticketModel->setSupportHelpdesk($helpdesk);

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn($ticketModel);

    $supportTicketArray = [];
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn($supportTicketArray);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);

    $emailConfig = [
        'to_staff' => true,
        'code' => 'mod_staff_ticket_open',
        'ticket' => ['priority' => 100],
    ];
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->with($emailConfig)
        ->andReturn(true);

    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')->atLeast()->once()
        ->andReturn(null);
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Helpdesk::class)
        ->atLeast()->once()
        ->andReturn($repoMock);
    $di['em'] = $emMock;
    $admin = createEntity(Admin::class);
    $di['loggedin_admin'] = $admin;

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();
    $service->onAfterClientOpenTicket($eventMock);
});

test('onAfterClientOpenTicket sends mod_support_helpdesk_ticket_open email', function (): void {
    $di = container();

    $ticketModel = new Box\Mod\Support\Entity\SupportTicket();
    \Tests\Helpers\setEntityId($ticketModel, 1);
    $helpdesk = new Helpdesk();
    \Tests\Helpers\setEntityId($helpdesk, 1);
    $ticketModel->setSupportHelpdesk($helpdesk);

    $supportServiceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn($ticketModel);

    $supportTicketArray = [];
    $supportServiceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn($supportTicketArray);

    $helpdeskModel = (new Helpdesk())->setEmail('helpdesk@support.com');

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailConfig = [
        'to' => $helpdeskModel->getEmail(),
        'code' => 'mod_support_helpdesk_ticket_open',
        'ticket' => ['priority' => 100],
    ];
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()
        ->with($emailConfig)
        ->andReturn(true);

    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'support') {
            return $supportServiceMock;
        }
        if ($name == 'email') {
            return $emailServiceMock;
        }
    });

    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')->atLeast()->once()
        ->andReturn($helpdeskModel);
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Helpdesk::class)
        ->atLeast()->once()
        ->andReturn($repoMock);
    $di['em'] = $emMock;
    $admin = createEntity(Admin::class);
    $di['loggedin_admin'] = $admin;

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')->atLeast()->once()
        ->andReturn($di);

    $eventMock->shouldReceive('getparameters')->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();
    $service->onAfterClientOpenTicket($eventMock);
});

test('getList returns paginated result', function (): void {
    $queryBuilderMock = Mockery::mock(Doctrine\ORM\QueryBuilder::class);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock->shouldReceive('paginateDoctrineQuery')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $pagerMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getList([]);
    expect($result)->toBeArray();
});

// Data provider for searchFilters
dataset('searchFilters', fn (): array => [
    'empty filters exclude cron by default' => [
        [],
        'system_name != :system_name',
        ['system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON],
    ],
    'search by keyword' => [
        ['search' => 'keyword'],
        '(name LIKE :name OR email LIKE :email )',
        ['name' => '%keyword%', 'email' => '%keyword%', 'system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON],
    ],
    'filter by status' => [
        ['status' => 'active'],
        'status = :status',
        ['status' => 'active', 'system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON],
    ],
    'filter by no_cron' => [
        ['no_cron' => 'true'],
        'system_name != :system_name',
        ['system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON],
    ],
    'do not filter by false no_cron' => [
        ['no_cron' => 'false'],
        'SELECT * FROM admin',
        [],
    ],
]);

test('getSearchQuery returns correct query and params', function (array $data, string $expectedStr, array $expectedParams): void {
    $di = container();

    $service = new Service();
    $service->setDi($di);
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains((string) $result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toBe([]);
})->with('searchFilters');

test('getCronAdmin returns existing cron admin', function (): void {
    $adminEntity = new Admin();

    $adminRepoMock = Mockery::mock(AdminRepository::class);
    $adminRepoMock->shouldReceive('findCronAdmin')->atLeast()->once()
        ->andReturn($adminEntity);

    $adminGroupRepoMock = Mockery::mock(AdminGroupRepository::class)->shouldIgnoreMissing();
    $adminGroupMemberRepoMock = Mockery::mock(AdminGroupMemberRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Admin::class)->andReturn($adminRepoMock);
    $emMock->shouldReceive('getRepository')->with(AdminGroup::class)->andReturn($adminGroupRepoMock);
    $emMock->shouldReceive('getRepository')->with(AdminGroupMember::class)->andReturn($adminGroupMemberRepoMock);

    $di = container();
    $di['em'] = $emMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getCronAdmin();
    expect($result)->not->toBeEmpty();
    expect($result)->toBeInstanceOf(Admin::class);
});

test('getCronAdmin creates and returns new cron admin', function (): void {
    $adminRepoMock = Mockery::mock(AdminRepository::class);
    $adminRepoMock->shouldReceive('findCronAdmin')->atLeast()->once()
        ->andReturn(null);

    $adminGroupRepoMock = Mockery::mock(AdminGroupRepository::class)->shouldIgnoreMissing();
    $adminGroupMemberRepoMock = Mockery::mock(AdminGroupMemberRepository::class)->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Admin::class)->andReturn($adminRepoMock);
    $emMock->shouldReceive('getRepository')->with(AdminGroup::class)->andReturn($adminGroupRepoMock);
    $emMock->shouldReceive('getRepository')->with(AdminGroupMember::class)->andReturn($adminGroupMemberRepoMock);

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['tools'] = new FOSSBilling\Tools();
    $di['password'] = $passwordMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getCronAdmin();
    expect($result)->not->toBeEmpty();
    expect($result)->toBeInstanceOf(Admin::class);
});

test('toAdminApiArray returns admin array data', function (): void {
    $adminModel = createEntity(Admin::class);

    $expected =
        [
            'id' => '',
            'email' => '',
            'name' => '',
            'system_name' => '',
            'status' => '',
            'signature' => '',
            'created_at' => '',
            'updated_at' => '',
            'groups' => [],
        ];

    $di = container();

    $service = new Service();
    $service->setDi($di);
    $result = $service->toAdminApiArray($adminModel);

    expect($result)->not->toBeEmpty();
    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))))->toBe(0, 'Missing array key values.');
});

test('update updates admin details', function (): void {
    $data = [
        'email' => 'test@example.com',
        'name' => 'testJohn',
        'status' => 'active',
        'signature' => '1345',
    ];

    $adminModel = createEntity(Admin::class);

    $adminEntity = new Admin();
    staffSetEntityId($adminEntity, 1);

    $adminRepository = Mockery::mock(AdminRepository::class);
    $adminRepository->shouldReceive('find')->once()->with(0)->andReturn($adminEntity);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager(adminRepository: $adminRepository);
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();

    $serviceMock->setDi($di);

    $result = $serviceMock->update($adminModel, $data);
    expect($result)->toBeTrue();
});

test('update rejects deactivating last active super administrator', function (): void {
    $adminModel = createEntity(Admin::class, ['id' => 3, 'status' => \Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE]);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->once()->with(3, AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(true);
    $groupMemberRepository->shouldReceive('countActiveMembersInSystemGroup')->once()->with(AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(1);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['events_manager'] = $eventsMock;
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->update($adminModel, ['status' => \Box\Mod\Staff\Entity\Admin::STATUS_INACTIVE]))
        ->toThrow(FOSSBilling\InformationException::class, 'Cannot remove the last active super administrator');
});

test('update rejects deactivating own staff account', function (): void {
    $adminModel = createEntity(Admin::class, ['id' => 10, 'status' => \Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE]);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class), Mockery::mock(AdminGroupMemberRepository::class));
    $di['events_manager'] = $eventsMock;
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->update($adminModel, ['status' => \Box\Mod\Staff\Entity\Admin::STATUS_INACTIVE]))
        ->toThrow(FOSSBilling\InformationException::class, 'You cannot deactivate your own staff account');
});

test('delete removes admin account', function (): void {
    $adminModel = createEntity(Admin::class, ['id' => 5]);

    $adminEntity = new Admin();
    staffSetEntityId($adminEntity, 5);

    $adminRepository = Mockery::mock(AdminRepository::class);
    $adminRepository->shouldReceive('find')->once()->with(5)->andReturn($adminEntity);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('deleteMembershipsForAdmin')->once()->with(5)->andReturn(2);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(false);

    $di = container();
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class), $groupMemberRepository, $adminRepository);
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();

    $serviceMock->setDi($di);

    $result = $serviceMock->delete($adminModel);
    expect($result)->toBeTrue();
});

test('delete rejects removing last active super administrator', function (): void {
    $adminModel = createEntity(Admin::class, ['id' => 3, 'status' => \Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE]);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->once()->with(3, AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(true);
    $groupMemberRepository->shouldReceive('countActiveMembersInSystemGroup')->once()->with(AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(1);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->delete($adminModel))
        ->toThrow(FOSSBilling\InformationException::class, 'Cannot remove the last active super administrator');
});

test('delete rejects cron account', function (): void {
    $adminModel = createEntity(Admin::class, ['system_name' => \Box\Mod\Staff\Entity\Admin::SYSTEM_CRON]);

    $service = new Service();

    expect(fn (): bool => $service->delete($adminModel))
        ->toThrow(FOSSBilling\Exception::class, 'The cron administrator account cannot be removed');
});

test('changePassword updates admin password', function (): void {
    $plainTextPassword = 'password';
    $adminModel = createEntity(Admin::class);

    $adminEntity = new Admin();
    staffSetEntityId($adminEntity, 1);

    $adminRepository = Mockery::mock(AdminRepository::class);
    $adminRepository->shouldReceive('find')->once()->with(0)->andReturn($adminEntity);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once()
        ->with($plainTextPassword);

    $profileServiceStub = $this->createStub(Box\Mod\Profile\Service::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager(adminRepository: $adminRepository);
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['password'] = $passwordMock;
    $di['mod_service'] = $di->protect(fn () => $profileServiceStub);
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();

    $serviceMock->setDi($di);

    $result = $serviceMock->changePassword($adminModel, $plainTextPassword);
    expect($result)->toBeTrue();
});

test('create creates new admin account', function (): void {
    $data = [
        'email' => 'test@example.com',
        'name' => 'testJohn',
        'status' => 'active',
        'password' => '1345',
        'group_id' => 2,
    ];

    $group = new AdminGroup();
    staffSetEntityId($group, 2);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once()
        ->with($data['password']);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class)->shouldReceive('findById')->once()->with(2)->andReturn($group)->getMock());
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $di['password'] = $passwordMock;

    $serviceMock->setDi($di);

    $result = $serviceMock->create($data);
    expect($result)->toBeInt();
    expect($result)->toBe(1);
    expect($di['em']->persisted[0])->toBeInstanceOf(Admin::class);
    expect($di['em']->persisted[1])->toBeInstanceOf(AdminGroupMember::class);
});

test('create rejects missing initial group', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class), Mockery::mock(AdminGroupMemberRepository::class));
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->create([
        'email' => 'test@example.com',
        'name' => 'testJohn',
        'status' => 'active',
        'password' => '1345',
    ]))->toThrow(FOSSBilling\InformationException::class, 'Group ID was not passed');
});

test('create throws exception for duplicate email', function (): void {
    $data = [
        'email' => 'test@example.com',
        'name' => 'testJohn',
        'status' => 'active',
        'password' => '1345',
        'group_id' => 2,
    ];

    $group = new AdminGroup();
    staffSetEntityId($group, 2);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once()
        ->with($data['password']);

    $adminRepository = Mockery::mock(AdminRepository::class)->shouldIgnoreMissing();
    $groupRepository = Mockery::mock(AdminGroupRepository::class)->shouldReceive('findById')->once()->with(2)->andReturn($group)->getMock();
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class)->shouldIgnoreMissing();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->andReturnUsing(static fn (string $class): object => match ($class) {
        Admin::class => $adminRepository,
        AdminGroup::class => $groupRepository,
        default => $groupMemberRepository,
    });
    $em->shouldReceive('persist');
    $em->shouldReceive('flush')->andThrow(new Doctrine\DBAL\Exception\UniqueConstraintViolationException(
        new Doctrine\DBAL\Driver\PDO\Exception('SQLSTATE[23000]'),
        null,
    ));

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['em'] = $em;
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $di['password'] = $passwordMock;

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->create($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Staff member with email test@example.com is already registered.');
});

test('createGroup creates new admin group', function (): void {
    $superAdminGroup = new AdminGroup();
    staffSetEntityId($superAdminGroup, 1);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('findSuperAdministratorGroup')->once()->andReturn($superAdminGroup);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    $result = $serviceMock->createGroup('new_group_name');
    expect($result)->toBeInt();
    expect($result)->toBe(1);
    expect($em->persisted[0])->toBeInstanceOf(AdminGroup::class);
    expect($em->persisted[0]->getParent())->toBe($superAdminGroup);
    expect($em->persisted[0]->getPermissions())->toBe([]);
});

test('createGroup rejects non-super administrator', function (): void {
    $parent = new AdminGroup();
    staffSetEntityId($parent, 1);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(false);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createGroup('new_group_name', $parent))
        ->toThrow(FOSSBilling\Exception::class, 'Only super administrators can manage staff groups');
    expect($em->persisted)->toBe([]);
});

test('createGroup rejects missing super administrator root group', function (): void {
    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('findSuperAdministratorGroup')->once()->andThrow(new FOSSBilling\InformationException('Super Administrator group not found'));
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createGroup('new_group_name'))
        ->toThrow(FOSSBilling\Exception::class, 'Super Administrator group not found');
});

test('deleteGroup removes admin group', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('getDescendantIdsForGroups')->once()->with([2])->andReturn([]);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $groupMemberRepository->shouldReceive('countMembersInGroup')->once()->with(2)->andReturn(0);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    $result = $serviceMock->deleteGroup($adminGroupModel);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
    expect($em->removed[0])->toBe($adminGroupModel);
});

test('deleteGroup rejects non-super administrator', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(FOSSBilling\Exception::class, 'Only super administrators can manage staff groups');
});

test('deleteGroup throws exception for protected group', function (): void {
    $adminGroupModel = (new AdminGroup())->setProtected(true);
    staffSetEntityId($adminGroupModel, 1);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class), $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(FOSSBilling\Exception::class, 'Protected staff groups cannot be removed');
});

test('deleteGroup throws exception when group has members', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('getDescendantIdsForGroups')->never();
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $groupMemberRepository->shouldReceive('countMembersInGroup')->once()->with(2)->andReturn(2);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove group which has staff members');
});

test('deleteGroup throws exception when group restricts email templates', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $groupMemberRepository->shouldReceive('countMembersInGroup')->once()->with(2)->andReturn(0);

    $templateGroupRepo = Mockery::mock(Box\Mod\Email\Repository\EmailTemplateGroupRepository::class);
    $templateGroupRepo->shouldReceive('countTemplatesUsingGroup')->once()->with(2)->andReturn(1);
    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $emailService->shouldReceive('getTemplateGroupRepository')->andReturn($templateGroupRepo);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $di['mod_service'] = $di->protect(fn (string $name = ''): object => strtolower($name) === 'email' ? $emailService : Mockery::mock()->shouldIgnoreMissing());

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove group which is used to restrict email templates');
});

test('deleteGroup throws exception when group has child groups', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('getDescendantIdsForGroups')->once()->with([2])->andReturn([3]);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $groupMemberRepository->shouldReceive('countMembersInGroup')->once()->with(2)->andReturn(0);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(FOSSBilling\Exception::class, 'Cannot remove group which has child groups');
});

test('updateGroup updates group details', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    $data = ['name' => 'OhExampleName'];
    $result = $serviceMock->updateGroup($adminGroupModel, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
    expect($adminGroupModel->getName())->toBe('OhExampleName');
    expect($adminGroupModel->getPermissions())->toBe([]);
    expect($em->persisted[0])->toBe($adminGroupModel);
});

test('updateGroup rejects details changes from non-super administrator', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->updateGroup($adminGroupModel, ['name' => 'Nope']))
        ->toThrow(FOSSBilling\Exception::class, 'Only super administrators can manage staff groups');
});

test('updateGroup allows super administrator to update permissions', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffRegularAdmin();

    $serviceMock->setDi($di);

    expect($serviceMock->updateGroup($adminGroupModel, ['permissions' => ['_submitted' => '1', 'support' => ['access' => true]]]))->toBeTrue();
    expect($adminGroupModel->getPermissions())->toBe(['support' => ['access' => true]]);
});

test('updateGroup rejects permission changes from non-super administrator', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(false);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->updateGroup($adminGroupModel, ['permissions' => ['support' => ['access' => true]]]))
        ->toThrow(FOSSBilling\Exception::class, 'Only super administrators can manage staff groups');
});

test('updateGroup rejects protected group changes', function (): void {
    $adminGroupModel = (new AdminGroup())->setProtected(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class), $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->updateGroup($adminGroupModel, ['name' => 'Nope']))
        ->toThrow(FOSSBilling\Exception::class, 'Protected staff groups cannot be modified');
});

test('updateGroup rejects clearing parent group', function (): void {
    $adminGroupModel = new AdminGroup();
    staffSetEntityId($adminGroupModel, 2);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);
    $di['em'] = staffEntityManager(Mockery::mock(AdminGroupRepository::class), $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->updateGroup($adminGroupModel, ['parent_id' => '']))
        ->toThrow(FOSSBilling\Exception::class, 'Staff groups must have a parent group');
});

test('addAdminToGroup creates membership', function (): void {
    $admin = createEntity(Admin::class, ['id' => 3]);

    $group = new AdminGroup();
    staffSetEntityId($group, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('findMembership')->once()->with(3, 2)->andReturn(null);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $serviceMock->setDi($di);

    expect($serviceMock->addAdminToGroup($admin, $group))->toBeTrue();
    expect($em->persisted[0])->toBeInstanceOf(AdminGroupMember::class);
});

test('addAdminToGroup is idempotent for existing membership', function (): void {
    $admin = createEntity(Admin::class, ['id' => 3]);

    $group = new AdminGroup();
    staffSetEntityId($group, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('findMembership')->once()->with(3, 2)->andReturn(new AdminGroupMember(3, $group));
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $serviceMock->setDi($di);

    expect($serviceMock->addAdminToGroup($admin, $group))->toBeTrue();
    expect($em->persisted)->toBe([]);
});

test('removeAdminFromGroup removes membership', function (): void {
    $admin = createEntity(Admin::class, ['id' => 3, 'status' => \Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE]);

    $group = new AdminGroup();
    staffSetEntityId($group, 2);
    $membership = new AdminGroupMember(3, $group);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('findMembership')->once()->with(3, 2)->andReturn($membership);
    $em = staffEntityManager($groupRepository, $groupMemberRepository);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $serviceMock->setDi($di);

    expect($serviceMock->removeAdminFromGroup($admin, $group))->toBeTrue();
    expect($em->removed[0])->toBe($membership);
});

test('removeAdminFromGroup rejects removing last active super administrator', function (): void {
    $admin = createEntity(Admin::class, ['id' => 3, 'status' => \Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE]);

    $group = (new AdminGroup())->setSystemName(AdminGroup::SYSTEM_SUPER_ADMIN);
    staffSetEntityId($group, 1);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('findMembership')->once()->with(3, 1)->andReturn(new AdminGroupMember(3, $group));
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->once()->with(3, AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(true);
    $groupMemberRepository->shouldReceive('countActiveMembersInSystemGroup')->once()->with(AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(1);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffHierarchyBypassAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->removeAdminFromGroup($admin, $group))
        ->toThrow(FOSSBilling\InformationException::class, 'Cannot remove the last active super administrator');
});

test('delete rejects staff outside actor group subtree', function (): void {
    $actor = createEntity(Admin::class, ['id' => 10]);

    $target = createEntity(Admin::class, ['id' => 20]);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('getDescendantIdsForGroups')->with([1])->andReturn([3]);

    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->with(10, AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(false);
    $groupMemberRepository->shouldReceive('getGroupIdsForAdmin')->with(10)->andReturn([1]);
    $groupMemberRepository->shouldReceive('getGroupIdsForAdmin')->with(20)->andReturn([2]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = $actor;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->delete($target))
        ->toThrow(FOSSBilling\InformationException::class, 'You can only manage staff accounts in lower groups');
});

test('addAdminToGroup rejects target staff without a group', function (): void {
    $actor = createEntity(Admin::class, ['id' => 10]);

    $target = createEntity(Admin::class, ['id' => 20]);

    $peerGroup = new AdminGroup();
    staffSetEntityId($peerGroup, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('getDescendantIdsForGroups')->never();

    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->with(10, AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(false);
    $groupMemberRepository->shouldReceive('getGroupIdsForAdmin')->with(20)->andReturn([]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = $actor;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addAdminToGroup($target, $peerGroup))
        ->toThrow(FOSSBilling\InformationException::class, 'You can only manage staff accounts in lower groups');
});

test('addAdminToGroup rejects assigning groups outside actor subtree', function (): void {
    $actor = createEntity(Admin::class, ['id' => 10]);

    $target = createEntity(Admin::class, ['id' => 20]);

    $peerGroup = new AdminGroup();
    staffSetEntityId($peerGroup, 2);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('getDescendantIdsForGroups')->with([1])->andReturn([3]);

    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->with(10, AdminGroup::SYSTEM_SUPER_ADMIN)->andReturn(false);
    $groupMemberRepository->shouldReceive('getGroupIdsForAdmin')->with(10)->andReturn([1]);
    $groupMemberRepository->shouldReceive('getGroupIdsForAdmin')->with(20)->andReturn([3]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('hasPermission')->once()->andReturn(true);

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = $actor;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->addAdminToGroup($target, $peerGroup))
        ->toThrow(FOSSBilling\InformationException::class, 'You can only manage lower staff groups');
});

test('updateGroup rejects moving group below its own child', function (): void {
    $group = new AdminGroup();
    staffSetEntityId($group, 2);
    $child = new AdminGroup();
    staffSetEntityId($child, 3);

    $groupRepository = Mockery::mock(AdminGroupRepository::class);
    $groupRepository->shouldReceive('findById')->once()->with(3)->andReturn($child);
    $groupRepository->shouldReceive('isDescendantOf')->once()->with(3, 2)->andReturn(true);
    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->atLeast()->once()->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $di = container();
    $di['em'] = staffEntityManager($groupRepository, $groupMemberRepository);
    $di['loggedin_admin'] = staffRegularAdmin();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->updateGroup($group, ['parent_id' => 3]))
        ->toThrow(FOSSBilling\InformationException::class, 'A group cannot use one of its subgroups as parent');
});

// Data provider for ActivityAdminHistorySearchFilters
dataset('ActivityAdminHistorySearchFilters', fn (): array => [
    'empty filters' => [
        [],
        'SELECT m.*, a.email, a.name',
        [],
    ],
    'search by keyword' => [
        ['search' => 'keyword'],
        'a.name LIKE :name OR a.id LIKE :id OR a.email LIKE :email',
        ['name' => '%keyword%', 'id' => '%keyword%', 'email' => '%keyword%'],
    ],
    'filter by admin_id' => [
        ['admin_id' => '2'],
        'm.admin_id = :admin_id',
        ['admin_id' => '2'],
    ],
]);

test('getActivityAdminHistorySearchQuery returns correct query and params', function (array $data, string $expectedStr, array $expectedParams): void {
    $di = container();

    $service = new Service();
    $service->setDi($di);
    $result = $service->getActivityAdminHistorySearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains((string) $result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toBe([]);
})->with('ActivityAdminHistorySearchFilters');

test('toActivityAdminHistoryApiArray returns history array data', function (): void {
    $adminHistoryModel = createEntity(\Box\Mod\Activity\Entity\ActivityAdminHistory::class, ['admin_id' => 2]);

    $expected = [
        'id' => '',
        'ip' => '',
        'created_at' => '',
        'staff' => [
            'id' => $adminHistoryModel->admin_id,
            'name' => '',
            'email' => '',
        ],
    ];

    $adminEntity = new Admin();
    staffSetEntityId($adminEntity, 2);

    $adminRepository = Mockery::mock(AdminRepository::class);
    $adminRepository->shouldReceive('find')->once()->with(2)->andReturn($adminEntity);

    $di = container();
    $di['em'] = staffEntityManager(adminRepository: $adminRepository);

    $service = new Service();
    $service->setDi($di);
    $result = $service->toActivityAdminHistoryApiArray($adminHistoryModel);

    expect($result)->not->toBeEmpty();
    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))))->toBe(0, 'Missing array key values.');
});

test('getPermissions returns empty array when staff has no groups', function (): void {
    $service = staffServiceWithGroupPermissions();

    $result = $service->getPermissions(1);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('getPermissions returns union of group permissions', function (): void {
    $supportGroup = (new AdminGroup())->setPermissions([
        'support' => [
            'access' => true,
            'manage_tickets' => true,
        ],
    ]);
    $billingGroup = (new AdminGroup())->setPermissions([
        'support' => [
            'manage_tickets' => false,
            'manage_kb' => true,
        ],
        'invoice' => [
            'access' => true,
        ],
    ]);

    $service = staffServiceWithGroupPermissions([$supportGroup, $billingGroup]);

    $result = $service->getPermissions(1);
    expect($result)->toBeArray();
    expect($result)->toBe([
        'support' => [
            'access' => true,
            'manage_tickets' => true,
            'manage_kb' => true,
        ],
        'invoice' => [
            'access' => true,
        ],
    ]);
});

test('authorizeAdmin returns null when email not found', function (): void {
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')->atLeast()->once()
        ->with(null, $password)
        ->andReturn(null);

    $di = container();
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->authorizeAdmin($email, $password);
    expect($result)->toBeNull();
});

test('authorizeAdmin returns admin model on success', function (): void {
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $model = createEntity(Admin::class);

    $adminRepoMock = Mockery::mock(Box\Mod\Staff\Repository\AdminRepository::class);
    $adminRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['email' => $email, 'status' => Admin::STATUS_ACTIVE])
        ->andReturn($model);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('authorizeUser')->atLeast()->once()
        ->with($model, $password)
        ->andReturn($model);

    $groupMemberRepository = Mockery::mock(AdminGroupMemberRepository::class);
    $groupMemberRepository->shouldReceive('adminBelongsToSystemGroup')->andReturn(false);
    $emMock = staffEntityManager(adminRepository: $adminRepoMock, groupMemberRepository: $groupMemberRepository);

    $di = container();
    $di['em'] = $emMock;
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->authorizeAdmin($email, $password);
    expect($result)->toBeInstanceOf(\Box\Mod\Staff\Entity\Admin::class);
});

test('i18n::validateTimezone returns null for null and empty input', function (): void {
    expect(FOSSBilling\i18n::validateTimezone(null))->toBeNull();
    expect(FOSSBilling\i18n::validateTimezone(''))->toBeNull();
});

test('i18n::validateTimezone accepts any IANA identifier', function (): void {
    expect(FOSSBilling\i18n::validateTimezone('America/New_York'))->toBe('America/New_York');
    expect(FOSSBilling\i18n::validateTimezone('Europe/Berlin'))->toBe('Europe/Berlin');
    expect(FOSSBilling\i18n::validateTimezone('UTC'))->toBe('UTC');
});

test('i18n::validateTimezone throws InformationException for unknown identifier', function (): void {
    expect(fn (): ?string => FOSSBilling\i18n::validateTimezone('Not/A_Zone'))->toThrow(FOSSBilling\InformationException::class);
});
