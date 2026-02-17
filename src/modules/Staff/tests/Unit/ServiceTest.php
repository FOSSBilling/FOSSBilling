<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Staff\Service;
use function Tests\Helpers\container;

class StaffPdoMock extends \PDO
{
    public function __construct()
    {
    }
}

class StaffPdoStatementMock extends \PDOStatement
{
    public function __construct()
    {
    }
}

class StaffQueryBuilderMock
{
    private $stmt;

    public function __construct($stmt = null)
    {
        $this->stmt = $stmt;
    }

    public function update($table)
    {
        return $this;
    }

    public function set($field, $value)
    {
        return $this;
    }

    public function where($cond)
    {
        return $this;
    }

    public function setParameter($key, $val)
    {
        return $this;
    }

    public function executeStatement(): int
    {
        return 1;
    }

    public function select($field)
    {
        return $this;
    }

    public function from($table)
    {
        return $this;
    }

    public function executeQuery()
    {
        return $this->stmt;
    }
}

class StaffDbalMock
{
    private $qb;

    public function __construct($qb)
    {
        $this->qb = $qb;
    }

    public function createQueryBuilder()
    {
        return $this->qb;
    }
}

class StaffStatementMock
{
    private $result;

    public function __construct($result = '{}')
    {
        $this->result = $result;
    }

    public function fetchOne()
    {
        return $this->result;
    }
}

test('login returns admin details on successful login', function (): void {
    $email = 'email@domain.com';
    $password = 'pass';
    $ip = '127.0.0.1';

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;
    $admin->email = $email;
    $admin->name = 'Admin';
    $admin->role = 'admin';

    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive("fire")->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("findOne")->atLeast()->once()
        ->andReturn($admin);

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive("set")->atLeast()->once();

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive("authorizeUser")->atLeast()->once()
        ->with($admin, $password)
        ->andReturn($admin);

    $di = container();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['session'] = $sessionMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->login($email, $password, $ip);

    $expected = [
        'id' => 1,
        'email' => $email,
        'name' => 'Admin',
        'role' => 'admin',
    ];

    expect($result)->toBe($expected);
});

test('login throws exception when credentials are invalid', function (): void {
    $email = 'email@domain.com';
    $password = 'pass';
    $ip = '127.0.0.1';

    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive("fire")->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("findOne")->atLeast()->once()
        ->andReturn(null);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive("authorizeUser")->atLeast()->once()
        ->with(null, $password)
        ->andReturn(null);

    $di = container();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    expect(fn () => $service->login($email, $password, $ip))
        ->toThrow(\FOSSBilling\Exception::class, 'Check your login details');
});

test('getAdminsCount returns count of administrators', function (): void {
    $countResult = 3;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("getCell")->atLeast()->once()
        ->andReturn($countResult);

    $di = container();
    $di['db'] = $dbMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getAdminsCount();
    expect($result)->toBeInt();
    expect($result)->toBe($countResult);
});

test('hasPermission returns true for admin role', function (): void {
    $member = new \Model_Admin();
    $member->loadBean(new \Tests\Helpers\DummyBean());
    $member->role = 'admin';

    $service = new Service();

    $result = $service->hasPermission($member, 'example');
    expect($result)->toBeTrue();
});

test('hasPermission returns false for staff with empty permissions', function (): void {
    $member = new \Model_Admin();
    $member->loadBean(new \Tests\Helpers\DummyBean());
    $member->role = 'staff';

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("getPermissions")->atLeast()->once();

    $extensionServiceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $extensionServiceMock->shouldReceive("getSpecificModulePermissions")->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extensionServiceMock);

    $serviceMock->setDi($di);

    $result = $serviceMock->hasPermission($member, 'example');
    expect($result)->toBeFalse();
});

test('hasPermission returns false for staff without module permission', function (): void {
    $member = new \Model_Admin();
    $member->loadBean(new \Tests\Helpers\DummyBean());
    $member->role = 'staff';

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("getPermissions")->atLeast()->once()
        ->andReturn(['cart' => [], 'client' => []]);

    $extensionServiceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $extensionServiceMock->shouldReceive("getSpecificModulePermissions")->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extensionServiceMock);

    $serviceMock->setDi($di);

    $result = $serviceMock->hasPermission($member, 'example');
    expect($result)->toBeFalse();
});

test('hasPermission returns false for staff without method permission', function (): void {
    $member = new \Model_Admin();
    $member->loadBean(new \Tests\Helpers\DummyBean());
    $member->role = 'staff';

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("getPermissions")->atLeast()->once()
        ->andReturn(['example' => [], 'client' => []]);

    $extensionServiceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $extensionServiceMock->shouldReceive("getSpecificModulePermissions")->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $extensionServiceMock);

    $serviceMock->setDi($di);

    $result = $serviceMock->hasPermission($member, 'example', 'get_list');
    expect($result)->toBeFalse();
});

test('onAfterClientReplyTicket sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportTicket());
    $supportServiceMock->shouldReceive("toApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once();

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientReplyTicket($eventMock);
});

test('onAfterClientReplyTicket handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportTicket());
    $supportServiceMock->shouldReceive("toApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
        ->andThrow(new \Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientReplyTicket($eventMock);
});

test('onAfterClientCloseTicket sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportTicket());
    $supportServiceMock->shouldReceive("toApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once();

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientCloseTicket($eventMock);
});

test('onAfterClientCloseTicket handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportTicket());
    $supportServiceMock->shouldReceive("toApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
        ->andThrow(new \Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientCloseTicket($eventMock);
});

test('onAfterGuestPublicTicketOpen sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getPublicTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportPTicket());
    $supportServiceMock->shouldReceive("publicToApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once();

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterGuestPublicTicketOpen($eventMock);
});

test('onAfterGuestPublicTicketOpen handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getPublicTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportPTicket());
    $supportServiceMock->shouldReceive("publicToApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
        ->andThrow(new \Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterGuestPublicTicketOpen($eventMock);
});

test('onAfterGuestPublicTicketReply sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getPublicTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportPTicket());
    $supportServiceMock->shouldReceive("publicToApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once();

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterGuestPublicTicketReply($eventMock);
});

test('onAfterGuestPublicTicketReply handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getPublicTicketById")->atLeast()->once()
        ->andReturn(new \Model_SupportPTicket());
    $supportServiceMock->shouldReceive("publicToApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
        ->andThrow(new \Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterGuestPublicTicketReply($eventMock);
});

test('onAfterClientSignUp sends email notification', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $clientMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $clientMock->shouldReceive("get")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once();

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientSignUp($eventMock);
});

test('onAfterClientSignUp handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $clientMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $clientMock->shouldReceive("get")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
        ->andThrow(new \Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

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

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterClientSignUp($eventMock);
});

test('onAfterGuestPublicTicketClose handles email exception', function (): void {
    $eventMock = Mockery::mock('\Box_Event');

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("publicToApiArray")->atLeast()->once()
        ->andReturn([]);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
        ->andThrow(new \Exception('PHPunit controlled Exception'));

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn(new \Model_SupportPTicket());

    $service = new Service();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($supportServiceMock, $emailServiceMock) {
        if ($name == 'Support') {
            return $supportServiceMock;
        }
        if ($name == 'Email') {
            return $emailServiceMock;
        }
    });
    $di['db'] = $dbMock;

    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);
    $service->setDi($di);
    $service->onAfterGuestPublicTicketClose($eventMock);
});

test('onAfterClientOpenTicket sends mod_staff_ticket_open email', function (): void {
    $di = container();

    $ticketModel = new \Model_SupportTicket();
    $ticketModel->loadBean(new \Tests\Helpers\DummyBean());

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getTicketById")->atLeast()->once()
        ->andReturn($ticketModel);

    $supportTicketArray = [];
    $supportServiceMock->shouldReceive("toApiArray")->atLeast()->once()
        ->andReturn($supportTicketArray);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);

    $emailConfig = [
        'to_staff' => true,
        'code' => 'mod_staff_ticket_open',
        'ticket' => $supportTicketArray,
    ];
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn(null);
    $di['db'] = $dbMock;
    $di['loggedin_admin'] = new \Model_Admin();

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();
    $service->onAfterClientOpenTicket($eventMock);
});

test('onAfterClientOpenTicket sends mod_support_helpdesk_ticket_open email', function (): void {
    $di = container();

    $ticketModel = new \Model_SupportTicket();
    $ticketModel->loadBean(new \Tests\Helpers\DummyBean());

    $supportServiceMock = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportServiceMock->shouldReceive("getTicketById")->atLeast()->once()
        ->andReturn($ticketModel);

    $supportTicketArray = [];
    $supportServiceMock->shouldReceive("toApiArray")->atLeast()->once()
        ->andReturn($supportTicketArray);

    $helpdeskModel = new \Model_SupportHelpdesk();
    $helpdeskModel->loadBean(new \Tests\Helpers\DummyBean());
    $helpdeskModel->email = 'helpdesk@support.com';

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailConfig = [
        'to' => $helpdeskModel->email,
        'code' => 'mod_support_helpdesk_ticket_open',
        'ticket' => $supportTicketArray,
    ];
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once()
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn($helpdeskModel);
    $di['db'] = $dbMock;
    $di['loggedin_admin'] = new \Model_Admin();

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive("getDi")->atLeast()->once()
        ->andReturn($di);

    $eventMock->shouldReceive("getparameters")->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $service = new Service();
    $service->onAfterClientOpenTicket($eventMock);
});

test('getList returns paginated result', function (): void {
    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pagerMock->shouldReceive("getPaginatedResultSet")->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $pagerMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getList([]);
    expect($result)->toBeArray();
});

// Data provider for searchFilters
dataset('searchFilters', function () {
    return [
        'empty filters' => [
            [],
            'SELECT * FROM admin',
            [],
        ],
        'search by keyword' => [
            ['search' => 'keyword'],
            '(name LIKE :name OR email LIKE :email )',
            [':name' => '%keyword%', ':email' => '%keyword%'],
        ],
        'filter by status' => [
            ['status' => 'active'],
            'status = :status',
            [':status' => 'active'],
        ],
        'filter by no_cron' => [
            ['no_cron' => 'true'],
            'role != :role',
            [':role' => \Model_Admin::ROLE_CRON],
        ],
    ];
});

test('getSearchQuery returns correct query and params', function (array $data, string $expectedStr, array $expectedParams): void {
    $di = container();

    $service = new Service();
    $service->setDi($di);
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toBe([]);
})->with('searchFilters');

test('getCronAdmin returns existing cron admin', function (): void {
    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("findOne")->atLeast()->once()
        ->andReturn($adminModel);

    $di = container();
    $di['db'] = $dbMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getCronAdmin();
    expect($result)->not->toBeEmpty();
    expect($result)->toBeInstanceOf(\Model_Admin::class);
});

test('getCronAdmin creates and returns new cron admin', function (): void {
    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("findOne")->atLeast()->once()
        ->andReturn(null);

    $dbMock->shouldReceive("dispense")->atLeast()->once()
        ->andReturn($adminModel);

    $dbMock->shouldReceive("store")->atLeast()->once();

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive("hashIt")->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = new \FOSSBilling\Tools();
    $di['password'] = $passwordMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getCronAdmin();
    expect($result)->not->toBeEmpty();
    expect($result)->toBeInstanceOf(\Model_Admin::class);
});

test('toModel_AdminApiArray returns admin array data', function (): void {
    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $adminGroupModel = new \Model_Admin();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn($adminGroupModel);

    $expected =
        [
            'id' => '',
            'role' => '',
            'admin_group_id' => '',
            'email' => '',
            'name' => '',
            'status' => '',
            'signature' => '',
            'created_at' => '',
            'updated_at' => '',
            'protected' => '',
            'group' => ['id' => '', 'name' => ''],
        ];

    $di = container();
    $di['db'] = $dbMock;

    $service = new Service();
    $service->setDi($di);
    $result = $service->toModel_AdminApiArray($adminModel);

    expect($result)->not->toBeEmpty();
    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))))->toBe(0, 'Missing array key values.');
});

test('update updates admin details', function (): void {
    $data = [
        'email' => 'test@example.com',
        'admin_group_id' => '1',
        'name' => 'testJohn',
        'status' => 'active',
        'signature' => '1345',
    ];

    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive("fire")->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("store")->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);

    $result = $serviceMock->update($adminModel, $data);
    expect($result)->toBeTrue();
});

test('delete removes admin account', function (): void {
    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive("fire")->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("trash")->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);

    $result = $serviceMock->delete($adminModel);
    expect($result)->toBeTrue();
});

test('delete throws exception for protected account', function (): void {
    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());
    $adminModel->protected = 1;

    $service = new Service();

    expect(fn () => $service->delete($adminModel))
        ->toThrow(\FOSSBilling\Exception::class, 'This administrator account is protected and cannot be removed');
});

test('changePassword updates admin password', function (): void {
    $plainTextPassword = 'password';
    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive("fire")->atLeast()->once();

    $logStub = $this->createStub('\Box_Log');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("store")->atLeast()->once();

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive("hashIt")->atLeast()->once()
        ->with($plainTextPassword);

    $profileServiceStub = $this->createStub(\Box\Mod\Profile\Service::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['db'] = $dbMock;
    $di['password'] = $passwordMock;
    $di['mod_service'] = $di->protect(fn () => $profileServiceStub);

    $serviceMock->setDi($di);

    $result = $serviceMock->changePassword($adminModel, $plainTextPassword);
    expect($result)->toBeTrue();
});

test('create creates new admin account', function (): void {
    $data = [
        'email' => 'test@example.com',
        'admin_group_id' => '1',
        'name' => 'testJohn',
        'status' => 'active',
        'password' => '1345',
    ];

    $newId = 1;

    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive("checkLimits")->atLeast()->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive("fire")->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("dispense")->atLeast()->once()
        ->andReturn($adminModel);
    $dbMock->shouldReceive("store")->atLeast()->once()
        ->andReturn($newId);

    $logStub = $this->createStub('\Box_Log');

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive("hashIt")->atLeast()->once()
        ->with($data['password']);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);

    $di['password'] = $passwordMock;

    $serviceMock->setDi($di);

    $result = $serviceMock->create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('create throws exception for duplicate email', function (): void {
    $data = [
        'email' => 'test@example.com',
        'admin_group_id' => '1',
        'name' => 'testJohn',
        'status' => 'active',
        'password' => '1345',
    ];

    $newId = 1;

    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive("checkLimits")->atLeast()->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive("fire")->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("dispense")->atLeast()->once()
        ->andReturn($adminModel);
    $dbMock->shouldReceive("store")->atLeast()->once()
        ->andThrow(new \RedBeanPHP\RedException());

    $logStub = $this->createStub('\Box_Log');

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive("hashIt")->atLeast()->once()
        ->with($data['password']);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventsMock;
    $di['logger'] = $logStub;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);

    $di['password'] = $passwordMock;

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->create($data))
        ->toThrow(\FOSSBilling\Exception::class, "Staff member with email {$data['email']} is already registered.");
});

test('createAdmin creates new admin without permission check', function (): void {
    $data = [
        'email' => 'test@example.com',
        'admin_group_id' => '1',
        'name' => 'testJohn',
        'status' => 'active',
        'password' => '1345',
    ];

    $newId = 1;

    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("dispense")->atLeast()->once()
        ->andReturn($adminModel);
    $dbMock->shouldReceive("store")->atLeast()->once()
        ->andReturn($newId);

    $logStub = $this->createStub('\Box_Log');

    $systemService = $this->createStub(\Box\Mod\System\Service::class);

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive("hashIt")->atLeast()->once()
        ->with($data['password']);

    $di = container();
    $di['logger'] = $logStub;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($systemService) {
        if ($serviceName == 'system') {
            return $systemService;
        }
    });
    $di['password'] = $passwordMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->createAdmin($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('getAdminGroupPair returns group pairs', function (): void {
    $rows = [
        [
            'id' => '1',
            'name' => 'First Jogh',
        ],
        [
            'id' => '2',
            'name' => 'Another Smith',
        ],
    ];

    $expected = [
        1 => 'First Jogh',
        2 => 'Another Smith',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("getAll")->atLeast()->once()
        ->andReturn($rows);

    $di = container();
    $di['db'] = $dbMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->getAdminGroupPair();

    expect($result)->toBe($expected);
    expect($result)->toBeArray();
});

test('getAdminGroupSearchQuery returns query and params', function (): void {
    $service = new Service();

    $result = $service->getAdminGroupSearchQuery([]);

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
});

test('createGroup creates new admin group', function (): void {
    $adminGroupModel = new \Model_AdminGroup();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());
    $newGroupId = 1;

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive("checkLimits")->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("dispense")->atLeast()->once()
        ->andReturn($adminGroupModel);
    $dbMock->shouldReceive("store")->atLeast()->once()
        ->andReturn($newGroupId);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);

    $serviceMock->setDi($di);

    $result = $serviceMock->createGroup('new_group_name');
    expect($result)->toBeInt();
    expect($result)->toBe($newGroupId);
});

test('toAdminGroupApiArray returns group array data', function (): void {
    $adminGroupModel = new \Model_AdminGroup();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());

    $expected =
        [
            'id' => '',
            'name' => '',
            'created_at' => '',
            'updated_at' => '',
        ];

    $service = new Service();

    $result = $service->toAdminGroupApiArray($adminGroupModel);

    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))))->toBe(0, 'Missing array key values.');
});

test('deleteGroup removes admin group', function (): void {
    $adminGroupModel = new \Model_AdminGroup();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("trash")->atLeast()->once();
    $dbMock->shouldReceive("getCell")->atLeast()->once()
        ->andReturn(0);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->deleteGroup($adminGroupModel);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deleteGroup throws exception for administrators group', function (): void {
    $adminGroupModel = new \Model_AdminGroup();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());
    $adminGroupModel->id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(\FOSSBilling\Exception::class, 'Administrators group cannot be removed');
});

test('deleteGroup throws exception when group has members', function (): void {
    $adminGroupModel = new \Model_AdminGroup();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("getCell")->atLeast()->once()
        ->andReturn(2);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->deleteGroup($adminGroupModel))
        ->toThrow(\FOSSBilling\Exception::class, 'Cannot remove group which has staff members');
});

test('updateGroup updates group details', function (): void {
    $adminGroupModel = new \Model_AdminGroup();
    $adminGroupModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("store")->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $data = ['name' => 'OhExampleName'];
    $result = $serviceMock->updateGroup($adminGroupModel, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

// Data provider for ActivityAdminHistorySearchFilters
dataset('ActivityAdminHistorySearchFilters', function () {
    return [
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
    ];
});

test('getActivityAdminHistorySearchQuery returns correct query and params', function (array $data, string $expectedStr, array $expectedParams): void {
    $di = container();

    $service = new Service();
    $service->setDi($di);
    $result = $service->getActivityAdminHistorySearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toBe([]);
})->with('ActivityAdminHistorySearchFilters');

test('toActivityAdminHistoryApiArray returns history array data', function (): void {
    $adminHistoryModel = new \Model_ActivityAdminHistory();
    $adminHistoryModel->loadBean(new \Tests\Helpers\DummyBean());
    $adminHistoryModel->admin_id = 2;

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

    $adminModel = new \Model_Admin();
    $adminModel->loadBean(new \Tests\Helpers\DummyBean());
    $adminModel->id = 2;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("load")->atLeast()->once()
        ->andReturn($adminModel);

    $di = container();
    $di['db'] = $dbMock;

    $service = new Service();
    $service->setDi($di);
    $result = $service->toActivityAdminHistoryApiArray($adminHistoryModel);

    expect($result)->not->toBeEmpty();
    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))))->toBe(0, 'Missing array key values.');
});

test('setPermissions updates staff permissions', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive("hasPermission")->atLeast()->once()->andReturn(true);

    $queryBuilderMock = new StaffQueryBuilderMock();

    $dbalMock = new StaffDbalMock($queryBuilderMock);

    $di = new \Pimple\Container();
    $di['dbal'] = $dbalMock;
    $serviceMock->setDi($di);

    $member_id = 1;
    $result = $serviceMock->setPermissions($member_id, []);
    expect($result)->toBeTrue();
});

test('getPermissions returns empty array when permissions are empty', function (): void {
    $statementWithFetchOne = new StaffStatementMock('{}');

    $service = new Service();

    $queryBuilderMock = new StaffQueryBuilderMock($statementWithFetchOne);

    $dbalMock = new StaffDbalMock($queryBuilderMock);

    $di = new \Pimple\Container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $member_id = 1;
    $result = $service->getPermissions($member_id);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('getPermissions returns permissions array', function (): void {
    $queryResult = '{"id" : "1"}';

    $statementWithFetchOne = new StaffStatementMock($queryResult);

    $service = new Service();

    $queryBuilderMock = new StaffQueryBuilderMock($statementWithFetchOne);

    $dbalMock = new StaffDbalMock($queryBuilderMock);

    $di = new \Pimple\Container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $member_id = 1;
    $expected = json_decode($queryResult ?? '', true);
    $result = $service->getPermissions($member_id);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('authorizeAdmin returns null when email not found', function (): void {
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("findOne")->atLeast()->once()
        ->andReturn(null);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive("authorizeUser")->atLeast()->once()
        ->with(null, $password)
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->authorizeAdmin($email, $password);
    expect($result)->toBeNull();
});

test('authorizeAdmin returns admin model on success', function (): void {
    $email = 'example@fossbilling.vm';
    $password = '123456';

    $model = new \Model_Admin();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive("findOne")->atLeast()->once()
        ->andReturn($model);

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive("authorizeUser")->atLeast()->once()
        ->with($model, $password)
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['auth'] = $authMock;

    $service = new Service();
    $service->setDi($di);

    $result = $service->authorizeAdmin($email, $password);
    expect($result)->toBeInstanceOf(\Model_Admin::class);
});
