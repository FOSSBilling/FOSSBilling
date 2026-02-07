<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Unit\FOSSBilling;

require_once __DIR__ . '/../../../src/load.php';
require_once __DIR__ . '/../../../src/vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ToolsTest extends TestCase
{
    public static function sanitizeContentProvider(): array
    {
        return [
            // [input, expected_output, allowSafeHtml]
            ['', '', false],
            ["Hello\0World", 'HelloWorld', false],
            ['<script>alert("xss")</script>', 'alert(&quot;xss&quot;)', false], // Text content HTML-encoded
            ['<p>Hello</p>', 'Hello', false],
            ['<p>Hello</p>', '<p>Hello</p>', true], // Safe HTML preserved
            ['Test <img src="x">', 'Test <img />', true], // img converted to self-closing
        ];
    }

    #[DataProvider('sanitizeContentProvider')]
    public function testSanitizeContent(string $input, string $expected, bool $allowSafeHtml): void
    {
        $result = \FOSSBilling\Tools::sanitizeContent($input, $allowSafeHtml);
        $this->assertEquals($expected, $result);
    }

    public function testSanitizeContentRemovesNullBytes(): void
    {
        $input = "Hello\0World";
        $result = \FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString("\0", $result);
        $this->assertSame('HelloWorld', $result);
    }

    public function testSanitizeContentRemovesScriptTags(): void
    {
        $input = '<p>Hello</p><script>alert("xss")</script>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testSanitizeContentRemovesJavaScriptUrls(): void
    {
        $input = '<a href="javascript:alert(1)">Click</a>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testSanitizeContentRemovesEventHandlers(): void
    {
        $input = '<p onclick="alert(1)">Hello</p>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function testSanitizeContentRemovesMultipleEventHandlers(): void
    {
        $input = '<img src="x" onerror="alert(1)" onload="evil()" onmouseover="bad()">';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('onmouseover', $result);
    }

    public function testSanitizeContentAllowsSafeLinks(): void
    {
        $input = '<a href="https://example.com" title="Example">Link</a>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    public function testSanitizeContentStripsUnsafeAttributes(): void
    {
        $input = '<p style="color:red">Text</p>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        // style attribute is not allowed, so it's stripped
        $this->assertStringNotContainsString('style=', $result);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('</p>', $result);
    }

    public function testSanitizeContentHandlesNestedTags(): void
    {
        $input = '<div><span>nested<span>deep</span></span></div>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringContainsString('<div>', $result);
        $this->assertStringContainsString('</div>', $result);
    }

    public function testSanitizeContentStripsPhpTags(): void
    {
        $input = '<?php echo "test"; ?>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString('<?php', $result);
        $this->assertStringNotContainsString('?>', $result);
    }

    public function testSanitizeContentStripsIframeTags(): void
    {
        $input = '<iframe src="https://evil.com"></iframe>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString('<iframe', $result);
    }

    public function testSanitizeContentStripsObjectTags(): void
    {
        $input = '<object data="evil.swf"></object>';
        $result = \FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString('<object', $result);
    }

    public function testSanitizeContentStripsEmbedTags(): void
    {
        $input = '<embed src="evil.swf">';
        $result = \FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString('<embed', $result);
    }

    public function testSanitizeContentPreservesTextContent(): void
    {
        $input = 'Plain text with special chars: < > & " \' /';
        $result = \FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }
}
