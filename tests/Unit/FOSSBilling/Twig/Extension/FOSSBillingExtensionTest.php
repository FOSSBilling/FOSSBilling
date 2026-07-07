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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

function makeFossBillingTwigExtension(?Container $di = null): FOSSBillingExtension
{
    $container = $di ?? new Container();
    $container['filesystem'] = new Filesystem();
    // Seed an empty loaded_assets array so internal helpers don't blow up.
    $container['loaded_assets'] = [];

    return new FOSSBillingExtension($container);
}

test('svgSprite uses current_theme global for client environments', function (): void {
    $extension = makeFossBillingTwigExtension();
    $filesystem = new Filesystem();
    $themeCode = 'test_current_theme_sprite';
    $spriteDirectory = Path::join(PATH_THEMES, $themeCode, 'assets/build/symbol');
    $spritePath = Path::join($spriteDirectory, 'icons-sprite.svg');
    $sprite = '<svg><symbol id="icon-test"></symbol></svg>';

    $filesystem->mkdir($spriteDirectory);
    $filesystem->dumpFile($spritePath, $sprite);

    try {
        $env = new Environment(new ArrayLoader([]));
        $env->addGlobal('current_theme', $themeCode);

        expect($extension->svgSprite($env))->toBe($sprite);
    } finally {
        $filesystem->remove(Path::join(PATH_THEMES, $themeCode));
    }
});

test('svgSprite still supports theme code global for admin environments', function (): void {
    $extension = makeFossBillingTwigExtension();
    $filesystem = new Filesystem();
    $themeCode = 'test_theme_code_sprite';
    $spriteDirectory = Path::join(PATH_THEMES, $themeCode, 'assets/build/symbol');
    $spritePath = Path::join($spriteDirectory, 'icons-sprite.svg');
    $sprite = '<svg><symbol id="icon-admin-test"></symbol></svg>';

    $filesystem->mkdir($spriteDirectory);
    $filesystem->dumpFile($spritePath, $sprite);

    try {
        $env = new Environment(new ArrayLoader([]));
        $env->addGlobal('theme', ['code' => $themeCode]);

        expect($extension->svgSprite($env))->toBe($sprite);
    } finally {
        $filesystem->remove(Path::join(PATH_THEMES, $themeCode));
    }
});

test('publicAssetUrl returns full URL for an asset', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->publicAssetUrl('css/app.css'))->toBe(SYSTEM_URL . 'public/assets/css/app.css');
});

test('publicAssetUrl strips leading slashes', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->publicAssetUrl('/css/app.css'))->toBe(SYSTEM_URL . 'public/assets/css/app.css');
});

test('publicAssetUrl returns empty string for null', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->publicAssetUrl(null))->toBe('');
});

test('daysleft returns integer days for a future date', function (): void {
    $extension = makeFossBillingTwigExtension();
    $future = date('Y-m-d H:i:s', time() + 86400 * 5 + 43200);
    expect($extension->daysleft($future))->toBe(5);
});

test('daysleft returns negative integer for a past date', function (): void {
    $extension = makeFossBillingTwigExtension();
    $past = date('Y-m-d H:i:s', time() - 86400 * 3 - 43200);
    expect($extension->daysleft($past))->toBe(-3);
});

test('daysleft returns 0 for null', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->daysleft(null))->toBe(0);
});

test('fileSize returns formatted string for a size', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->fileSize(1024))->toBe(FOSSBilling\Tools::humanReadableBytes(1024));
});

test('fileSize returns empty string for null', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->fileSize(null))->toBe('');
});

test('truncate returns text unchanged when shorter than length', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->truncate('hello', 10))->toBe('hello');
});

test('truncate shortens text longer than length', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->truncate('hello world', 5))->toBe('hello...');
});

test('truncate supports custom suffix', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->truncate('hello world', 5, '~~'))->toBe('hello~~');
});

test('truncate returns empty string for null', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->truncate(null))->toBe('');
});

test('wysiwyg returns rendered HTML for a selector', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->wysiwyg('.editor');

    expect($output)->toBeString();
    expect($output)->toContain('FOSSBilling.editor.init');
    expect($output)->toContain('.editor');
});

test('wysiwyg returns empty string for null', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->wysiwyg(null))->toBe('');
});

test('scriptTag returns script tag for a path', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->scriptTag('js/app.js');

    expect($output)->toBeString();
    expect($output)->toContain('<script');
    expect($output)->toContain('js/app.js');
});

test('scriptTag returns empty string for null', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->scriptTag(null))->toBe('');
});

test('scriptTag returns empty string when asset already loaded', function (): void {
    $extension = makeFossBillingTwigExtension();
    $extension->scriptTag('js/once.js');
    expect($extension->scriptTag('js/once.js'))->toBe('');
});

test('stylesheetTag returns link tag for a path', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->stylesheetTag('css/app.css');

    expect($output)->toBeString();
    expect($output)->toContain('<link');
    expect($output)->toContain('css/app.css');
});

test('stylesheetTag returns empty string for null path', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->stylesheetTag(null))->toBe('');
});

test('stylesheetTag returns empty string when asset already loaded', function (): void {
    $extension = makeFossBillingTwigExtension();
    $extension->stylesheetTag('css/once.css');
    expect($extension->stylesheetTag('css/once.css'))->toBe('');
});

test('stylesheetTag uses custom media type when provided', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->stylesheetTag('css/print.css', 'print');

    expect($output)->toContain('media="print"');
});

test('hash returns hex digest of value', function (): void {
    $extension = makeFossBillingTwigExtension();
    $hash = $extension->hash('test@example.com');
    expect($hash)->toBe(hash('xxh128', 'test@example.com'));
});

test('hash casts non-string values to string', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->hash(42))->toBe(hash('xxh128', '42'));
});

test('hash throws on unsupported algorithm', function (): void {
    $extension = makeFossBillingTwigExtension();
    $extension->hash('foo', 'not-a-real-algorithm');
})->throws(InvalidArgumentException::class);

test('avatar returns empty string for null email', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->avatar(null))->toBe('');
});

test('avatar returns empty string for empty email', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->avatar(''))->toBe('');
    expect($extension->avatar('   '))->toBe('');
});

test('avatar returns escaped fallback for null email', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->avatar(null, fallback: '<script>'))->toBe('&lt;script&gt;');
});

test('avatar ignores fallback for valid email', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com', fallback: '<script>alert(1)</script>');

    expect($output)->toBeString();
    expect($output)->toStartWith('<span class="db-avatar avatar"');
    expect($output)->toEndWith('</span>');
    expect($output)->not->toContain('<script>alert(1)</script>');
    expect($output)->not->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('avatar returns span element with quoted data URI for valid email', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com');

    expect($output)->toBeString();
    expect($output)->toStartWith('<span class="db-avatar avatar"');
    expect($output)->toEndWith('</span>');
    expect($output)->toContain('width: 40px; height: 40px;');
    expect($output)->toContain('background-image: url(&quot;data:image/svg+xml;charset=utf-8,');
    expect($output)->toContain('background-size: 100% 100%;');
    expect($output)->toContain('background-position: center;');
    expect($output)->toContain('background-repeat: no-repeat;');
});

test('avatar quotes the data URI so the unencoded semicolon does not break CSS', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com');

    expect($output)->toContain('url(&quot;data:image/svg+xml;charset=utf-8,');
    expect($output)->not->toContain('url(data:image/svg+xml;charset=utf-8,');
});

test('avatar honors custom size and additional classes', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com', 24, 'avatar avatar-xs rounded');

    expect($output)->toContain('class="db-avatar avatar avatar-xs rounded"');
    expect($output)->toContain('width: 24px; height: 24px;');
});

test('avatar supports a div tag when requested', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com', 60, 'img-thumbnail', null, 'div');

    expect($output)->toStartWith('<div class="db-avatar img-thumbnail"');
    expect($output)->toEndWith('</div>');
});

test('avatar falls back to span for unsupported tag values', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com', 40, 'avatar', null, 'section');

    expect($output)->toStartWith('<span class="db-avatar avatar"');
});

test('avatar html-escapes the class attribute', function (): void {
    $extension = makeFossBillingTwigExtension();
    $output = $extension->avatar('user@example.com', 40, 'a"b');

    expect($output)->toContain('class="db-avatar a&quot;b"');
});

test('avatar clamps non-positive sizes to 1', function (): void {
    $extension = makeFossBillingTwigExtension();
    expect($extension->avatar('user@example.com', 0))->toContain('width: 1px; height: 1px;');
    expect($extension->avatar('user@example.com', -5))->toContain('width: 1px; height: 1px;');
});
