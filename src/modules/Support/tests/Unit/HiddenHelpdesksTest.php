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
use Box\Mod\Support\HiddenHelpdesks;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function hiddenHelpdesksConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_helpdesk_hidden (helpdesk_id BIGINT NOT NULL, hidden_at DATETIME NOT NULL, PRIMARY KEY (helpdesk_id))');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;

    return [$di, $connection];
}

test('hidden helpdesks start visible and round-trip', function (): void {
    [$di] = hiddenHelpdesksConnection();

    expect(HiddenHelpdesks::ids($di))->toBe([]);
    expect(HiddenHelpdesks::isHidden($di, 3))->toBeFalse();

    HiddenHelpdesks::save($di, 3, '1');
    HiddenHelpdesks::save($di, 3, true);
    expect(HiddenHelpdesks::ids($di))->toBe([3]);
    expect(HiddenHelpdesks::isHidden($di, 3))->toBeTrue();

    HiddenHelpdesks::save($di, 2, '0');
    expect(HiddenHelpdesks::ids($di))->toBe([3]);

    expect(HiddenHelpdesks::visiblePairs($di, [2 => 'Billing', 3 => 'Hidden']))->toBe([2 => 'Billing']);

    HiddenHelpdesks::forget($di, 3);
    expect(HiddenHelpdesks::ids($di))->toBe([]);
});

test('hidden helpdesk flags annotate staff rows', function (): void {
    [$di] = hiddenHelpdesksConnection();
    HiddenHelpdesks::save($di, 3, true);

    $rows = HiddenHelpdesks::withFlags($di, [['id' => 2, 'name' => 'Billing'], ['id' => 3, 'name' => 'Hidden']]);
    expect($rows[0]['hidden'])->toBeFalse();
    expect($rows[1]['hidden'])->toBeTrue();
});

test('hidden helpdesk reads degrade to visible without the table', function (): void {
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andThrow(new RuntimeException('no such table'));
    $di['em'] = $em;

    expect(HiddenHelpdesks::ids($di))->toBe([]);
    expect(HiddenHelpdesks::isHidden($di, 3))->toBeFalse();
    expect(HiddenHelpdesks::visiblePairs($di, [3 => 'Hidden']))->toBe([3 => 'Hidden']);
});

test('client ticket create refuses a hidden helpdesk', function (): void {
    [$di, $connection] = hiddenHelpdesksConnection();
    $connection->insert('support_helpdesk_hidden', ['helpdesk_id' => 9, 'hidden_at' => date('Y-m-d H:i:s')]);

    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $clientApi->setDi($di);
    $client = createEntity(Client::class, ['id' => 1]);
    $clientApi->setIdentity($client);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $helpdeskRepo = Mockery::mock(Box\Mod\Support\Repository\HelpdeskRepository::class);
    $hidden = new Box\Mod\Support\Entity\Helpdesk();
    $hidden->setName('Hidden');
    Tests\Helpers\setEntityId($hidden, 9);
    $helpdeskRepo->shouldReceive('find')->with(9)->andReturn($hidden);
    $serviceMock->shouldReceive('getHelpdeskRepository')->andReturn($helpdeskRepo);
    $clientApi->setService($serviceMock);

    expect(fn (): int => $clientApi->ticket_create(['content' => 'x', 'subject' => 'y', 'support_helpdesk_id' => 9]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Helpdesk invalid');
});
