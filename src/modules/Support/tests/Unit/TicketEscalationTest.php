<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\TicketEscalation;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function ticketEscalationConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_helpdesk (id INTEGER PRIMARY KEY, name VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30), priority INTEGER, support_helpdesk_id INTEGER, client_id INTEGER, author_name VARCHAR(255) DEFAULT NULL, author_email VARCHAR(255) DEFAULT NULL, created_at DATETIME, updated_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, admin_id INTEGER, client_id INTEGER, content TEXT, created_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_escalation_rule (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, helpdesks TEXT NOT NULL, statuses TEXT NOT NULL, priorities TEXT NOT NULL, minutes INT NOT NULL, new_helpdesk_id BIGINT DEFAULT NULL, new_status VARCHAR(30) NOT NULL DEFAULT \'\', new_priority VARCHAR(10) NOT NULL DEFAULT \'\', assign_to BIGINT DEFAULT NULL, notify TEXT NOT NULL, reply TEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_escalation_applied (id INTEGER PRIMARY KEY AUTOINCREMENT, rule_id BIGINT NOT NULL, ticket_id BIGINT NOT NULL, reply_at DATETIME NOT NULL, applied_at DATETIME NOT NULL, UNIQUE (rule_id, ticket_id))');
    $connection->insert('admin', ['id' => 1, 'name' => 'Cron', 'status' => 'active', 'system_name' => 'cron']);
    $connection->insert('admin', ['id' => 3, 'name' => 'Support Agent', 'status' => 'active', 'system_name' => null]);
    $connection->insert('admin', ['id' => 4, 'name' => 'Gone', 'status' => 'inactive', 'system_name' => null]);
    $connection->insert('support_helpdesk', ['id' => 1, 'name' => 'General']);

    return [$connection];
}

function ticketEscalationDi(Doctrine\DBAL\Connection $connection, array &$calls): Pimple\Container
{
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $support = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $support->shouldReceive('getTicketById')->andReturnUsing(function (int $id): SupportTicket {
        $ticket = createEntity(SupportTicket::class, ['id' => $id, 'client_id' => 1]);

        return $ticket;
    });
    $support->shouldReceive('ticketReply')->andReturnUsing(function () use (&$calls): int {
        $calls['replies'][] = func_get_args();

        return count($calls['replies']);
    });
    $support->shouldReceive('ticketUpdate')->andReturnUsing(function () use (&$calls): bool {
        $calls['updates'][] = func_get_args();

        return true;
    });
    $staff = Mockery::mock(Box\Mod\Staff\Service::class)->makePartial();
    $staff->shouldReceive('getCronAdmin')->andReturn(createEntity(Box\Mod\Staff\Entity\Admin::class, ['id' => 1]));
    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->andReturnUsing(function (array $data) use (&$calls): bool {
        $calls['emails'][] = $data;

        return true;
    });
    $url = Mockery::mock(FOSSBilling\Core\Url::class);
    $url->shouldReceive('adminLink')->andReturnUsing(fn (string $path): string => 'https://billing.example/admin/' . $path);
    $di['mod_service'] = $di->protect(fn (string $name): object => match ($name) {
        'support' => $support,
        'staff' => $staff,
        'email' => $email,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });
    $di['url'] = $url;

    return $di;
}

function ticketEscalationRule(Doctrine\DBAL\Connection $connection, array $overrides = []): int
{
    // Created two minutes ago so a reply from 61 minutes ago ran out of time after the rule was made.
    $connection->insert('support_escalation_rule', [
        'name' => 'Nudge',
        'active' => 1,
        'helpdesks' => '[]',
        'statuses' => '["open"]',
        'priorities' => '[]',
        'minutes' => 60,
        'new_helpdesk_id' => null,
        'new_status' => '',
        'new_priority' => '',
        'assign_to' => null,
        'notify' => '[]',
        'reply' => 'hi',
        'created_at' => date('Y-m-d H:i:s', time() - 120),
        'updated_at' => date('Y-m-d H:i:s', time() - 120),
        ...$overrides,
    ]);

    return (int) $connection->lastInsertId();
}

function ticketEscalationTicket(Doctrine\DBAL\Connection $connection, int $id, string $status, ?int $clientId, string $lastReply, ?int $adminId = null): void
{
    $connection->insert('support_ticket', ['id' => $id, 'subject' => 'Help ' . $id, 'status' => $status, 'priority' => 100, 'support_helpdesk_id' => 1, 'client_id' => $clientId, 'created_at' => '2026-01-01 10:00:00', 'updated_at' => $lastReply]);
    $connection->insert('support_ticket_message', ['support_ticket_id' => $id, 'admin_id' => $adminId, 'client_id' => $adminId === null ? $clientId : null, 'content' => 'hello', 'created_at' => $lastReply]);
}

test('escalation rule crud normalizes inputs', function (): void {
    [$connection] = ticketEscalationConnection();
    $calls = [];
    $di = ticketEscalationDi($connection, $calls);

    $bad = ['name' => ' No reply ', 'minutes' => '240', 'helpdesks' => '1,1,2', 'statuses' => ['open']];
    // Unknown helpdesk 2 is rejected (only 1 exists).
    expect(fn (): int => TicketEscalation::save($di, $bad))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'help desks');

    $id = TicketEscalation::save($di, [
        'name' => '  Nudge  ',
        'minutes' => '240',
        'helpdesks' => '1,1',
        'statuses' => 'OPEN',
        'priorities' => 'High',
        'new_helpdesk_id' => '1',
        'new_status' => 'on_hold',
        'new_priority' => 'HIGH',
        'assign_to' => '3',
        'notify' => '3,3',
        'reply' => "Hello [NAME]\r\nhi",
        'active' => '1',
    ]);
    $rule = TicketEscalation::get($di, $id);
    expect($rule['name'])->toBe('Nudge');
    expect($rule['helpdesks'])->toBe([1]);
    expect($rule['statuses'])->toBe(['open']);
    expect($rule['priorities'])->toBe(['high']);
    expect($rule['new_priority'])->toBe('high');
    expect($rule['notify'])->toBe([3]);
    expect($rule['reply'])->toBe("Hello [NAME]\nhi");
    expect($rule['active'])->toBeTrue();
});

test('escalation rule validation matrix', function (): void {
    [$connection] = ticketEscalationConnection();
    $calls = [];
    $di = ticketEscalationDi($connection, $calls);

    $valid = ['name' => 'R', 'minutes' => 60, 'reply' => 'hi'];
    $cases = [
        'empty name' => [['name' => '  '], 'Name the rule'],
        'bad minutes' => [['minutes' => 0], 'minutes'],
        'minutes too large' => [['minutes' => 9999999], 'minutes'],
        'unknown helpdesk' => [['helpdesks' => [99]], 'help desks'],
        'unknown status' => [['statuses' => ['bogus']], 'statuses'],
        'unknown priority' => [['priorities' => ['urgent']], 'priorities'],
        'unknown move target' => [['new_helpdesk_id' => 99], 'existing help desk'],
        'unknown new status' => [['new_status' => 'bogus'], 'ticket status'],
        'unknown new priority' => [['new_priority' => 'urgent'], 'Low, Medium or High'],
        'inactive assignee' => [['assign_to' => 4], 'active staff member'],
        'unknown notify' => [['notify' => [99]], 'email'],
        'no action' => [['reply' => ''], 'at least one action'],
    ];
    foreach ($cases as [$overrides, $message]) {
        expect(fn (): int => TicketEscalation::save($di, [...$valid, ...$overrides]))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, $message);
    }
});

test('escalation rule runs once per reply and re-arms on a new reply', function (): void {
    [$connection] = ticketEscalationConnection();
    $calls = [];
    $di = ticketEscalationDi($connection, $calls);
    $connection->insert('client', ['id' => 1, 'first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com']);

    $ruleId = ticketEscalationRule($connection, ['reply' => 'Hello [NAME] ([FIRSTNAME], [EMAIL])', 'new_priority' => 'high', 'notify' => '[3]']);
    expect($ruleId)->toBeGreaterThan(0);

    // Client reply 61 minutes ago: due.
    ticketEscalationTicket($connection, 10, 'open', 1, date('Y-m-d H:i:s', time() - 3660));
    // Client reply 30 minutes ago: not due yet.
    ticketEscalationTicket($connection, 11, 'open', 1, date('Y-m-d H:i:s', time() - 30 * 60));
    // Only the cron's own reply: never due (system replies do not count).
    $connection->insert('support_ticket', ['id' => 12, 'subject' => 'H', 'status' => 'open', 'priority' => 100, 'support_helpdesk_id' => 1, 'client_id' => 1, 'created_at' => '2026-01-01 10:00:00', 'updated_at' => date('Y-m-d H:i:s', time() - 3660)]);
    $connection->insert('support_ticket_message', ['support_ticket_id' => 12, 'admin_id' => 1, 'client_id' => null, 'content' => 'auto', 'created_at' => date('Y-m-d H:i:s', time() - 3660)]);

    expect(TicketEscalation::run($di))->toBe(1);
    expect($calls['replies'][0][2])->toBe('Hello Alice Smith (Alice, alice@example.com)');
    expect($calls['updates'][0][1])->toBe(['priority' => 'high']);
    expect($calls['emails'])->toHaveCount(1);
    expect($calls['emails'][0]['code'])->toBe('mod_staff_ticket_escalated');

    // Second run: already acted for that reply.
    $acted = TicketEscalation::run($di);
    unset($calls);
    expect($acted)->toBe(0);

    // A new client reply re-arms the rule.
    $connection->insert('support_ticket_message', ['support_ticket_id' => 10, 'admin_id' => null, 'client_id' => 1, 'content' => 'still stuck', 'created_at' => date('Y-m-d H:i:s', time() - 3650)]);
    expect(TicketEscalation::run($di))->toBe(1);
});

test('paused rules never run and failed rules are logged', function (): void {
    [$connection] = ticketEscalationConnection();
    $calls = [];
    $di = ticketEscalationDi($connection, $calls);

    ticketEscalationRule($connection, ['name' => 'Paused', 'active' => 0]);
    ticketEscalationTicket($connection, 10, 'open', null, date('Y-m-d H:i:s', time() - 3660));
    expect(TicketEscalation::run($di))->toBe(0);

    // A rule whose assignee was deactivated afterwards fails but does not loop forever.
    ticketEscalationRule($connection, ['name' => 'Doomed assign', 'assign_to' => 3]);
    $connection->update('admin', ['status' => 'inactive'], ['id' => 3]);
    expect(TicketEscalation::run($di))->toBe(0);
    expect(TicketEscalation::run($di))->toBe(0);
});

test('escalation sweep drops orphan marks and reads degrade without tables', function (): void {
    [$connection] = ticketEscalationConnection();
    $calls = [];
    $di = ticketEscalationDi($connection, $calls);

    ticketEscalationRule($connection);
    ticketEscalationTicket($connection, 10, 'open', null, date('Y-m-d H:i:s', time() - 3660));
    expect(TicketEscalation::run($di))->toBe(1);
    expect(TicketEscalation::sweep($di))->toBe(0);

    $connection->delete('support_ticket', ['id' => 10]);
    expect(TicketEscalation::sweep($di))->toBe(1);

    TicketEscalation::delete($di, 1);
    expect(TicketEscalation::rules($di))->toBe([]);
    expect(fn (): array => TicketEscalation::get($di, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'not found');

    $bare = container();
    $bareEm = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $bareEm->shouldReceive('getConnection')->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    $bare['em'] = $bareEm;
    expect(TicketEscalation::rules($bare))->toBe([]);
    expect(TicketEscalation::run($bare))->toBe(0);
    expect(TicketEscalation::sweep($bare))->toBe(0);
});
