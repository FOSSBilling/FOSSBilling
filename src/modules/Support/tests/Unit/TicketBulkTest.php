<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketBulk;

use function Tests\Helpers\container;

function ticketBulkConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, status VARCHAR(30))');
    $connection->executeStatement('CREATE TABLE support_ticket_pin (ticket_id BIGINT NOT NULL, pinned_by BIGINT DEFAULT NULL, pinned_at DATETIME NOT NULL, PRIMARY KEY (ticket_id))');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;

    return [$di, $connection];
}

test('bulk ids are cleaned and bounded', function (): void {
    expect(TicketBulk::ids(['12', 10, '12', 'x', -3, 0]))->toBe([12, 10]);
    expect(TicketBulk::ids('5, 6,7'))->toBe([5, 6, 7]);
    expect(fn (): array => TicketBulk::ids([]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'at least one');
    expect(fn (): array => TicketBulk::ids(range(1, 101)))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'At most');
});

test('bulk pins are idempotent and ordered newest first', function (): void {
    [$di, $connection] = ticketBulkConnection();
    foreach ([10, 12, 99] as $id) {
        $connection->insert('support_ticket', ['id' => $id, 'status' => 'open']);
    }

    expect(TicketBulk::pin($di, [10, 12], 1))->toBe(2);
    expect(TicketBulk::pin($di, [10, 12], 1))->toBe(2);
    expect(TicketBulk::pinnedIds($di))->toBe([12, 10]);
    expect(TicketBulk::unpin($di, [12, 14]))->toBe(1);
    expect(TicketBulk::pinnedIds($di))->toBe([10]);
});

test('bulk sweep drops pins of deleted tickets', function (): void {
    [$di, $connection] = ticketBulkConnection();
    $connection->insert('support_ticket', ['id' => 10, 'status' => 'open']);
    TicketBulk::pin($di, [10], 1);
    expect(TicketBulk::sweep($di))->toBe(0);

    $connection->delete('support_ticket', ['id' => 10]);
    expect(TicketBulk::sweep($di))->toBe(1);
    expect(TicketBulk::pinnedIds($di))->toBe([]);
});

test('bulk reads degrade to unpinned without the table', function (): void {
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    $di['em'] = $em;

    expect(TicketBulk::pinnedIds($di))->toBe([]);
    expect(TicketBulk::sweep($di))->toBe(0);
});

test('bulk senders lists guest and client addresses lowercased', function (): void {
    [$di, $connection] = ticketBulkConnection();
    $connection->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, email VARCHAR(255))');
    $connection->executeStatement('ALTER TABLE support_ticket ADD COLUMN client_id INTEGER DEFAULT NULL');
    $connection->executeStatement('ALTER TABLE support_ticket ADD COLUMN author_email VARCHAR(255) DEFAULT NULL');
    $connection->insert('support_ticket', ['id' => 10, 'status' => 'open', 'client_id' => null, 'author_email' => 'Guest@X.test']);
    $connection->insert('support_ticket', ['id' => 11, 'status' => 'open', 'client_id' => 1, 'author_email' => '']);
    $connection->insert('client', ['id' => 1, 'email' => 'Alice@Example.com']);

    // Without the author table the first message is not author-attributed: falls back cleanly.
    expect(TicketBulk::senders($di, [10, 11, 10]))->toBe(['guest@x.test', 'alice@example.com']);
});
