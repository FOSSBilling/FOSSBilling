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
use Box\Mod\Support\TicketLimit;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function ticketLimitDi(array $config, Doctrine\DBAL\Connection $connection): Pimple\Container
{
    $di = container();
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_config'] = $di->protect(fn (): array => $config);

    return $di;
}

function ticketLimitConnection(): Doctrine\DBAL\Connection
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL, created_at DATETIME NOT NULL)');

    return $connection;
}

test('ticket limit is null without a configured limit', function (): void {
    $connection = ticketLimitConnection();
    $client = createEntity(Client::class, ['id' => 1]);

    foreach ([[], ['ticket_limit_max' => 2], ['ticket_limit_max' => 2, 'ticket_limit_days' => 0]] as $config) {
        expect(TicketLimit::status(ticketLimitDi($config, $connection), $client))->toBeNull();
    }
    // No exception either way.
    TicketLimit::check(ticketLimitDi([], $connection), $client);
    expect(true)->toBeTrue();
});

test('ticket limit counts the window and refuses when exhausted', function (): void {
    $connection = ticketLimitConnection();
    $now = date('Y-m-d H:i:s');
    foreach ([1, 2] as $clientId) {
        $connection->insert('support_ticket', ['client_id' => $clientId, 'created_at' => $now]);
    }
    $client = createEntity(Client::class, ['id' => 1]);
    $di = ticketLimitDi(['ticket_limit_max' => 2, 'ticket_limit_days' => 7], $connection);

    $status = TicketLimit::status($di, $client);
    expect($status['max'])->toBe(2);
    expect($status['left'])->toBe(1);

    $connection->insert('support_ticket', ['client_id' => 1, 'created_at' => $now]);
    $status = TicketLimit::status($di, $client);
    expect($status['left'])->toBe(0);

    expect(fn (): int => TicketLimit::check($di, $client) && 1)
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'reached your limit');
});

test('ticket limit ignores other clients and frees aged-out tickets', function (): void {
    $connection = ticketLimitConnection();
    $old = date('Y-m-d H:i:s', time() - 8 * 86400);
    $now = date('Y-m-d H:i:s');
    $connection->insert('support_ticket', ['client_id' => 1, 'created_at' => $old]);
    $connection->insert('support_ticket', ['client_id' => 2, 'created_at' => $now]);
    $client = createEntity(Client::class, ['id' => 1]);
    $di = ticketLimitDi(['ticket_limit_max' => 1, 'ticket_limit_days' => 7], $connection);

    // The only in-window ticket is another client's: one left for client 1.
    expect(TicketLimit::status($di, $client)['left'])->toBe(1);
});
