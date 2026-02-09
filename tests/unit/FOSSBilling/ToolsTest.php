<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

dataset('sanitizeContentProvider', function () {
    return [
        // [input, expected_output, allowSafeHtml]
        ['', '', false],
        ["Hello\0World", 'HelloWorld', false],
        ['<script>alert("xss")</script>', 'alert(&quot;xss&quot;)', false], // Text content HTML-encoded
        ['<p>Hello</p>', 'Hello', false],
        ['<p>Hello</p>', '<p>Hello</p>', true], // Safe HTML preserved
        ['Test <img src="x">', 'Test <img />', true], // img converted to self-closing
    ];
});

test('sanitize content', function (string $input, string $expected, bool $allowSafeHtml): void {
    $result = \FOSSBilling\Tools::sanitizeContent($input, $allowSafeHtml);
    expect($result)->toEqual($expected);
})->with('sanitizeContentProvider');

test('sanitize content removes null bytes', function (): void {
    $input = "Hello\0World";
    $result = \FOSSBilling\Tools::sanitizeContent($input, false);
    expect($result)->not->toContain("\0");
    expect($result)->toBe('HelloWorld');
});

test('sanitize content removes script tags', function (): void {
    $input = '<p>Hello</p><script>alert("xss")</script>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);
    expect($result)->not->toContain('<script>');
    expect($result)->not->toContain('alert');
});

test('sanitize content removes java script urls', function (): void {
    $input = '<a href="javascript:alert(1)">Click</a>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);
    expect($result)->not->toContain('javascript:');
});

test('sanitize content removes event handlers', function (): void {
    $input = '<p onclick="alert(1)">Hello</p>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);
    expect($result)->not->toContain('onclick');
});

test('sanitize content removes multiple event handlers', function (): void {
    $input = '<img src="x" onerror="alert(1)" onload="evil()" onmouseover="bad()">';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);
    expect($result)->not->toContain('onerror');
    expect($result)->not->toContain('onload');
    expect($result)->not->toContain('onmouseover');
});

test('sanitize content allows safe links', function (): void {
    $input = '<a href="https://example.com" title="Example">Link</a>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);
    expect($result)->toContain('href="https://example.com"');
});

test('sanitize content strips unsafe attributes', function (): void {
    $input = '<p style="color:red">Text</p>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);

    // style attribute is not allowed, so it's stripped
    expect($result)->not->toContain('style=');
    expect($result)->toContain('<p>');
    expect($result)->toContain('</p>');
});

test('sanitize content handles nested tags', function (): void {
    $input = '<div><span>nested<span>deep</span></span></div>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, true);
    expect($result)->toContain('<div>');
    expect($result)->toContain('</div>');
});

test('sanitize content strips php tags', function (): void {
    $input = '<?php echo "test"; ?>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, false);
    expect($result)->not->toContain('<?php');
    expect($result)->not->toContain('?>');
});

test('sanitize content strips iframe tags', function (): void {
    $input = '<iframe src="https://evil.com"></iframe>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, false);
    expect($result)->not->toContain('<iframe');
});

test('sanitize content strips object tags', function (): void {
    $input = '<object data="evil.swf"></object>';
    $result = \FOSSBilling\Tools::sanitizeContent($input, false);
    expect($result)->not->toContain('<object');
});

test('sanitize content strips embed tags', function (): void {
    $input = '<embed src="evil.swf">';
    $result = \FOSSBilling\Tools::sanitizeContent($input, false);
    expect($result)->not->toContain('<embed');
});

test('sanitize content preserves text content', function (): void {
    $input = 'Plain text with special chars: < > & " \' /';
    $result = \FOSSBilling\Tools::sanitizeContent($input, false);
    expect($result)->not->toContain('<');
    expect($result)->not->toContain('>');
});
