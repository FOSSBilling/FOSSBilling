<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Module;

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
    $di['url'] = new FOSSBilling\Url();

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
    expect($subService)->toBeInstanceOf(Box\Mod\Invoice\ServiceTransaction::class);
});

test('module configuration rejects unreadable or non-document ciphertext consistently', function (string $stored): void {
    $di = container();
    $crypt = new FOSSBilling\Crypt();
    $meta = new Box\Mod\Extension\Entity\ExtensionMeta();
    $meta->setMetaValue($stored === 'tampered' ? $stored : $crypt->encrypt($stored));
    $di['em']->getRepository(Box\Mod\Extension\Entity\ExtensionMeta::class)
        ->shouldReceive('findOneByExtensionAndScope')->with('mod_api', 'config')->andReturn($meta);
    $mod = new Module('api');
    $mod->setDi($di);
    expect(fn () => $mod->getConfig())->toThrow(FOSSBilling\InformationException::class, 'Encrypted extension configuration could not be read');
})->with(['tampered', '{invalid', 'null', '"scalar"']);
