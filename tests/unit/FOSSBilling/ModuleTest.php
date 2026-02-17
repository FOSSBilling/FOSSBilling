<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Module;
use function Tests\Helpers\container;

test('empty config', function (): void {
    $dbMock = Mockery::mock('Box_Database');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('findOne');
    $expectation->atLeast()->once();
    $expectation->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $mod = new Module('api');
    $mod->setDi($di);
    $array = $mod->getConfig();
    expect($array)->toBe([]);
});

test('core mod', function (): void {
    $mod = new Module('api');
    expect($mod->isCore())->toBeTrue();

    $array = $mod->getCoreModules();
    expect($array)->toBeArray();

    $mod = new Module('Cookieconsent');
    expect($mod->isCore())->toBeFalse();
});

test('manifest', function (): void {
    $di = container();
    $di['url'] = new \Box_Url();

    $mod = new Module('Cookieconsent');
    $mod->setDi($di);

    $bool = $mod->hasManifest();
    expect($bool)->toBeTrue();

    $array = $mod->getManifest();
    expect($array)->toBeArray();
});

test('get service sub', function (): void {
    $mod = new Module('Invoice');
    $subServiceName = 'transaction';

    $di = container();
    $mod->setDi($di);

    $subService = $mod->getService($subServiceName);
    expect($subService)->toBeInstanceOf(\Box\Mod\Invoice\ServiceTransaction::class);
});
