<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Twig\Extension\FOSSBillingExtension;
use Pimple\Container;

function makeExtension(?Container $di = null): FOSSBillingExtension
{
    $container = $di ?? new Container();
    // Seed an empty loaded_assets array so internal helpers don't blow up.
    $container['loaded_assets'] = [];

    return new FOSSBillingExtension($container);
}

test('publicAssetUrl returns full URL for an asset', function (): void {
    $extension = makeExtension();
    expect($extension->publicAssetUrl('css/app.css'))->toBe(SYSTEM_URL . 'public/assets/css/app.css');
});

test('publicAssetUrl strips leading slashes', function (): void {
    $extension = makeExtension();
    expect($extension->publicAssetUrl('/css/app.css'))->toBe(SYSTEM_URL . 'public/assets/css/app.css');
});

test('publicAssetUrl returns empty string for null', function (): void {
    $extension = makeExtension();
    expect($extension->publicAssetUrl(null))->toBe('');
});

test('daysleft returns integer days for a future date', function (): void {
    $extension = makeExtension();
    $future = date('Y-m-d H:i:s', time() + 86400 * 5);
    expect($extension->daysleft($future))->toBe(5);
});

test('daysleft returns negative integer for a past date', function (): void {
    $extension = makeExtension();
    $past = date('Y-m-d H:i:s', time() - 86400 * 3);
    expect($extension->daysleft($past))->toBe(-3);
});

test('daysleft returns 0 for null', function (): void {
    $extension = makeExtension();
    expect($extension->daysleft(null))->toBe(0);
});

test('fileSize returns formatted string for a size', function (): void {
    $extension = makeExtension();
    expect($extension->fileSize(1024))->toBe(FOSSBilling\Tools::humanReadableBytes(1024));
});

test('fileSize returns empty string for null', function (): void {
    $extension = makeExtension();
    expect($extension->fileSize(null))->toBe('');
});

test('truncate returns text unchanged when shorter than length', function (): void {
    $extension = makeExtension();
    expect($extension->truncate('hello', 10))->toBe('hello');
});

test('truncate shortens text longer than length', function (): void {
    $extension = makeExtension();
    expect($extension->truncate('hello world', 5))->toBe('hello...');
});

test('truncate supports custom suffix', function (): void {
    $extension = makeExtension();
    expect($extension->truncate('hello world', 5, '…'))->toBe('hello…');
});

test('truncate returns empty string for null', function (): void {
    $extension = makeExtension();
    expect($extension->truncate(null))->toBe('');
});

test('wysiwyg returns rendered HTML for a selector', function (): void {
    $extension = makeExtension();
    $output = $extension->wysiwyg('.editor');

    expect($output)->toBeString();
    expect($output)->toContain('FOSSBilling.editor.init');
    expect($output)->toContain('.editor');
});

test('wysiwyg returns empty string for null', function (): void {
    $extension = makeExtension();
    expect($extension->wysiwyg(null))->toBe('');
});

test('scriptTag returns script tag for a path', function (): void {
    $extension = makeExtension();
    $output = $extension->scriptTag('js/app.js');

    expect($output)->toBeString();
    expect($output)->toContain('<script');
    expect($output)->toContain('js/app.js');
});

test('scriptTag returns empty string for null', function (): void {
    $extension = makeExtension();
    expect($extension->scriptTag(null))->toBe('');
});

test('scriptTag returns empty string when asset already loaded', function (): void {
    $extension = makeExtension();
    $extension->scriptTag('js/once.js');
    expect($extension->scriptTag('js/once.js'))->toBe('');
});

test('stylesheetTag returns link tag for a path', function (): void {
    $extension = makeExtension();
    $output = $extension->stylesheetTag('css/app.css');

    expect($output)->toBeString();
    expect($output)->toContain('<link');
    expect($output)->toContain('css/app.css');
});

test('stylesheetTag returns empty string for null path', function (): void {
    $extension = makeExtension();
    expect($extension->stylesheetTag(null))->toBe('');
});

test('stylesheetTag returns empty string when asset already loaded', function (): void {
    $extension = makeExtension();
    $extension->stylesheetTag('css/once.css');
    expect($extension->stylesheetTag('css/once.css'))->toBe('');
});

test('stylesheetTag uses custom media type when provided', function (): void {
    $extension = makeExtension();
    $output = $extension->stylesheetTag('css/print.css', 'print');

    expect($output)->toContain('media="print"');
});

test('hash returns hex digest of value', function (): void {
    $extension = makeExtension();
    $hash = $extension->hash('test@example.com');
    expect($hash)->toBe(hash('xxh128', 'test@example.com'));
});

test('hash casts non-string values to string', function (): void {
    $extension = makeExtension();
    expect($extension->hash(42))->toBe(hash('xxh128', '42'));
});

test('hash throws on unsupported algorithm', function (): void {
    $extension = makeExtension();
    $extension->hash('foo', 'not-a-real-algorithm');
})->throws(InvalidArgumentException::class);
