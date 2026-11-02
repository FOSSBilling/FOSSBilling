<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Core\Module;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

use function Tests\Helpers\container;

test('empty config', function (): void {
    $di = container();

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
    $di['url'] = new FOSSBilling\Core\Url();

    $mod = new Module('Cookieconsent');
    $mod->setDi($di);

    $bool = $mod->hasManifest();
    expect($bool)->toBeTrue();

    $array = $mod->getManifest();
    expect($array)->toBeArray();
});

test('hasDeclaredVersion reflects the on-disk manifest', function (string $manifest, bool $expected): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->andReturnTrue();
    $filesystem->shouldReceive('readFile')->andReturn($manifest);

    $di = container();
    $di['filesystem'] = $filesystem;

    // A real Module instance, so the JSON parsing and version checks run
    // for real; only the filesystem is faked to avoid touching src/modules.
    $mod = new Module('testmod');
    $mod->setDi($di);

    expect($mod->hasDeclaredVersion())->toBe($expected);
})->with([
    'declared version' => ['{"name": "Test", "version": "1.0.0"}', true],
    'missing version' => ['{"name": "Test"}', false],
    'non-string version' => ['{"name": "Test", "version": 1}', false],
    'invalid manifest' => ['not json at all', false],
]);

test('hasDeclaredVersion is false when no manifest file exists', function (): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->andReturnFalse();

    $di = container();
    $di['filesystem'] = $filesystem;

    $mod = new Module('testmod');
    $mod->setDi($di);

    expect($mod->hasDeclaredVersion())->toBeFalse();
});

test('hasDeclaredVersion is false when the manifest cannot be read', function (): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->andReturnTrue();
    $filesystem->shouldReceive('readFile')->andThrow(new IOException('unreadable'));

    $di = container();
    $di['filesystem'] = $filesystem;

    $mod = new Module('testmod');
    $mod->setDi($di);

    expect($mod->hasDeclaredVersion())->toBeFalse();
});

test('get service sub', function (): void {
    $mod = new Module('Invoice');
    $subServiceName = 'transaction';

    $di = container();
    $mod->setDi($di);

    $subService = $mod->getService($subServiceName);
    expect($subService)->toBeInstanceOf(Box\Mod\Invoice\ServiceTransaction::class);
});
