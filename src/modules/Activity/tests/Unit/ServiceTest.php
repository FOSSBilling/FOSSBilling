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
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;

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
    $dbMock = Mockery::mock('Box_Database');

    $di['db'] = $dbMock;
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

test('login events persist history entities', function (string $method, string $entityClass, string $idGetter): void {
    $persisted = null;
    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('persist')->once()->withArgs(function (object $history) use (&$persisted, $entityClass): bool {
        $persisted = $history;

        return $history instanceof $entityClass;
    });
    $entityManager->shouldReceive('flush')->once();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('isExtensionActive')->once()->with('mod', 'demo')->andReturnFalse();

    $di = container();
    $di['em'] = $entityManager;
    $di['mod_service'] = $di->protect(fn (): object => $extensionService);

    $event = new Box_Event(null, $method, ['id' => 7, 'ip' => '192.0.2.1']);
    $event->setDi($di);

    Box\Mod\Activity\Service::{$method}($event);

    expect($persisted)->toBeInstanceOf($entityClass)
        ->and($persisted->{$idGetter}())->toBe(7)
        ->and($persisted->getIp())->toBe('192.0.2.1');
})->with([
    ['onAfterClientLogin', ActivityClientHistory::class, 'getClientId'],
    ['onAfterAdminLogin', ActivityAdminHistory::class, 'getAdminId'],
]);

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

test('remove by client', function (): void {
    $service = new Box\Mod\Activity\Service();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
    $clientModel->id = 1;

    $clientHistoryRepository = Mockery::mock(ActivityClientHistoryRepository::class);
    $clientHistoryRepository->shouldReceive('deleteByClientId')->once()->with(1)->andReturn(1);
    $activitySystemRepository = Mockery::mock(ActivitySystemRepository::class);
    $activitySystemRepository->shouldReceive('deleteByClientId')->once()->with(1)->andReturn(1);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getRepository')->once()->with(ActivityClientHistory::class)->andReturn($clientHistoryRepository);
    $entityManager->shouldReceive('getRepository')->once()->with(ActivitySystem::class)->andReturn($activitySystemRepository);

    $di = container();
    $di['em'] = $entityManager;

    $service->setDi($di);

    $service->rmByClient($clientModel);
});
