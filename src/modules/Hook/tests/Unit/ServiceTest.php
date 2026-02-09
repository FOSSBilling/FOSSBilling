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

beforeEach(function () {
    $this->service = new \Box\Mod\Hook\Service();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets search query', function () {
    [$sql, $params] = $this->service->getSearchQuery([]);

    expect($sql)->toBeString()
        ->and($params)->toBeArray()
        ->and(str_contains($sql, 'SELECT id, rel_type, rel_id, meta_value as event, created_at, updated_at'))->toBeTrue()
        ->and($params)->toBe([]);
});

test('converts to api array', function () {
    $arrMock = ['testing' => 'okey'];
    $result = $this->service->toApiArray($arrMock);
    expect($result)->toBe($arrMock);
});

test('handles on after admin activate extension', function () {
    $eventParams = [
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()
        ->once()
        ->andReturn($eventParams);
    $eventMock->shouldReceive('setReturnValue')->atLeast()->once();

    $model = new \Model_Extension();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->type = 'mod';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()
        ->once()
        ->andReturn($model);

    $hookService = Mockery::mock(\Box\Mod\Hook\Service::class);
    $hookService->shouldReceive('batchConnect')
        ->atLeast()
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $hookService);

    $eventMock->shouldReceive('getDi')
        ->atLeast()
        ->once()
        ->andReturn($di);

    $this->service->setDi($di);
    $this->service->onAfterAdminActivateExtension($eventMock);
    $result = true;
    expect($result)->toBeTrue();
});

test('handles on after admin activate extension with missing id', function () {
    $eventParams = [];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()
        ->once()
        ->andReturn($eventParams);
    $eventMock->shouldReceive('setReturnValue')->atLeast()->once();

    $this->service->onAfterAdminActivateExtension($eventMock);
    $result = false;
    expect($result)->toBeFalse();
});

test('handles on after admin deactivate extension', function () {
    $eventParams = [
        'type' => 'mod',
        'id' => 1,
    ];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()
        ->once()
        ->andReturn($eventParams);
    $eventMock->shouldReceive('setReturnValue')->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $eventMock->shouldReceive('getDi')
        ->atLeast()
        ->once()
        ->andReturn($di);

    $this->service->setDi($di);
    $this->service->onAfterAdminDeactivateExtension($eventMock);
    $result = true;
    expect($result)->toBeTrue();
});

test('batch connects', function () {
    $mod = 'activity';

    $data['mods'] = [$mod];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn(false);

    $extensionModel = new \Model_ExtensionMeta();
    $extensionModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock->shouldReceive('dispense')
        ->atLeast()
        ->once()
        ->andReturn($extensionModel);

    $dbMock->shouldReceive('store')->atLeast()->once();

    $returnArr = [
        [
            'id' => 2,
            'rel_id' => 1,
            'meta_value' => 'testValue',
        ],
    ];
    $dbMock->shouldReceive('getAll')
        ->atLeast()
        ->once()
        ->andReturn($returnArr);

    $activityServiceMock = Mockery::mock(\Box\Mod\Activity\Service::class);

    $boxModMock = Mockery::mock(\FOSSBilling\Module::class);
    $boxModMock->shouldReceive('hasService')
        ->atLeast()
        ->once()
        ->andReturn(true);
    $boxModMock->shouldReceive('getService')
        ->andReturn($activityServiceMock);
    $boxModMock->shouldReceive('getName')
        ->andReturn('activity');

    $extensionServiceMock = Mockery::mock(\Box\Mod\Extension\Service::class);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn () => $boxModMock);
    $di['mod_service'] = $di->protect(function ($name) use ($extensionServiceMock) {
        if ($name == 'extension') {
            return $extensionServiceMock;
        }
    });
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');
    $di['validator'] = $validatorMock;
    $this->service->setDi($di);
    $result = $this->service->batchConnect($mod);
    expect($result)->toBeTrue();
});
