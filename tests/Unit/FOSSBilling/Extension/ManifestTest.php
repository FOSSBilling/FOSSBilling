<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Extension\ExtensionType;
use FOSSBilling\Extension\Manifest;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

function writeManifest(array $data): string
{
    $dir = Path::join(sys_get_temp_dir(), 'fossbilling_manifest_' . uniqid());
    (new Filesystem())->mkdir($dir);
    file_put_contents(Path::join($dir, Manifest::FILENAME), json_encode($data));

    return $dir;
}

function validManifest(array $overrides = []): array
{
    return array_merge([
        'id' => 'Stripe',
        'type' => 'gateway',
        'name' => 'Stripe',
        'version' => '1.0.0',
        'api' => Manifest::API_VERSION,
    ], $overrides);
}

test('reads a well formed manifest', function (): void {
    $dir = writeManifest(validManifest(['description' => 'Card payments']));

    $manifest = Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway);

    expect($manifest->id)->toBe('Stripe')
        ->and($manifest->type)->toBe(ExtensionType::Gateway)
        ->and($manifest->version)->toBe('1.0.0')
        ->and($manifest->description)->toBe('Card payments');
});

test('rejects a manifest whose id disagrees with its directory', function (): void {
    $dir = writeManifest(validManifest(['id' => 'SomethingElse']));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects a manifest installed under the wrong type', function (): void {
    $dir = writeManifest(validManifest(['type' => 'manager']));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects an extension built against a different API version', function (): void {
    $dir = writeManifest(validManifest(['api' => Manifest::API_VERSION + 1]));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects a manifest missing a required key', function (): void {
    $data = validManifest();
    unset($data['version']);
    $dir = writeManifest($data);

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects malformed JSON', function (): void {
    $dir = Path::join(sys_get_temp_dir(), 'fossbilling_manifest_' . uniqid());
    (new Filesystem())->mkdir($dir);
    file_put_contents(Path::join($dir, Manifest::FILENAME), '{not json');

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('reports a missing manifest', function (): void {
    $dir = Path::join(sys_get_temp_dir(), 'fossbilling_manifest_' . uniqid());
    (new Filesystem())->mkdir($dir);

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('reads logo, settings and embeddable', function (): void {
    $dir = writeManifest(validManifest([
        'logo' => ['file' => 'stripe.png', 'width' => '65px', 'height' => '30px'],
        'embeddable' => true,
        'settings' => [
            ['name' => 'api_key', 'type' => 'password', 'label' => 'Live Secret Key', 'secret' => true, 'required_when' => ['enabled' => true, 'test_mode' => false]],
            ['name' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'required' => false],
        ],
    ]));

    $manifest = Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway);

    expect($manifest->logo)->toBe(['file' => 'stripe.png', 'width' => '65px', 'height' => '30px'])
        ->and($manifest->embeddable)->toBeTrue()
        ->and($manifest->settings)->toHaveCount(2)
        ->and($manifest->settings[0]['name'])->toBe('api_key')
        ->and($manifest->settings[0]['secret'])->toBeTrue();
});

test('defaults logo, settings and embeddable when absent', function (): void {
    $dir = writeManifest(validManifest());

    $manifest = Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway);

    expect($manifest->logo)->toBeNull()
        ->and($manifest->settings)->toBe([])
        ->and($manifest->embeddable)->toBeFalse();
});

test('rejects a logo without a file', function (): void {
    $dir = writeManifest(validManifest(['logo' => ['width' => '10px']]));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects a settings field missing a name', function (): void {
    $dir = writeManifest(validManifest(['settings' => [['type' => 'text']]]));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects a settings field with an unknown key', function (): void {
    $dir = writeManifest(validManifest(['settings' => [['name' => 'a', 'type' => 'text', 'multiOptions' => []]]]));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('rejects duplicate settings field names', function (): void {
    $dir = writeManifest(validManifest(['settings' => [
        ['name' => 'a', 'type' => 'text'],
        ['name' => 'a', 'type' => 'password'],
    ]]));

    expect(fn () => Manifest::fromDirectory($dir, 'Stripe', ExtensionType::Gateway))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('every bundled extension ships a manifest this version can load', function (): void {
    $loaded = 0;

    foreach (ExtensionType::cases() as $type) {
        foreach (new DirectoryIterator($type->directory()) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $manifest = Manifest::fromDirectory($entry->getPathname(), $entry->getFilename(), $type);
            expect($manifest->name)->not->toBeEmpty();
            ++$loaded;
        }
    }

    expect($loaded)->toBe(17);
});
