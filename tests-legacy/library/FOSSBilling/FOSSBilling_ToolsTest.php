<?php

declare(strict_types=1);

#[Group('Core')]
final class FOSSBilling_ToolsTest extends PHPUnit\Framework\TestCase
{
    // sanitizeContent tests
    public function testSanitizeContentEmptyString(): void
    {
        $this->assertSame('', FOSSBilling\Tools::sanitizeContent(''));
    }

    public function testSanitizeContentRemovesNullBytes(): void
    {
        $input = "Hello\0World";
        $result = FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString("\0", $result);
        $this->assertSame('HelloWorld', $result);
    }

    public function testSanitizeContentStripsAllHtml(): void
    {
        $input = '<p>Hello <strong>World</strong></p>';
        $result = FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertSame('Hello World', $result);
    }

    public function testSanitizeContentEscapesSpecialChars(): void
    {
        $input = 'Test <script> & "quotes"';
        $result = FOSSBilling\Tools::sanitizeContent($input, false);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&amp;', $result);
    }

    public function testSanitizeContentAllowsSafeHtml(): void
    {
        $input = '<p>Hello <strong>World</strong></p>';
        $result = FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<strong>', $result);
    }

    public function testSanitizeContentAllowsLinks(): void
    {
        $input = '<a href="https://example.com" title="Example">Link</a>';
        $result = FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringContainsString('<a href="https://example.com"', $result);
    }

    public function testSanitizeContentRemovesScriptTags(): void
    {
        $input = '<p>Hello</p><script>alert("xss")</script>';
        $result = FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testSanitizeContentRemovesJavascriptUrls(): void
    {
        $input = '<a href="javascript:alert(1)">Click</a>';
        $result = FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testSanitizeContentRemovesEventHandlers(): void
    {
        $input = '<p onclick="alert(1)">Hello</p>';
        $result = FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function testSanitizeContentRemovesOnErrorHandler(): void
    {
        $input = '<img src="x" onerror="alert(1)">';
        $result = FOSSBilling\Tools::sanitizeContent($input, true);
        $this->assertStringNotContainsString('onerror', $result);
    }
}
