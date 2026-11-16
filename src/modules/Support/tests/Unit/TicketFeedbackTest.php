<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketFeedback;

use function Tests\Helpers\container;

function ticketFeedbackConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, first_name VARCHAR(255), last_name VARCHAR(255), email VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30), client_id INTEGER, author_name VARCHAR(255), author_email VARCHAR(255), access_hash VARCHAR(255), support_helpdesk_id INTEGER, created_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, admin_id INTEGER, client_id INTEGER, content TEXT, created_at DATETIME)');
    $connection->executeStatement('CREATE TABLE support_ticket_message_rating (
        message_id BIGINT NOT NULL, ticket_id BIGINT NOT NULL, admin_id BIGINT NOT NULL,
        rating SMALLINT NOT NULL, rated_by_kind VARCHAR(10) NOT NULL, rated_by_id BIGINT NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT \'\', rated_at DATETIME NOT NULL, PRIMARY KEY (message_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, admin_id BIGINT DEFAULT NULL,
        rating SMALLINT DEFAULT NULL, comments TEXT NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT \'\',
        created_at DATETIME NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback_request (
        ticket_id BIGINT NOT NULL, token CHAR(64) NOT NULL, sent_at DATETIME NOT NULL,
        answered_at DATETIME DEFAULT NULL, PRIMARY KEY (ticket_id))');
    $connection->executeStatement('CREATE TABLE support_helpdesk_feedback (
        helpdesk_id BIGINT NOT NULL, feedback_request TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL, PRIMARY KEY (helpdesk_id))');
    $connection->insert('admin', ['id' => 1, 'name' => 'Test Admin', 'status' => 'active', 'system_name' => null]);
    $connection->insert('admin', ['id' => 2, 'name' => 'Cron', 'status' => 'active', 'system_name' => 'cron']);
    $connection->insert('admin', ['id' => 3, 'name' => 'Support Agent', 'status' => 'active', 'system_name' => null]);
    $connection->insert('client', ['id' => 5, 'first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com']);
    // Ticket 10: client ticket, closed, staff + cron replies.
    $connection->insert('support_ticket', ['id' => 10, 'subject' => 'Help', 'status' => 'closed', 'client_id' => 5, 'author_name' => 'Alice', 'author_email' => 'alice@example.com', 'access_hash' => '', 'support_helpdesk_id' => 1, 'created_at' => '2026-10-01 10:00:00']);
    $connection->insert('support_ticket_message', ['id' => 101, 'support_ticket_id' => 10, 'admin_id' => null, 'client_id' => 5, 'content' => 'broken', 'created_at' => '2026-10-01 10:00:00']);
    $connection->insert('support_ticket_message', ['id' => 102, 'support_ticket_id' => 10, 'admin_id' => 3, 'client_id' => null, 'content' => 'fixed', 'created_at' => '2026-10-03 13:30:00']);
    $connection->insert('support_ticket_message', ['id' => 103, 'support_ticket_id' => 10, 'admin_id' => 2, 'client_id' => null, 'content' => 'auto close', 'created_at' => '2026-10-03 13:31:00']);
    // Ticket 11: guest ticket, closed.
    $connection->insert('support_ticket', ['id' => 11, 'subject' => 'Guest help', 'status' => 'closed', 'client_id' => 0, 'author_name' => 'Guest Person', 'author_email' => 'guest@mail.test', 'access_hash' => 'guesthash', 'support_helpdesk_id' => 1, 'created_at' => '2026-10-01 10:00:00']);
    // Ticket 12: open ticket.
    $connection->insert('support_ticket', ['id' => 12, 'subject' => 'Open', 'status' => 'open', 'client_id' => 5, 'author_name' => 'Alice', 'author_email' => 'alice@example.com', 'access_hash' => '', 'support_helpdesk_id' => 1, 'created_at' => '2026-10-01 10:00:00']);
    // Ticket 13: closed, no staff reply.
    $connection->insert('support_ticket', ['id' => 13, 'subject' => 'Lonely', 'status' => 'closed', 'client_id' => 5, 'author_name' => 'Alice', 'author_email' => 'alice@example.com', 'access_hash' => '', 'support_helpdesk_id' => 1, 'created_at' => '2026-10-01 10:00:00']);
    $connection->insert('support_ticket_message', ['id' => 130, 'support_ticket_id' => 13, 'admin_id' => null, 'client_id' => 5, 'content' => 'hello?', 'created_at' => '2026-10-01 10:00:00']);

    return [$connection];
}

function ticketFeedbackDi(Doctrine\DBAL\Connection $connection, array $config, array &$emails): Pimple\Container
{
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->andReturnUsing(function (array $data) use (&$emails): bool {
        $emails[] = $data;

        return true;
    });
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $di['mod_config'] = $di->protect(fn (): array => $config);
    $url = Mockery::mock(FOSSBilling\Core\Url::class);
    $url->shouldReceive('link')->andReturnUsing(fn (string $path): string => 'https://billing.example/' . $path);
    $di['url'] = $url;

    return $di;
}

test('reply ratings rate once and reject bad input', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    TicketFeedback::rate($di, 10, 102, '4', 'client', 5, '127.0.0.1');
    expect(TicketFeedback::ratings($di, 10))->toBe([102 => 4]);

    expect(fn () => TicketFeedback::rate($di, 10, 102, 5, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'already rated');
    expect(fn () => TicketFeedback::rate($di, 10, 0, 3, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Only staff replies');
    expect(fn () => TicketFeedback::rate($di, 10, 101, 3, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Only staff replies');
    expect(fn () => TicketFeedback::rate($di, 10, 102, 0, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, '1 to 5');
    expect(fn () => TicketFeedback::rate($di, 10, 102, 6, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, '1 to 5');
    expect(fn () => TicketFeedback::rate($di, 10, 102, 'many', 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, '1 to 5');
});

test('reply ratings can be switched off', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, ['ticket_rating' => 'false'], $emails);

    expect(TicketFeedback::ratingsOn($di))->toBeFalse();
    expect(fn () => TicketFeedback::rate($di, 10, 102, 3, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'switched off');

    $di = ticketFeedbackDi($connection, [], $emails);
    expect(TicketFeedback::ratingsOn($di))->toBeTrue();
});

test('closing a ticket asks for feedback once, reusing the link', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    TicketFeedback::afterClose($di, 10);
    expect($emails)->toHaveCount(1);
    expect($emails[0]['code'])->toBe('mod_support_ticket_feedback_request');
    expect($emails[0]['to_client'])->toBe(5);
    $urlToken = (string) substr((string) $emails[0]['feedback_url'], -64);
    expect(preg_match('/^[a-f0-9]{64}$/', $urlToken))->toBe(1);
    expect(TicketFeedback::openLink($di, 10))->toBe($emails[0]['feedback_url']);

    // A second close resends with the same link.
    TicketFeedback::afterClose($di, 10);
    expect($emails)->toHaveCount(2);
    expect($emails[1]['feedback_url'])->toBe($emails[0]['feedback_url']);

    // Guests get the link by email.
    TicketFeedback::afterClose($di, 11);
    expect($emails[2]['to'])->toBe('guest@mail.test');
    expect($emails[2]['to_name'])->toBe('Guest Person');

    // Open tickets and feedback-switched-off helpdesks get nothing.
    TicketFeedback::afterClose($di, 12);
    TicketFeedback::saveHelpdesk($di, 1, '0');
    expect(TicketFeedback::helpdeskOn($di, 1))->toBeFalse();
    TicketFeedback::afterClose($di, 11);
    expect($emails)->toHaveCount(3);
    TicketFeedback::saveHelpdesk($di, 1, '1');
    expect(TicketFeedback::helpdeskOn($di, 1))->toBeTrue();
});

test('feedback page and submit round-trip', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);
    $token = (function () use ($connection, $di): string {
        TicketFeedback::afterClose($di, 10);

        return (string) $connection->fetchOne('SELECT token FROM support_ticket_feedback_request WHERE ticket_id = 10');
    })();

    $page = TicketFeedback::page($di, $token);
    expect($page['state'])->toBe('form');
    expect($page['staff'])->toBe([3 => 'Support Agent']);
    expect($page['ticket']['duration'])->toBe(['days' => 2, 'hours' => 3, 'minutes' => 31]);
    expect($page['ticket']['opened_at'])->toBe('2026-10-01 10:00:00');
    expect($page['ticket']['last_reply_at'])->toBe('2026-10-03 13:31:00');

    expect(fn () => TicketFeedback::submit($di, $token, [3 => 11], [], null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Support Agent');

    // Staff scores without a generic comment are fine when staff replied.
    TicketFeedback::submit($di, $token, [3 => 9], [3 => 'Quick and clear'], null);
    $feedback = TicketFeedback::forTicket($di, 10);
    expect($feedback['staff'])->toBe([['admin_id' => 3, 'name' => 'Support Agent', 'rating' => 9, 'comments' => 'Quick and clear']]);
    expect($feedback['comments'])->toBe('');
    expect(TicketFeedback::page($di, $token)['state'])->toBe('done');
    expect(TicketFeedback::openLink($di, 10))->toBeNull();

    // Answered links cannot be reused.
    expect(fn () => TicketFeedback::submit($di, $token, [3 => 9], [], null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'already given');
    expect(fn () => TicketFeedback::page($di, 'nope'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'not valid');
    expect(fn () => TicketFeedback::page($di, str_repeat('0', 64)))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'not valid');
});

test('feedback without staff replies only takes the generic comment', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    TicketFeedback::afterClose($di, 13);
    $token = (string) $connection->fetchOne('SELECT token FROM support_ticket_feedback_request WHERE ticket_id = 13');
    $page = TicketFeedback::page($di, $token);
    expect($page['staff'])->toBe([]);
    expect(fn () => TicketFeedback::submit($di, $token, [], [], null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'improve');

    TicketFeedback::submit($di, $token, [], ['generic' => 'Nobody answered'], null);
    expect(TicketFeedback::forTicket($di, 13)['comments'])->toBe('Nobody answered');
});

test('open tickets show the open state and never ask', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    $connection->insert('support_ticket_feedback_request', ['ticket_id' => 12, 'token' => str_repeat('a', 64), 'sent_at' => date('Y-m-d H:i:s'), 'answered_at' => null]);
    $page = TicketFeedback::page($di, str_repeat('a', 64));
    expect($page['state'])->toBe('open');
    expect(fn () => TicketFeedback::submit($di, str_repeat('a', 64), [], ['generic' => 'x'], null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'once the ticket is closed');
});

test('feedback report aggregates ratings, scores and comments', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    TicketFeedback::rate($di, 10, 102, 5, 'client', 5, null);
    $connection->insert('support_ticket_message', ['id' => 104, 'support_ticket_id' => 10, 'admin_id' => 1, 'client_id' => null, 'content' => 'also fixed', 'created_at' => '2026-10-02 10:00:00']);
    TicketFeedback::rate($di, 10, 104, 4, 'client', 5, null);
    $connection->insert('support_ticket', ['id' => 14, 'subject' => 'Other', 'status' => 'closed', 'client_id' => 5, 'author_name' => 'Alice', 'author_email' => 'alice@example.com', 'access_hash' => '', 'support_helpdesk_id' => 1, 'created_at' => '2026-10-01 10:00:00']);
    $connection->insert('support_ticket_message', ['id' => 140, 'support_ticket_id' => 14, 'admin_id' => 1, 'client_id' => null, 'content' => 'ok', 'created_at' => '2026-10-02 10:00:00']);
    TicketFeedback::rate($di, 14, 140, 2, 'client', 5, null);

    $token = (function () use ($connection, $di): string {
        TicketFeedback::afterClose($di, 10);

        return (string) $connection->fetchOne('SELECT token FROM support_ticket_feedback_request WHERE ticket_id = 10');
    })();
    TicketFeedback::submit($di, $token, [3 => 10, 1 => 7], [3 => 'Great', 1 => 'Polite', 'generic' => ''], null);

    $report = TicketFeedback::report($di, 'all');
    expect($report['period'])->toBe('all');

    $byAdmin = [];
    foreach ($report['ratings'] as $row) {
        $byAdmin[$row['admin_id']] = $row;
    }
    expect($byAdmin[3]['total'])->toBe(1);
    expect($byAdmin[3]['average'])->toBe(5.0);
    expect($byAdmin[3]['stars'])->toBe([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1]);
    expect($byAdmin[1]['total'])->toBe(2);
    expect($byAdmin[1]['average'])->toBe(3.0);

    $scores = [];
    foreach ($report['feedback'] as $row) {
        $scores[$row['admin_id']] = $row;
    }
    expect($scores[3]['average'])->toBe(10.0);
    expect($scores[1]['average'])->toBe(7.0);
    expect(array_column($report['comments'], 'comments'))->toBe(['Polite', 'Great']);

    $ninety = TicketFeedback::report($di, 'bogus');
    expect($ninety['period'])->toBe('90');
});

test('feedback sweep drops marks of deleted tickets', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    TicketFeedback::rate($di, 10, 102, 5, 'client', 5, null);
    TicketFeedback::afterClose($di, 10);

    $connection->delete('support_ticket', ['id' => 10]);
    expect(TicketFeedback::sweep($di))->toBe(2);
    expect(TicketFeedback::ratings($di, 10))->toBe([]);
    expect(TicketFeedback::forTicket($di, 10))->toBeNull();
});

test('feedback moves with merged tickets', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);

    TicketFeedback::rate($di, 10, 102, 5, 'client', 5, null);
    TicketFeedback::afterClose($di, 10);
    $token = (string) $connection->fetchOne('SELECT token FROM support_ticket_feedback_request WHERE ticket_id = 10');
    $connection->insert('support_ticket', ['id' => 20, 'subject' => 'Master', 'status' => 'open', 'client_id' => 5, 'author_name' => 'Alice', 'author_email' => 'alice@example.com', 'access_hash' => '', 'support_helpdesk_id' => 1, 'created_at' => '2026-10-01 10:00:00']);

    TicketFeedback::moveForMerge($connection, 20, [10]);
    expect($connection->fetchOne('SELECT token FROM support_ticket_feedback_request WHERE ticket_id = 20'))->toBe($token);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_message_rating WHERE ticket_id = 20'))->toBe(1);

    TicketFeedback::moveForMerge($connection, 20, []);
    expect(TicketFeedback::sweep($di))->toBe(0);
});

test('feedback is rejected if the ticket reopens while the submission is prepared', function (): void {
    [$connection] = ticketFeedbackConnection();
    $token = str_repeat('a', 64);
    $connection->insert('support_ticket_feedback_request', ['ticket_id' => 10, 'token' => $token, 'sent_at' => '2026-10-01 00:00:00', 'answered_at' => null]);
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $calls = 0;
    $em->shouldReceive('getConnection')->andReturnUsing(function () use ($connection, &$calls): Doctrine\DBAL\Connection {
        if (++$calls === 3) {
            $connection->executeStatement("UPDATE support_ticket SET status = 'open' WHERE id = 10");
        }

        return $connection;
    });
    $di['em'] = $em;

    expect(fn () => TicketFeedback::submit($di, $token, [3 => 9], [], null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'once the ticket is closed');
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_feedback'))->toBe(0)
        ->and($connection->fetchOne('SELECT answered_at FROM support_ticket_feedback_request WHERE ticket_id = 10'))->toBeNull();
});

test('rating refuses a reply moved before the ticket lock is acquired', function (): void {
    [$connection] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($connection, [], $emails);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->once()->andReturnUsing(function () use ($connection): Doctrine\DBAL\Connection {
        $connection->executeStatement('UPDATE support_ticket_message SET support_ticket_id = 12 WHERE id = 102');

        return $connection;
    });
    $di['em'] = $em;

    expect(fn () => TicketFeedback::rate($di, 10, 102, 4, 'client', 5, null))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Only staff replies');
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_message_rating'))->toBe(0);
});

test('close-event delivery retries a failed feedback setting lookup without bypassing opt-out', function (): void {
    [$db] = ticketFeedbackConnection();
    $emails = [];
    $di = ticketFeedbackDi($db, [], $emails);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('flush')->zeroOrMoreTimes();
    $em->shouldReceive('isOpen')->andReturnTrue();
    $em->shouldReceive('clear')->zeroOrMoreTimes();
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $db->transactional(fn (): mixed => $fn()));
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $db->executeStatement('CREATE TABLE support_ticket_close_event (id TEXT PRIMARY KEY, ticket_id INTEGER, actor TEXT, actor_id INTEGER, created_at TEXT, attempted_at TEXT, notification_queued_at TEXT, feedback_queued_at TEXT, delivered_at TEXT)');
    $db->insert('support_helpdesk_feedback', ['helpdesk_id' => 1, 'feedback_request' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    $id = Box\Mod\Support\TicketCloseOutbox::enqueue($db, 10, Box\Mod\Support\Event\TicketActorRole::CLIENT, 5);
    $db->executeStatement('ALTER TABLE support_helpdesk_feedback RENAME TO feedback_temporarily_unavailable');
    expect(Box\Mod\Support\TicketCloseOutbox::deliver($di, $id))->toBeFalse()
        ->and($db->fetchOne('SELECT feedback_queued_at FROM support_ticket_close_event WHERE id = ?', [$id]))->toBeNull()
        ->and($emails)->toBeEmpty();
    $db->executeStatement('ALTER TABLE feedback_temporarily_unavailable RENAME TO support_helpdesk_feedback');
    expect(Box\Mod\Support\TicketCloseOutbox::deliver($di, $id))->toBeTrue()
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$id]))->not->toBeNull()
        ->and($emails)->toBeEmpty()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_feedback_request WHERE ticket_id = 10'))->toBe(0);
});
