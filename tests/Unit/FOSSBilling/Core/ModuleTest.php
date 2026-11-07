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
    $mod = new Module('testModule');
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

    $mod = new Module('testModule');
    $mod->setDi($di);

    expect($mod->hasDeclaredVersion())->toBeFalse();
});

test('hasDeclaredVersion is false when the manifest cannot be read', function (): void {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('exists')->andReturnTrue();
    $filesystem->shouldReceive('readFile')->andThrow(new IOException('unreadable'));

    $di = container();
    $di['filesystem'] = $filesystem;

    $mod = new Module('testModule');
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

test('module configuration rejects unreadable or non-document ciphertext consistently', function (string $stored): void {
    $di = container();
    $crypt = new FOSSBilling\Core\Security\Crypt();
    $meta = new Box\Mod\Extension\Entity\ExtensionMeta();
    $meta->setMetaValue($stored === 'tampered' ? $stored : $crypt->encrypt($stored));
    $di['em']->getRepository(Box\Mod\Extension\Entity\ExtensionMeta::class)
        ->shouldReceive('findOneByExtensionAndScope')->with('mod_api', 'config')->andReturn($meta);
    $mod = new Module('api');
    $mod->setDi($di);
    expect(fn () => $mod->getConfig())->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Encrypted extension configuration could not be read');
})->with(['tampered', '{invalid', 'null', '"scalar"']);

test('module icons are discovered by filename without manifest metadata', function (string $format): void {
    $di = container();
    $name = 'Test' . bin2hex(random_bytes(8));
    $directory = PATH_MODS . '/' . $name;
    $filesystem = new Filesystem();
    $filesystem->mkdir($directory);

    try {
        $filesystem->dumpFile($directory . '/manifest.json', json_encode(['name' => 'Icon Test', 'version' => '1.0.0', 'icon_url' => 'ignored.svg'], JSON_THROW_ON_ERROR));
        $filesystem->dumpFile($directory . '/icon.' . $format, 'icon fixture');
        $module = new Module($name);
        $module->setDi($di);
        expect($module->getManifest()['icon_url'])->toBe('/modules/' . $name . '/icon.' . $format);
    } finally {
        $filesystem->remove($directory);
    }
})->with(['svg', 'png', 'webp', 'avif']);

test('modules without convention icons use the generic fallback', function (): void {
    $di = container();
    $name = 'Test' . bin2hex(random_bytes(8));
    $directory = PATH_MODS . '/' . $name;
    $filesystem = new Filesystem();
    $filesystem->mkdir($directory);

    try {
        $filesystem->dumpFile($directory . '/manifest.json', '{"name":"Icon Test","icon_url":"other.svg"}');
        $filesystem->dumpFile($directory . '/other.svg', 'ignored');
        $module = new Module($name);
        $module->setDi($di);
        expect($module->getManifest()['icon_url'])->toBeNull();
    } finally {
        $filesystem->remove($directory);
    }
});
