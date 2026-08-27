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

test('gets dependency injection container', function (): void {
    $service = new Box\Mod\Hook\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets search query', function (): void {
    $service = new Box\Mod\Hook\Service();
    [$sql, $params] = $service->getSearchQuery([]);

    expect($sql)->toBeString()
        ->and($params)->toBeArray()
        ->and(str_contains((string) $sql, 'SELECT id, rel_type, rel_id, meta_value as event, created_at, updated_at'))->toBeTrue()
        ->and($params)->toBe([]);
});

test('converts to api array', function (): void {
    $service = new Box\Mod\Hook\Service();
    $arrMock = ['testing' => 'okay'];
    $result = $service->toApiArray($arrMock);
    expect($result)->toBe($arrMock);
});

test('handles on after admin activate extension', function (): void {
    $service = new Box\Mod\Hook\Service();
    $eventParams = [
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $eventMock->shouldReceive('getParameters');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($eventParams);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $eventMock->shouldReceive('setReturnValue');
    $expectation2->atLeast()->once();

    $extension = createEntity(Box\Mod\Extension\Entity\Extension::class, ['id' => 1, 'type' => 'mod', 'name' => 'activity']);

    $extensionRepository = Mockery::mock(Box\Mod\Extension\Repository\ExtensionRepository::class);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $extensionRepository->shouldReceive('find');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($extension);

    $hookService = Mockery::mock(Box\Mod\Hook\Service::class);
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $hookService->shouldReceive('batchConnect');
    $expectation4->atLeast()->once();
    $expectation4->with('activity');

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Extension\Entity\Extension::class)->andReturn($extensionRepository);
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $hookService);

    /** @var Mockery\Expectation $expectation5 */
    $expectation5 = $eventMock->shouldReceive('getDi');
    $expectation5->atLeast()->once();
    $expectation5->andReturn($di);

    $service->setDi($di);
    /* @var \Box_Event $eventMock */
    $service->onAfterAdminActivateExtension($eventMock);
    $result = true;
    expect($result)->toBeTrue();
});

test('handles on after admin activate extension with missing id', function (): void {
    $service = new Box\Mod\Hook\Service();
    $eventParams = [];

    $eventMock = Mockery::mock('\Box_Event');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $eventMock->shouldReceive('getParameters');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($eventParams);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $eventMock->shouldReceive('setReturnValue');
    $expectation2->atLeast()->once();

    /* @var \Box_Event $eventMock */
    $service->onAfterAdminActivateExtension($eventMock);
    $result = false;
    expect($result)->toBeFalse();
});

test('handles on after admin deactivate extension', function (): void {
    $service = new Box\Mod\Hook\Service();
    $eventParams = [
        'type' => 'mod',
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $eventMock->shouldReceive('getParameters');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($eventParams);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $eventMock->shouldReceive('setReturnValue');
    $expectation2->atLeast()->once();

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $connection->shouldReceive('executeStatement');
    $expectation3->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $eventMock->shouldReceive('getDi');
    $expectation4->atLeast()->once();
    $expectation4->andReturn($di);

    $service->setDi($di);
    /* @var \Box_Event $eventMock */
    $service->onAfterAdminDeactivateExtension($eventMock);
    $result = true;
    expect($result)->toBeTrue();
});

test('batch connects', function (): void {
    $service = new Box\Mod\Hook\Service();
    $mod = 'activity';

    $data['mods'] = [$mod];

    $activityServiceMock = Mockery::mock(Box\Mod\Activity\Service::class);

    $boxModMock = Mockery::mock(FOSSBilling\Module::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $boxModMock->shouldReceive('hasService');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(true);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $boxModMock->shouldReceive('getService');
    $expectation2->andReturn($activityServiceMock);
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $boxModMock->shouldReceive('getName');
    $expectation3->andReturn('activity');

    $extensionServiceMock = Mockery::mock(Box\Mod\Extension\Service::class);

    $extensionRepository = Mockery::mock(Box\Mod\Extension\Repository\ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->byDefault()
        ->andReturn(true);

    $di = container();
    $di['mod'] = $di->protect(fn () => $boxModMock);
    $di['mod_service'] = $di->protect(function ($name) use ($extensionServiceMock) {
        if ($name == 'extension') {
            return $extensionServiceMock;
        }
    });
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    /** @var Mockery\Expectation $validatorExpectation */
    $validatorExpectation = $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $validatorExpectation->atLeast()->once();
    $di['validator'] = $validatorMock;

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchOne')
        ->with('SELECT GET_LOCK(:name, 5)', ['name' => 'fossbilling_hook_batch_connect'])
        ->andReturn(1);
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $connectionMock->shouldReceive('fetchOne')
        ->with(Mockery::on(fn ($sql): bool => !str_contains((string) $sql, 'GET_LOCK')), Mockery::any());
    $expectation4->atLeast()->once();
    $expectation4->andReturn(false);
    $connectionMock->shouldReceive('executeStatement')
        ->byDefault();
    $connectionMock->shouldReceive('executeStatement')
        ->with('SELECT RELEASE_LOCK(:name)', ['name' => 'fossbilling_hook_batch_connect'])
        ->once();
    $connectionMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback) => $callback());

    $returnArr = [
        [
            'id' => 2,
            'rel_id' => 1,
            'meta_value' => 'testValue',
        ],
    ];
    /** @var Mockery\Expectation $expectation5 */
    $expectation5 = $connectionMock->shouldReceive('fetchAllAssociative');
    $expectation5->atLeast()->once();
    $expectation5->andReturn($returnArr);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connectionMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Extension\Entity\Extension::class)->andReturn($extensionRepository);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->batchConnect($mod);
    expect($result)->toBeTrue();
});

test('batch connect returns false without rebuilding when the lock is held by another process', function (): void {
    $service = new Box\Mod\Hook\Service();

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT GET_LOCK(:name, 5)', ['name' => 'fossbilling_hook_batch_connect'])
        ->andReturn(0);
    $connectionMock->shouldNotReceive('transactional');
    $connectionMock->shouldNotReceive('executeStatement');
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connectionMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->batchConnect();
    expect($result)->toBeFalse();
});

test('hasConnectedListeners reflects whether any listener row exists', function (bool $exists): void {
    $service = new Box\Mod\Hook\Service();

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchOne')
        ->once()
        ->andReturn($exists ? 1 : false);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connectionMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->hasConnectedListeners())->toBe($exists);
})->with([
    'listeners connected' => [true],
    'no listeners connected' => [false],
]);
