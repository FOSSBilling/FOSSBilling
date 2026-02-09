<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

dataset('searchFilters', function () {
    return [
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
    ];
});

test('dependency injection', function () {
    $service = new \Box\Mod\Activity\Service();

    $di = container();
    $dbMock = Mockery::mock('Box_Database');

    $di['db'] = $dbMock;
    $service->setDi($di);
    $result = $service->getDi();
    expect($result)->toEqual($di);
});

test('get search query', function (array $filterKey, string $search, bool $expected) {
    $di = container();
    $service = new \Box\Mod\Activity\Service();
    $service->setDi($di);
    $result = $service->getSearchQuery($filterKey);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect(str_contains($result[0], $search))->toEqual($expected);
})->with('searchFilters');

test('log email', function () {
    $service = new \Box\Mod\Activity\Service();
    $data = [
        'client_id' => 1,
        'sender' => 'sender',
        'recipients' => 'recipients',
        'subject' => 'subject',
        'content_html' => 'html',
        'content_text' => 'text',
    ];

    $model = new \Model_ActivityClientEmail();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $dbMock = Mockery::mock('Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn([]);

    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->logEmail($data['subject'], $data['client_id'], $data['sender'], $data['recipients'], $data['content_html'], $data['content_text']);
    expect($result)->toBeTrue();
});

test('to api array', function () {
    $clientHistoryModel = new \Model_ActivityClientHistory();
    $clientHistoryModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientHistoryModel->client_id = 1;

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->with('Client', $clientHistoryModel->client_id, 'Client not found')
        ->andReturn($clientModel);

    $di = container();
    $di['db'] = $dbMock;

    $service = new \Box\Mod\Activity\Service();
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

test('remove by client', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->id = 1;

    $activitySystemModel = new \Model_ActivitySystem();
    $activitySystemModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->with('ActivitySystem', 'client_id = ?', [$clientModel->id])
        ->andReturn([$activitySystemModel]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->with($activitySystemModel);

    $di = container();
    $di['db'] = $dbMock;

    $service = new \Box\Mod\Activity\Service();
    $service->setDi($di);

    $service->rmByClient($clientModel);
});
