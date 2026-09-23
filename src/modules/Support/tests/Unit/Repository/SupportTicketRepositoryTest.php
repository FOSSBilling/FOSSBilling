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
use Box\Mod\Support\Repository\SupportTicketRepository;

test('findByClientId delegates to findBy', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['clientId' => 5])
        ->andReturn([]);

    expect($repo->findByClientId(5))->toBe([]);
});

test('findByIds delegates to findBy', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['id' => [2, 3]])
        ->andReturn([]);

    expect($repo->findByIds([2, 3]))->toBe([]);
});

test('findByIds returns empty array without calling findBy for empty ids', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findBy')->never();

    expect($repo->findByIds([]))->toBe([]);
});

test('hasPendingTaskForClient checks pending task criteria', function (): void {
    $repo = Mockery::mock(SupportTicketRepository::class)->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with([
            'clientId' => 1,
            'relId' => 7,
            'relType' => SupportTicket::REL_TYPE_ORDER,
            'relTask' => SupportTicket::REL_TASK_UPGRADE,
            'relStatus' => SupportTicket::REL_STATUS_PENDING,
        ])
        ->andReturn(new SupportTicket());

    expect($repo->hasPendingTaskForClient(1, 7, SupportTicket::REL_TYPE_ORDER, SupportTicket::REL_TASK_UPGRADE))->toBeTrue();
});

function supportTicketSortEntityManager(): Doctrine\ORM\EntityManager
{
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../../../Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new Doctrine\ORM\EntityManager(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = supportTicketSortEntityManager()->getRepository(SupportTicket::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', t.id');
    } else {
        expect($dql)->not->toContain(', t.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY t.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY t.id DESC', false],
    'status' => [['sort' => 'status'], 'ORDER BY t.status ASC, t.id ASC', true],
    'priority' => [['sort' => 'priority', 'direction' => 'desc'], 'ORDER BY t.priority DESC, t.id DESC', true],
    'subject' => [['sort' => 'subject'], 'ORDER BY t.subject ASC, t.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY t.createdAt ASC, t.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY t.updatedAt ASC, t.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 't.id; DROP TABLE support_ticket'], 'ORDER BY t.id DESC', false],
    'invalid direction falls back to ascending' => [['sort' => 'status', 'direction' => 'sideways'], 'ORDER BY t.status ASC, t.id ASC', true],
]);
