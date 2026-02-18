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
        ->and(str_contains($sql, 'SELECT id, rel_type, rel_id, meta_value as event, created_at, updated_at'))->toBeTrue()
        ->and($params)->toBe([]);
});

test('converts to api array', function (): void {
    $service = new Box\Mod\Hook\Service();
    $arrMock = ['testing' => 'okey'];
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

    $model = new Model_Extension();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->type = 'mod';

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('load');
    $expectation3->atLeast()->once();
    $expectation3->andReturn($model);

    $hookService = Mockery::mock(Box\Mod\Hook\Service::class);
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $hookService->shouldReceive('batchConnect');
    $expectation4->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('exec');
    $expectation3->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

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

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $dbMock->shouldReceive('getCell');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(false);

    $extensionModel = new Model_ExtensionMeta();
    $extensionModel->loadBean(new Tests\Helpers\DummyBean());

    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbMock->shouldReceive('dispense');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($extensionModel);

    /** @var Mockery\Expectation $expectation3 */
    $expectation3 = $dbMock->shouldReceive('store');
    $expectation3->atLeast()->once();

    $returnArr = [
        [
            'id' => 2,
            'rel_id' => 1,
            'meta_value' => 'testValue',
        ],
    ];
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $dbMock->shouldReceive('getAll');
    $expectation4->atLeast()->once();
    $expectation4->andReturn($returnArr);

    $activityServiceMock = Mockery::mock(Box\Mod\Activity\Service::class);

    $boxModMock = Mockery::mock(FOSSBilling\Module::class);
    /** @var Mockery\Expectation $expectation5 */
    $expectation5 = $boxModMock->shouldReceive('hasService');
    $expectation5->atLeast()->once();
    $expectation5->andReturn(true);
    /** @var Mockery\Expectation $expectation6 */
    $expectation6 = $boxModMock->shouldReceive('getService');
    $expectation6->andReturn($activityServiceMock);
    /** @var Mockery\Expectation $expectation7 */
    $expectation7 = $boxModMock->shouldReceive('getName');
    $expectation7->andReturn('activity');

    $extensionServiceMock = Mockery::mock(Box\Mod\Extension\Service::class);

    $di = container();
    $di['db'] = $dbMock;
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
    $service->setDi($di);
    $result = $service->batchConnect($mod);
    expect($result)->toBeTrue();
});
