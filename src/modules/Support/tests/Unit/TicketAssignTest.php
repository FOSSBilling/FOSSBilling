<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketAssign;

use function Tests\Helpers\container;

function ticketAssignConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30), priority INTEGER, support_helpdesk_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_helpdesk (id INTEGER PRIMARY KEY, name VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_ticket_assignment (ticket_id BIGINT NOT NULL, admin_id BIGINT NOT NULL, assigned_by BIGINT DEFAULT NULL, assigned_at DATETIME NOT NULL, PRIMARY KEY (ticket_id))');
    $connection->insert('admin', ['id' => 1, 'name' => 'Test Admin', 'status' => 'active', 'system_name' => null]);
    $connection->insert('admin', ['id' => 2, 'name' => 'Cron', 'status' => 'active', 'system_name' => 'cron']);
    $connection->insert('admin', ['id' => 3, 'name' => 'Support Agent', 'status' => 'active', 'system_name' => null]);
    $connection->insert('admin', ['id' => 4, 'name' => 'Gone', 'status' => 'inactive', 'system_name' => null]);
    $connection->insert('support_helpdesk', ['id' => 1, 'name' => 'General']);
    $connection->insert('support_ticket', ['id' => 10, 'subject' => 'Help', 'status' => 'open', 'priority' => 100, 'support_helpdesk_id' => 1]);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;

    return [$di, $connection];
}

test('assignable staff are active non-system staff by name', function (): void {
    [$di] = ticketAssignConnection();

    expect(TicketAssign::staff($di))->toBe([3 => 'Support Agent', 1 => 'Test Admin']);
});

test('assign changes the assignee and emails them once', function (): void {
    [$di, $connection] = ticketAssignConnection();
    $sent = [];
    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->twice()->with(Mockery::capture($sent))->andReturnTrue();
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $di['url'] = Mockery::mock(FOSSBilling\Core\Url::class);
    $di['url']->shouldReceive('adminLink')->andReturn('https://billing.example/admin/support/ticket/10');

    expect(TicketAssign::assign($di, 10, 3, 1))->toBeTrue();
    expect($sent['to_admin'])->toBe(3);
    expect($sent['code'])->toBe('mod_staff_ticket_assigned');
    expect($sent['assigned_by'])->toBe('Test Admin');
    expect($sent['ticket']['priority'])->toBe('medium');
    expect(TicketAssign::assignee($di, 10))->toBe(3);
    // Same assignee again: no change, no second email.
    expect(TicketAssign::assign($di, 10, 3, 1))->toBeFalse();

    // Self-assign: changes, but stays silent.
    expect(TicketAssign::assign($di, 10, 1, 1))->toBeTrue();

    // Unassign.
    expect(TicketAssign::assign($di, 10, 0, 1))->toBeTrue();
    expect(TicketAssign::assignee($di, 10))->toBeNull();

    // Deactivated staff read as unassigned.
    TicketAssign::assign($di, 10, 3, 1);
    $connection->update('admin', ['status' => 'inactive'], ['id' => 3]);
    expect(TicketAssign::assignee($di, 10))->toBeNull();
});

test('assign rejects unknown tickets and staff', function (): void {
    [$di] = ticketAssignConnection();

    expect(fn (): bool => TicketAssign::assign($di, 999, 1, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
    expect(fn (): bool => TicketAssign::assign($di, 10, 2, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'active staff member');
    expect(fn (): bool => TicketAssign::assign($di, 10, 4, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'active staff member');
});

test('assignee filter resolves me, none and unknown staff', function (): void {
    [$di] = ticketAssignConnection();
    TicketAssign::assign($di, 10, 3, 1);

    expect(TicketAssign::listFilter('', 3))->toBeNull();
    expect(TicketAssign::listFilter('me', 3))->toBe(3);
    expect(TicketAssign::listFilter('me', 1))->toBe(1);
    expect(TicketAssign::listFilter('none', 3))->toBe('none');
    expect(TicketAssign::listFilter('any', 3))->toBe('any');
    expect(TicketAssign::listFilter('3', 1))->toBe(3);
    expect(TicketAssign::listFilter('2', 1))->toBe(2);
    expect(fn (): int|string|null => TicketAssign::listFilter('bogus', 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Unknown assignee');
});

test('assign sweep drops assignments of deleted tickets', function (): void {
    [$di, $connection] = ticketAssignConnection();
    TicketAssign::assign($di, 10, 3, 1);
    expect(TicketAssign::sweep($di))->toBe(0);

    $connection->delete('support_ticket', ['id' => 10]);
    expect(TicketAssign::sweep($di))->toBe(1);
    expect(TicketAssign::assignees($di, [10]))->toBe([]);
});

test('assignee membership covers older tickets before filtering and pagination', function (string $filter, int $me, array $expected): void {
    [$di, $db] = ticketAssignConnection();
    $db->executeStatement('ALTER TABLE support_ticket ADD author_email VARCHAR(255)');
    $db->executeStatement('ALTER TABLE support_ticket ADD author_name VARCHAR(255)');
    $db->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id BIGINT, content TEXT)');
    $db->transactional(function () use ($db): void {
        for ($id = 100; $id <= 5100; ++$id) {
            $db->insert('support_ticket', ['id' => $id, 'subject' => 'Historical', 'status' => 'closed', 'support_helpdesk_id' => 1]);
            $db->insert('support_ticket_assignment', ['ticket_id' => $id, 'admin_id' => 3, 'assigned_at' => '2026-01-01 00:00:00']);
        }
        foreach ([10 => 3, 11 => null, 12 => 4, 13 => 2, 14 => 999, 15 => 1] as $id => $admin) {
            if ($id !== 10) {
                $db->insert('support_ticket', ['id' => $id, 'subject' => 'Help', 'status' => 'open', 'support_helpdesk_id' => 1]);
            }
            if ($admin !== null) {
                $db->insert('support_ticket_assignment', ['ticket_id' => $id, 'admin_id' => $admin, 'assigned_at' => '2026-01-01 00:00:00']);
            }
        }
    });
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    $qb = $em->getRepository(Box\Mod\Support\Entity\SupportTicket::class)->getSearchQueryBuilder([
        'assigned_filter' => TicketAssign::listFilter($filter, $me), 'status' => 'open', 'support_helpdesk_id' => 1, 'search' => 'Help',
    ])->select('t.id');

    try {
        expect(array_map(intval(...), $qb->getQuery()->getSingleColumnResult()))->toBe($expected);
        $count = (clone $qb)->resetDQLPart('orderBy')->select('COUNT(t.id)');
        expect((int) $count->getQuery()->getSingleScalarResult())->toBe(count($expected));
        $paged = (clone $qb)->setFirstResult(1)->setMaxResults(1);
        expect(array_map(intval(...), $paged->getQuery()->getSingleColumnResult()))->toBe(array_slice($expected, 1, 1));
    } finally {
        $db->close();
    }
})->with([
    'me' => ['me', 3, [10]],
    'staff id' => ['3', 1, [10]],
    'any' => ['any', 3, [15, 10]],
    'none includes inactive system and deleted staff' => ['none', 3, [14, 13, 12, 11]],
    'unknown staff' => ['999', 3, []],
    'inactive staff' => ['4', 3, []],
]);
