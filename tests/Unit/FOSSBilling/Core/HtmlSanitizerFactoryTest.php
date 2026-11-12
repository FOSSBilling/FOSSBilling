<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Core\HtmlSanitizerFactory;

dataset('sanitizeContentProvider', fn (): array => [
    // [input, expected_output, allowSafeHtml]
    ['', '', false],
    ["Hello\0World", 'HelloWorld', false],
    ['<script>alert("xss")</script>', 'alert(&quot;xss&quot;)', false], // Text content HTML-encoded
    ['<p>Hello</p>', 'Hello', false],
    ['<p>Hello</p>', '<p>Hello</p>', true], // Safe HTML preserved
    ['Test <img src="x">', 'Test <img />', true], // img converted to self-closing
]);

test('sanitize content', function (string $input, string $expected, bool $allowSafeHtml): void {
    $result = HtmlSanitizerFactory::sanitizeContent($input, $allowSafeHtml);
    expect($result)->toEqual($expected);
})->with('sanitizeContentProvider');

test('sanitize content removes null bytes', function (): void {
    $input = "Hello\0World";
    $result = HtmlSanitizerFactory::sanitizeContent($input, false);
    expect($result)->not->toContain("\0");
    expect($result)->toBe('HelloWorld');
});

test('sanitize content removes script tags', function (): void {
    $input = '<p>Hello</p><script>alert("xss")</script>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);
    expect($result)->not->toContain('<script>');
    expect($result)->not->toContain('alert');
});

test('sanitize content removes java script urls', function (): void {
    $input = '<a href="javascript:alert(1)">Click</a>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);
    expect($result)->not->toContain('javascript:');
});

test('sanitize content removes event handlers', function (): void {
    $input = '<p onclick="alert(1)">Hello</p>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);
    expect($result)->not->toContain('onclick');
});

test('sanitize content removes multiple event handlers', function (): void {
    $input = '<img src="x" onerror="alert(1)" onload="evil()" onmouseover="bad()">';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);
    expect($result)->not->toContain('onerror');
    expect($result)->not->toContain('onload');
    expect($result)->not->toContain('onmouseover');
});

test('sanitize content allows safe links', function (): void {
    $input = '<a href="https://example.com" title="Example">Link</a>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);
    expect($result)->toContain('href="https://example.com"');
});

test('sanitize content strips unsafe attributes', function (): void {
    $input = '<p style="color:red">Text</p>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);

    // style attribute is not allowed, so it's stripped
    expect($result)->not->toContain('style=');
    expect($result)->toContain('<p>');
    expect($result)->toContain('</p>');
});

test('sanitize content handles nested tags', function (): void {
    $input = '<div><span>nested<span>deep</span></span></div>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, true);
    expect($result)->toContain('<div>');
    expect($result)->toContain('</div>');
});

test('sanitize content strips php tags', function (): void {
    $input = '<?php echo "test"; ?>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, false);
    expect($result)->not->toContain('<?php');
    expect($result)->not->toContain('?>');
});

test('sanitize content strips iframe tags', function (): void {
    $input = '<iframe src="https://evil.com"></iframe>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, false);
    expect($result)->not->toContain('<iframe');
});

test('sanitize content strips object tags', function (): void {
    $input = '<object data="evil.swf"></object>';
    $result = HtmlSanitizerFactory::sanitizeContent($input, false);
    expect($result)->not->toContain('<object');
});

test('sanitize content strips embed tags', function (): void {
    $input = '<embed src="evil.swf">';
    $result = HtmlSanitizerFactory::sanitizeContent($input, false);
    expect($result)->not->toContain('<embed');
});

test('sanitize content preserves text content', function (): void {
    $input = 'Plain text with special chars: < > & " \' /';
    $result = HtmlSanitizerFactory::sanitizeContent($input, false);
    expect($result)->not->toContain('<');
    expect($result)->not->toContain('>');
});

test('sanitize plain text preserves characters without storing HTML entities', function (): void {
    $input = " What's <strong>new</strong> & useful\0 ";
    $result = HtmlSanitizerFactory::sanitizePlainText($input);

    expect($result)->toBe("What's new & useful");
});

test('sanitize markdown content preserves angle brackets as literal text', function (): void {
    $input = 'Hello <World> this text must not disappear';
    $result = HtmlSanitizerFactory::sanitizeMarkdownContent($input);
    expect($result)->toBe('Hello <World> this text must not disappear');
});

test('sanitize markdown content preserves comparison operators', function (): void {
    $input = 'Value must be 1 < x < 10 and y > 0';
    $result = HtmlSanitizerFactory::sanitizeMarkdownContent($input);
    expect($result)->toBe('Value must be 1 < x < 10 and y > 0');
});

test('sanitize markdown content removes null bytes', function (): void {
    $result = HtmlSanitizerFactory::sanitizeMarkdownContent("Hello\0World");
    expect($result)->not->toContain("\0");
    expect($result)->toBe('HelloWorld');
});

test('sanitize markdown content returns empty string for empty input', function (): void {
    expect(HtmlSanitizerFactory::sanitizeMarkdownContent(''))->toBe('');
});

test('sanitize markdown content trims surrounding whitespace', function (): void {
    $result = HtmlSanitizerFactory::sanitizeMarkdownContent("  Hello World  \n");
    expect($result)->toBe('Hello World');
});

test('sanitize markdown content preserves markdown bold syntax', function (): void {
    $input = 'This is **bold** and _italic_ text';
    $result = HtmlSanitizerFactory::sanitizeMarkdownContent($input);
    expect($result)->toBe($input);
});

test('sanitize markdown content preserves markdown inline code', function (): void {
    $input = 'Use `git clone` or `<tag>` syntax';
    $result = HtmlSanitizerFactory::sanitizeMarkdownContent($input);
    expect($result)->toBe($input);
});

test('theme settings sanitizer does not truncate input over the default length limit', function (): void {
    // Regression test for https://github.com/FOSSBilling/FOSSBilling/issues/4664:
    // translated theme settings pages can exceed Symfony's default 20,000-byte
    // input limit, which truncated the rendered form and made the page report
    // the theme as not configurable.
    $field = '<input type="text" name="setting" value="' . str_repeat('a', 200) . '" />';
    $html = str_repeat($field, 200) . '<input type="hidden" name="last_field" value="end" />';
    expect(strlen($html))->toBeGreaterThan(20000);

    $result = HtmlSanitizerFactory::sanitize($html, 'theme_settings');

    expect($result)->not->toBe('')
        ->and($result)->toContain('last_field');
});

test('content sanitizer does not truncate input over the default length limit', function (): void {
    $paragraph = '<p>' . str_repeat('a', 500) . '</p>';
    $html = str_repeat($paragraph, 100) . '<p>final_paragraph_marker</p>';
    expect(strlen($html))->toBeGreaterThan(20000);

    $result = HtmlSanitizerFactory::sanitize($html);

    expect($result)->not->toBe('')
        ->and($result)->toContain('final_paragraph_marker');
});
