<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketMerge;

use function Tests\Helpers\container;

function ticketMergeConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, client_id INTEGER, author_email VARCHAR(255), subject VARCHAR(255), status VARCHAR(30), rel_type VARCHAR(30) DEFAULT NULL, rel_id INTEGER DEFAULT NULL, rel_task VARCHAR(30) DEFAULT NULL, rel_new_value VARCHAR(255) DEFAULT NULL, rel_status VARCHAR(30) DEFAULT NULL, access_hash VARCHAR(255) DEFAULT NULL, updated_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, created_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_note (id INTEGER PRIMARY KEY, support_ticket_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_file (token CHAR(32) NOT NULL, ticket_id BIGINT DEFAULT NULL, message_id BIGINT DEFAULT NULL, PRIMARY KEY (token))');
    $connection->executeStatement('CREATE TABLE support_ticket_secret (id INTEGER PRIMARY KEY, ticket_id BIGINT NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_secret_event (id INTEGER PRIMARY KEY, ticket_id BIGINT NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_message_rating (message_id BIGINT NOT NULL, ticket_id BIGINT NOT NULL, PRIMARY KEY (message_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback (id INTEGER PRIMARY KEY, ticket_id BIGINT NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback_request (ticket_id BIGINT NOT NULL, token CHAR(64) NOT NULL, sent_at DATETIME NOT NULL, answered_at DATETIME DEFAULT NULL, PRIMARY KEY (ticket_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_merge (
        ticket_id BIGINT NOT NULL, merged_into BIGINT NOT NULL, access_hash VARCHAR(255) DEFAULT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT \'\', merged_by BIGINT DEFAULT NULL, merged_at DATETIME NOT NULL,
        PRIMARY KEY (ticket_id))');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();

    return [$di, $connection];
}

function ticketMergeTicket(Doctrine\DBAL\Connection $connection, int $id, int $clientId, string $subject, string $status, string $updatedAt, string $hash = ''): void
{
    $connection->insert('support_ticket', ['id' => $id, 'client_id' => $clientId, 'author_email' => '', 'subject' => $subject, 'status' => $status, 'access_hash' => $hash, 'updated_at' => $updatedAt]);
}

test('ticket ids are cleaned, deduped and sorted', function (): void {
    expect(TicketMerge::ids([12, '3', ' 7 ', 3, 0, -2, 'x', '1.5', null, [4]]))->toBe([3, 7, 12]);
    expect(TicketMerge::ids([]))->toBe([]);
});

test('merge requires two existing tickets of one owner', function (): void {
    [$di, $connection] = ticketMergeConnection();
    ticketMergeTicket($connection, 10, 1, 'A', 'open', '2026-01-01 10:00:00');

    expect(fn (): array => TicketMerge::merge($di, [10], null, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'at least two');
    expect(fn (): array => TicketMerge::merge($di, [10, 999], null, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'was not found');

    ticketMergeTicket($connection, 11, 2, 'B', 'open', '2026-01-01 11:00:00');
    expect(fn (): array => TicketMerge::merge($di, [10, 11], null, 1))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'same client');
    expect($connection->fetchOne('SELECT COUNT(*) FROM support_ticket'))->toBe(2);
});

test('merge keeps the oldest ticket with everything moved', function (): void {
    [$di, $connection] = ticketMergeConnection();
    ticketMergeTicket($connection, 10, 1, 'First', 'open', '2026-01-01 10:00:00');
    ticketMergeTicket($connection, 12, 1, 'Second', 'on_hold', '2026-01-02 10:00:00', 'guesthash');
    $connection->insert('support_ticket_message', ['id' => 1, 'support_ticket_id' => 12, 'created_at' => '2026-01-03 10:00:00']);
    $connection->insert('support_ticket_message', ['id' => 2, 'support_ticket_id' => 10, 'created_at' => '2026-01-01 11:00:00']);
    $connection->insert('support_ticket_note', ['id' => 1, 'support_ticket_id' => 12]);
    $connection->insert('support_ticket_file', ['token' => str_repeat('a', 32), 'ticket_id' => 12, 'message_id' => 1]);
    $connection->insert('support_ticket_secret', ['id' => 1, 'ticket_id' => 12]);
    $connection->insert('support_ticket_secret_event', ['id' => 1, 'ticket_id' => 12]);
    $connection->insert('support_ticket_message_rating', ['message_id' => 1, 'ticket_id' => 12]);
    $connection->insert('support_ticket_feedback', ['id' => 1, 'ticket_id' => 12]);
    $connection->insert('support_ticket_feedback_request', ['ticket_id' => 12, 'token' => str_repeat('b', 64), 'sent_at' => '2026-01-02 10:00:00', 'answered_at' => null]);
    ticketMergeTicket($connection, 13, 1, 'Third', 'open', '2026-01-01 12:00:00');

    $result = TicketMerge::merge($di, [13, 12, 10], null, 7);
    expect($result)->toBe(['id' => 10, 'merged' => [12, 13]]);

    $master = $connection->fetchAssociative('SELECT * FROM support_ticket WHERE id = 10');
    expect($master['subject'])->toBe('First [MERGED]');
    // Status of the ticket with the latest message.
    expect($master['status'])->toBe('on_hold');
    expect($master['updated_at'])->toBe('2026-01-02 10:00:00');
    // A client master keeps its guest link field empty.
    expect($master['access_hash'])->toBe('');

    // Everything moved to the survivor.
    expect($connection->fetchOne('SELECT COUNT(*) FROM support_ticket_message WHERE support_ticket_id = 10'))->toBe(2);
    expect($connection->fetchOne('SELECT support_ticket_id FROM support_ticket_note WHERE id = 1'))->toBe(10);
    expect($connection->fetchOne('SELECT ticket_id FROM support_ticket_file WHERE token = ?', [str_repeat('a', 32)]))->toBe(10);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket'))->toBe(1);

    // Secrets and their events are gone with the source ticket.
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(0);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_secret_event'))->toBe(0);

    // Ratings and feedback followed the replies.
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_message_rating WHERE ticket_id = 10'))->toBe(1);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_feedback WHERE ticket_id = 10'))->toBe(1);

    // The open feedback link of a feedback-free ticket transfers to the survivor.
    // (The main merge inherited feedback, so its links were dropped instead.)
    ticketMergeTicket($connection, 20, 1, 'Solo', 'open', '2026-01-01 09:00:00');
    ticketMergeTicket($connection, 21, 1, 'Solo other', 'open', '2026-01-01 09:30:00');
    $connection->insert('support_ticket_feedback_request', ['ticket_id' => 21, 'token' => str_repeat('c', 64), 'sent_at' => '2026-01-03 10:00:00', 'answered_at' => null]);
    expect(TicketMerge::merge($di, [21, 20], null, 7))->toBe(['id' => 20, 'merged' => [21]]);
    expect($connection->fetchOne('SELECT ticket_id FROM support_ticket_feedback_request WHERE token = ?', [str_repeat('c', 64)]))->toBe(20);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_feedback_request WHERE ticket_id = 21'))->toBe(0);

    // The merge rows resolve old pages to the survivor.
    expect(TicketMerge::target($di, 12))->toBe(10);
    expect(TicketMerge::target($di, 13))->toBe(10);
    expect(TicketMerge::target($di, 10))->toBeNull();
    expect(TicketMerge::targetForClient($di, 12, 1))->toBe(10);
    expect(TicketMerge::targetForClient($di, 12, 2))->toBeNull();
    expect(TicketMerge::mergedInto($di, 10))->toBe([
        ['id' => 12, 'subject' => 'Second', 'merged_at' => $connection->fetchOne('SELECT merged_at FROM support_ticket_merge WHERE ticket_id = 12')],
        ['id' => 13, 'subject' => 'Third', 'merged_at' => $connection->fetchOne('SELECT merged_at FROM support_ticket_merge WHERE ticket_id = 13')],
    ]);

    // Re-merging into an older ticket does not stack the suffix and follows old links.
    ticketMergeTicket($connection, 5, 1, 'Oldest', 'open', '2026-01-01 09:00:00');
    $result = TicketMerge::merge($di, [10, 5], null, 7);
    expect($result)->toBe(['id' => 5, 'merged' => [10]]);
    expect($connection->fetchOne('SELECT subject FROM support_ticket WHERE id = 5'))->toBe('Oldest [MERGED]');
    expect(TicketMerge::target($di, 12))->toBe(5);

    // Late writes to a merged ticket are swept to the survivor.
    $connection->insert('support_ticket_message', ['id' => 3, 'support_ticket_id' => 12, 'created_at' => '2026-01-04 10:00:00']);
    expect(TicketMerge::sweep($di))->toBe(1);
    expect($connection->fetchOne('SELECT support_ticket_id FROM support_ticket_message WHERE id = 3'))->toBe(5);
    expect(TicketMerge::sweep($di))->toBe(0);
});

test('merge honors a typed subject and guest email ownership', function (): void {
    [$di, $connection] = ticketMergeConnection();
    $connection->insert('support_ticket', ['id' => 10, 'client_id' => 0, 'author_email' => 'Guest@X.test', 'subject' => 'A', 'status' => 'open', 'access_hash' => 'oldhash', 'updated_at' => '2026-01-01 10:00:00']);
    $connection->insert('support_ticket', ['id' => 11, 'client_id' => 0, 'author_email' => 'guest@x.test', 'subject' => 'B', 'status' => 'open', 'access_hash' => '', 'updated_at' => '2026-01-01 11:00:00']);

    $result = TicketMerge::merge($di, [10, 11], 'Combined', 7);
    expect($result)->toBe(['id' => 10, 'merged' => [11]]);
    expect($connection->fetchOne('SELECT subject FROM support_ticket WHERE id = 10'))->toBe('Combined');
    // A guest master gets a new guest link; old guest hashes never redirect.
    expect($connection->fetchOne('SELECT access_hash FROM support_ticket WHERE id = 10'))->not->toBe('oldhash');
    expect(TicketMerge::targetForClient($di, 11, 9))->toBeNull();

    // Bare guest addresses (no email) can never merge.
    $connection->insert('support_ticket', ['id' => 12, 'client_id' => 0, 'author_email' => '', 'subject' => 'C', 'status' => 'open', 'access_hash' => '', 'updated_at' => '2026-01-01 12:00:00']);
    expect(fn (): array => TicketMerge::merge($di, [10, 12], null, 7))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'same client');
});

test('merge moves a lone task and refuses a second pending one', function (): void {
    [$di, $connection] = ticketMergeConnection();
    ticketMergeTicket($connection, 10, 1, 'Plain', 'open', '2026-01-01 10:00:00');
    $connection->insert('support_ticket', ['id' => 11, 'client_id' => 1, 'author_email' => '', 'subject' => 'Upgrade', 'status' => 'open', 'rel_type' => 'order', 'rel_id' => 9, 'rel_task' => 'upgrade', 'rel_new_value' => '3', 'rel_status' => 'complete', 'access_hash' => '', 'updated_at' => '2026-01-01 11:00:00']);

    expect(TicketMerge::merge($di, [10, 11], null, 7)['id'])->toBe(10);
    $master = $connection->fetchAssociative('SELECT rel_type, rel_id, rel_task FROM support_ticket WHERE id = 10');
    expect($master)->toMatchArray(['rel_type' => 'order', 'rel_id' => 9, 'rel_task' => 'upgrade']);

    $connection->insert('support_ticket', ['id' => 12, 'client_id' => 1, 'author_email' => '', 'subject' => 'Cancel', 'status' => 'open', 'rel_type' => 'order', 'rel_id' => 8, 'rel_task' => 'cancel', 'rel_new_value' => null, 'rel_status' => 'pending', 'access_hash' => '', 'updated_at' => '2026-01-01 12:00:00']);
    expect(fn (): array => TicketMerge::merge($di, [10, 12], null, 7))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'pending task');
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket'))->toBe(2);
});

test('merge lookups degrade without the table', function (): void {
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andThrow(new RuntimeException('no such table'));
    $di['em'] = $em;

    expect(TicketMerge::target($di, 1))->toBeNull();
    expect(TicketMerge::targetForClient($di, 1, 1))->toBeNull();
    expect(TicketMerge::mergedInto($di, 1))->toBe([]);
});
