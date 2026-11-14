<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketSecrets;

use function Tests\Helpers\container;

function ticketSecretsConnection(array $config = []): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, client_id INTEGER, status VARCHAR(30))');
    $connection->executeStatement('CREATE TABLE support_ticket_secret (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, kind VARCHAR(10) NOT NULL,
        label VARCHAR(100) NOT NULL, data TEXT NOT NULL, added_by_client_id BIGINT DEFAULT NULL,
        created_at DATETIME NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_secret_event (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, secret_label VARCHAR(100) NOT NULL,
        action VARCHAR(10) NOT NULL, admin_id BIGINT DEFAULT NULL, client_id BIGINT DEFAULT NULL,
        created_at DATETIME NOT NULL)');
    $connection->insert('admin', ['id' => 1, 'name' => 'Test Admin']);
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 5, 'status' => 'open']);
    $connection->insert('support_ticket', ['id' => 61, 'client_id' => 5, 'status' => 'closed']);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['crypt'] = new FOSSBilling\Core\Security\Crypt();
    $di['mod_config'] = $di->protect(fn (): array => $config);

    return [$di, $connection];
}

function ticketSecretsServer(): array
{
    return [
        'host' => 'db.example.com', 'type' => 'ssh', 'username' => 'root',
        'password' => 's3cr3t', 'port' => '22', 'note' => 'main db',
    ];
}

test('ticket secret kinds default on with a week keep', function (): void {
    [$di] = ticketSecretsConnection();
    expect(TicketSecrets::kinds($di))->toBe(['server' => true, 'other' => true, 'days' => 7]);

    [$di] = ticketSecretsConnection(['secrets_server' => '0', 'secrets_other' => '1', 'secrets_days' => '200']);
    expect(TicketSecrets::kinds($di))->toBe(['server' => false, 'other' => true, 'days' => 90]);

    [$di] = ticketSecretsConnection(['secrets_days' => '']);
    expect(TicketSecrets::kinds($di)['days'])->toBe(7);
});

test('ticket secret add stores encrypted values with a label', function (): void {
    [$di, $connection] = ticketSecretsConnection(['secrets_server' => '1']);

    $result = TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer());
    expect($result['id'])->toBeGreaterThan(0);

    $row = $connection->fetchAssociative('SELECT * FROM support_ticket_secret WHERE id = ?', [$result['id']]);
    expect($row['label'])->toBe('SSH db.example.com');
    expect($row['data'])->not->toContain('s3cr3t');
    expect($row['data'])->not->toContain('db.example.com');

    $list = TicketSecrets::listForTicket($di, 60, 5);
    expect($list['secrets'][0])->toBe(['id' => $result['id'], 'kind' => 'server', 'label' => 'SSH db.example.com', 'created_at' => $row['created_at']]);
    expect($list['events'])->toBe([]);

    // Other kinds need a label and a value.
    $other = TicketSecrets::addForClient($di, 5, 60, 'other', ['label' => ' API key ', 'value' => 'abc']);
    expect($other['id'])->toBeGreaterThan(0);
    expect($connection->fetchOne('SELECT label FROM support_ticket_secret WHERE id = ?', [$other['id']]))->toBe('API key');
});

test('ticket secret add refuses closed tickets, guests and bad input', function (): void {
    [$di] = ticketSecretsConnection();

    expect(fn (): array => TicketSecrets::addForClient($di, 5, 61, 'server', ticketSecretsServer()))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'closed ticket');
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 999, 'server', ticketSecretsServer()))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
    expect(fn (): array => TicketSecrets::addForClient($di, 6, 60, 'server', ticketSecretsServer()))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'bogus', []))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'valid kind');

    [$di] = ticketSecretsConnection(['secrets_server' => '0']);
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer()))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'turned off');

    [$di] = ticketSecretsConnection();
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'server', [...ticketSecretsServer(), 'password' => '', 'ssh_key' => '']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'password or an SSH key');
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'server', [...ticketSecretsServer(), 'host' => '']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'host');
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'server', [...ticketSecretsServer(), 'port' => '99999']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'valid port');
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'other', ['label' => 'x', 'value' => '']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'label and a value');

    // Ten secrets is the cap.
    [$di, $connection] = ticketSecretsConnection();
    for ($i = 0; $i < 10; ++$i) {
        $connection->insert('support_ticket_secret', ['ticket_id' => 60, 'kind' => 'other', 'label' => "s$i", 'data' => 'x', 'added_by_client_id' => 5, 'created_at' => date('Y-m-d H:i:s')]);
    }
    expect(fn (): array => TicketSecrets::addForClient($di, 5, 60, 'other', ['label' => 'x', 'value' => 'y']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'At most 10');
});

test('ticket secret open is audited and delete is scoped', function (): void {
    [$di, $connection] = ticketSecretsConnection();

    $added = TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer());
    $fields = TicketSecrets::openForStaff($di, $added['id'], 1);
    expect($fields['password'])->toBe('s3cr3t');
    expect($fields['host'])->toBe('db.example.com');

    $events = TicketSecrets::listForTicket($di, 60)['events'];
    expect($events)->toHaveCount(1);
    expect($events[0]['action'])->toBe('opened');
    expect($events[0]['staff_name'])->toBe('Test Admin');

    // Another client cannot see the ticket at all.
    expect(fn (): array => TicketSecrets::listForTicket($di, 60, 6))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
    expect(fn () => TicketSecrets::delete($di, $added['id'], null, 6))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Secret not found');

    // The owner deletes; the event stays with the client id.
    TicketSecrets::delete($di, $added['id'], null, 5);
    expect(TicketSecrets::listForTicket($di, 60)['secrets'])->toBe([]);
    $deleted = TicketSecrets::listForTicket($di, 60)['events'];
    expect(array_column($deleted, 'action'))->toBe(['opened', 'deleted']);
});

test('ticket close purges values but keeps history; delete purges all', function (): void {
    [$di, $connection] = ticketSecretsConnection();

    $added = TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer());
    TicketSecrets::openForStaff($di, $added['id'], 1);

    TicketSecrets::forgetTicket($connection, 60);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(0);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_secret_event'))->toBe(1);

    $added = TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer());
    TicketSecrets::forgetTicket($connection, 60, true);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM support_ticket_secret_event'))->toBe(0);
    expect($added['id'])->toBeGreaterThan(0);
});

test('ticket secret prune expires old secrets with an event', function (): void {
    [$di, $connection] = ticketSecretsConnection(['secrets_days' => '7']);

    TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer());
    $old = date('Y-m-d H:i:s', time() - 8 * 86400);
    $connection->executeStatement('UPDATE support_ticket_secret SET created_at = ?', [$old]);

    expect(TicketSecrets::prune($di))->toBe(1);
    expect(TicketSecrets::listForTicket($di, 60)['secrets'])->toBe([]);
    expect(TicketSecrets::listForTicket($di, 60)['events'][0]['action'])->toBe('expired');

    // Fresh secrets stay.
    TicketSecrets::addForClient($di, 5, 60, 'server', ticketSecretsServer());
    expect(TicketSecrets::prune($di))->toBe(0);
});
