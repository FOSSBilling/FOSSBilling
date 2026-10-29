<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Box\Mod\Activity\Entity\ActivityClientHistory;
use Box\Mod\Activity\Entity\ActivitySystem;
use Box\Mod\Activity\Repository\ActivityClientHistoryRepository;
use Box\Mod\Activity\Repository\ActivitySystemRepository;
use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Event\AfterClientLoginEvent;
use Box\Mod\Cron\Event\BeforeAdminCronRunEvent;
use Box\Mod\Staff\Event\AfterAdminLoginEvent;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\setEntityId;

dataset('searchFilters', fn (): array => [
    [[], 'FROM activity_system ', true],
    [['user_filter' => 'only_clients'], 'm.client_id IS NOT NULL', true],
    [['user_filter' => 'only_staff'], 'm.admin_id IS NOT NULL', true],
    [['priority' => '2'], 'm.priority =', true],
    [['search' => 'keyword'], 'm.message LIKE ', true],
    [['min_priority' => 6], 'm.priority <= :min_priority', true],
    [['priority' => 6], 'm.priority = :priority', true],
    // When both priority and min_priority are set, priority takes precedence
    [['priority' => 5, 'min_priority' => 3], 'm.priority = :priority', true],
    [['priority' => 5, 'min_priority' => 3], 'm.priority <= :min_priority', false],
]);

test('dependency injection', function (): void {
    $service = new Box\Mod\Activity\Service();

    $di = container();
    $service->setDi($di);
    $result = $service->getDi();
    expect($result)->toEqual($di);
});

test('get search query', function (array $filterKey, string $search, bool $expected): void {
    $di = container();
    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);
    $result = $service->getSearchQuery($filterKey);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect(str_contains((string) $result[0], $search))->toEqual($expected);
})->with('searchFilters');

test('get search query applies allowlisted sort', function (): void {
    $di = container();
    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);

    [$query] = $service->getSearchQuery(['sort' => 'priority', 'direction' => 'desc']);
    expect($query)->toContain('ORDER BY m.priority DESC, m.id DESC');

    [$pkQuery] = $service->getSearchQuery(['sort' => 'id', 'direction' => 'desc']);
    expect($pkQuery)->toContain('ORDER BY m.id DESC');
    expect($pkQuery)->not->toContain('m.id DESC, m.id DESC');

    [$defaultQuery] = $service->getSearchQuery([]);
    expect($defaultQuery)->toContain('ORDER BY m.id desc');

    [$invalidQuery] = $service->getSearchQuery(['sort' => 'message']);
    expect($invalidQuery)->toContain('ORDER BY m.id desc');
});

test('log event persists a system activity entity', function (): void {
    $persisted = null;
    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('persist')->once()->withArgs(function (ActivitySystem $activity) use (&$persisted): bool {
        $persisted = $activity;

        return true;
    });
    $entityManager->shouldReceive('flush')->once();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('isExtensionActive')->once()->with('mod', 'demo')->andReturnFalse();

    $di = container();
    $di['em'] = $entityManager;
    $di['mod_service'] = $di->protect(fn (): object => $extensionService);

    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);
    $service->logEvent([
        'client_id' => 3,
        'admin_id' => 4,
        'priority' => 5,
        'message' => 'Test event',
    ]);

    expect($persisted)->toBeInstanceOf(ActivitySystem::class)
        ->and($persisted->getClientId())->toBe(3)
        ->and($persisted->getAdminId())->toBe(4)
        ->and($persisted->getPriority())->toBe(5)
        ->and($persisted->getMessage())->toBe('Test event');
});

test('typed client login event persists history', function (): void {
    $persisted = null;
    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('persist')->once()->withArgs(function (ActivityClientHistory $history) use (&$persisted): bool {
        $persisted = $history;

        return true;
    });
    $entityManager->shouldReceive('flush')->once();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('isExtensionActive')->once()->with('mod', 'demo')->andReturnFalse();

    $di = container();
    $di['em'] = $entityManager;
    $di['mod_service'] = $di->protect(fn (): object => $extensionService);

    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);
    $service->recordClientLogin(new AfterClientLoginEvent(7, '192.0.2.1'));

    expect($persisted)->toBeInstanceOf(ActivityClientHistory::class)
        ->and($persisted->getClientId())->toBe(7)
        ->and($persisted->getIp())->toBe('192.0.2.1');
});

test('typed admin login event persists history', function (): void {
    $persisted = null;
    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('persist')->once()->withArgs(function (ActivityAdminHistory $history) use (&$persisted): bool {
        $persisted = $history;

        return true;
    });
    $entityManager->shouldReceive('flush')->once();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('isExtensionActive')->once()->with('mod', 'demo')->andReturnFalse();

    $di = container();
    $di['em'] = $entityManager;
    $di['mod_service'] = $di->protect(fn (): object => $extensionService);

    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);
    $service->recordAdminLogin(new AfterAdminLoginEvent(7, '192.0.2.1'));

    expect($persisted)->toBeInstanceOf(ActivityAdminHistory::class)
        ->and($persisted->getAdminId())->toBe(7)
        ->and($persisted->getIp())->toBe('192.0.2.1');
});

test('logs retention failures through the application logger', function (): void {
    $dbal = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbal->shouldReceive('fetchFirstColumn')->once()->andReturn([1, 2]);
    $dbal->shouldReceive('executeStatement')
        ->once()
        ->andThrow(new RuntimeException('activity cleanup failed'));

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('getConfig')
        ->once()
        ->with('mod_activity')
        ->andReturn(['max_age' => 30, 'email_max_age' => 0]);

    $logger = new Tests\Helpers\TestLogger();
    $di = container();
    $di['dbal'] = $dbal;
    $di['logger'] = $logger;
    $di['mod_service'] = $di->protect(fn (string $name): object => $extensionService);

    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);
    $service->cleanupOldActivity(new BeforeAdminCronRunEvent());

    expect($logger->calls)->toContain([
        'method' => 'error',
        'params' => ['activity cleanup failed'],
    ]);
});

test('retention removes old rows in batches, keeps recent rows and spares tables that are switched off', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($connection);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
        $em->getClassMetadata(ActivityAdminHistory::class),
        $em->getClassMetadata(ActivityClientHistory::class),
        $em->getClassMetadata(ActivitySystem::class),
        $em->getClassMetadata(Box\Mod\Email\Entity\ActivityClientEmail::class),
    ]);

    $old = date('Y-m-d H:i:s', time() - 200 * 86_400);
    $recent = date('Y-m-d H:i:s', time() - 5 * 86_400);
    // More old rows than one batch, so the loop has to run more than once.
    $oldRows = Box\Mod\Activity\Service::RETENTION_BATCH_SIZE * 2 + 17;
    $connection->beginTransaction();
    for ($i = 0; $i < $oldRows; ++$i) {
        $connection->insert('activity_system', ['priority' => 6, 'message' => 'old', 'created_at' => $old]);
    }
    $connection->insert('activity_system', ['priority' => 6, 'message' => 'recent', 'created_at' => $recent]);
    $connection->insert('activity_admin_history', ['admin_id' => 1, 'ip' => '192.0.2.1', 'created_at' => $old]);
    $connection->insert('activity_client_history', ['client_id' => 1, 'ip' => '192.0.2.1', 'created_at' => $old]);
    $connection->insert('activity_client_email', ['client_id' => 1, 'sender' => 's', 'recipients' => 'r', 'subject' => 'x', 'content_html' => 'h', 'content_text' => 't', 'created_at' => $old, 'updated_at' => $old]);
    $connection->commit();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    // Email retention is off (0), so the old email row must survive.
    $extensionService->shouldReceive('getConfig')->with('mod_activity')->andReturn(['max_age' => 90, 'email_max_age' => 0]);
    $di = container();
    $di['dbal'] = $connection;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn (string $name): object => $extensionService);

    $service = new Box\Mod\Activity\Service();
    $service->setDi($di);
    $service->cleanupOldActivity(new BeforeAdminCronRunEvent());

    expect((int) $connection->fetchOne("SELECT COUNT(*) FROM activity_system WHERE message = 'old'"))->toBe(0)
        ->and((int) $connection->fetchOne("SELECT COUNT(*) FROM activity_system WHERE message = 'recent'"))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM activity_admin_history'))->toBe(0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM activity_client_history'))->toBe(0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM activity_client_email'))->toBe(1);
});

test('every activity table has an index on created_at for the retention sweep', function (): void {
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

    foreach ([ActivityAdminHistory::class, ActivityClientHistory::class, ActivitySystem::class, Box\Mod\Email\Entity\ActivityClientEmail::class] as $entity) {
        $metadata = $em->getClassMetadata($entity);
        $indexed = array_filter(
            $metadata->table['indexes'] ?? [],
            static fn (array $index): bool => ($index['columns'][0] ?? null) === 'created_at',
        );
        // Retention deletes by created_at; without an index every cron tick scans the whole table.
        expect($indexed)->not->toBeEmpty("{$metadata->getTableName()} has no created_at index");
    }
});

test('log email', function (): void {
    $service = new Box\Mod\Activity\Service();
    $data = [
        'client_id' => 1,
        'sender' => 'sender',
        'recipients' => 'recipients',
        'subject' => 'subject',
        'content_html' => 'html',
        'content_text' => 'text',
    ];

    $di = container();
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('insert')
        ->once()
        ->with('activity_client_email', Mockery::on(static fn (array $values): bool => $values['client_id'] === $data['client_id']
            && $values['sender'] === $data['sender']
            && $values['recipients'] === $data['recipients']
            && $values['subject'] === $data['subject']
            && $values['content_html'] === $data['content_html']
            && $values['content_text'] === $data['content_text']
            && $values['attachment_name'] === null
            && $values['attachment_content'] === null
            && $values['attachment_mime'] === null
            && isset($values['created_at'])), Mockery::any())
        ->andReturn(1);

    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->logEmail($data['subject'], $data['client_id'], $data['sender'], $data['recipients'], $data['content_html'], $data['content_text']);
    expect($result)->toBeTrue();
});

test('log email stores the given attachment', function (): void {
    $service = new Box\Mod\Activity\Service();
    $data = [
        'client_id' => 1,
        'sender' => 'sender',
        'recipients' => 'recipients',
        'subject' => 'subject',
        'content_html' => 'html',
        'content_text' => 'text',
    ];
    $attachment = [
        'content' => '%PDF-1.4 fake invoice contents',
        'name' => 'Invoice-BB0001.pdf',
        'mime' => 'application/pdf',
    ];

    $di = container();
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('insert')
        ->once()
        ->with('activity_client_email', Mockery::on(static fn (array $values): bool => $values['attachment_name'] === $attachment['name']
            && $values['attachment_content'] === $attachment['content']
            && $values['attachment_mime'] === $attachment['mime']), Mockery::on(static fn (array $types): bool => ($types['attachment_content'] ?? null) === Doctrine\DBAL\Types\Types::BLOB))
        ->andReturn(1);

    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->logEmail($data['subject'], $data['client_id'], $data['sender'], $data['recipients'], $data['content_html'], $data['content_text'], $attachment);
    expect($result)->toBeTrue();
});

test('to api array', function (): void {
    $service = new Box\Mod\Activity\Service();
    $clientHistoryModel = (new ActivityClientHistory())
        ->setClientId(1)
        ->setIp('192.0.2.1')
        ->setCreatedAt(new DateTime('2026-01-01 12:00:00'));

    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAssociative')
        ->once()
        ->andReturn([
            'id' => 1,
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => 'client@example.test',
        ]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->once()
        ->with('SELECT id, first_name, last_name, email FROM client WHERE id = ?', [1])
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $result = $service->toApiArray($clientHistoryModel);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('ip');
    expect($result)->toHaveKey('created_at');
    expect($result['created_at'])->toBe('2026-01-01 12:00:00');

    expect($result['client'])->toBeArray();
    expect($result['client'])->toHaveKey('id');
    expect($result['client'])->toHaveKey('first_name');
    expect($result['client'])->toHaveKey('last_name');
    expect($result['client'])->toHaveKey('email');
});

test('remove by Doctrine client', function (): void {
    $service = new Box\Mod\Activity\Service();
    $client = new Client();
    setEntityId($client, 2);

    $clientHistoryRepository = Mockery::mock(ActivityClientHistoryRepository::class);
    $clientHistoryRepository->shouldReceive('deleteByClientId')->once()->with(2)->andReturn(1);
    $activitySystemRepository = Mockery::mock(ActivitySystemRepository::class);
    $activitySystemRepository->shouldReceive('deleteByClientId')->once()->with(2)->andReturn(1);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getRepository')->once()->with(ActivityClientHistory::class)->andReturn($clientHistoryRepository);
    $entityManager->shouldReceive('getRepository')->once()->with(ActivitySystem::class)->andReturn($activitySystemRepository);

    $di = container();
    $di['em'] = $entityManager;
    $service->setDi($di);

    $service->rmByClient($client);
});

test('remove by client returns early when the client id is null', function (): void {
    $service = new Box\Mod\Activity\Service();
    $client = new Client();

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldNotReceive('getRepository');

    $di = container();
    $di['em'] = $entityManager;
    $service->setDi($di);

    $service->rmByClient($client);
});
