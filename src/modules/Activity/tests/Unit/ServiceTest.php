<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

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
    $clientHistoryModel = createEntity(Box\Mod\Activity\Entity\ActivityClientHistory::class, ['client_id' => 1]);

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
        ->with('SELECT id, first_name, last_name, email FROM client WHERE id = ?', [$clientHistoryModel->client_id])
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $result = $service->toApiArray($clientHistoryModel);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('ip');
    expect($result)->toHaveKey('created_at');

    expect($result['client'])->toBeArray();
    expect($result['client'])->toHaveKey('id');
    expect($result['client'])->toHaveKey('first_name');
    expect($result['client'])->toHaveKey('last_name');
    expect($result['client'])->toHaveKey('email');
});

test('remove by client', function (): void {
    $service = new Box\Mod\Activity\Service();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM activity_client_history WHERE client_id = ?', [$clientModel->id])
        ->andReturn(1);
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM activity_system WHERE client_id = ?', [$clientModel->id])
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $service->rmByClient($clientModel);
});

test('remove by client returns early when the client id is null', function (): void {
    $service = new Box\Mod\Activity\Service();
    $client = new Box\Mod\Client\Entity\Client();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldNotReceive('executeStatement');

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $service->rmByClient($client);
});
