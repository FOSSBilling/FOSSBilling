<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

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

    public function testNormalizeBooleanHandlesBooleanStringValues(): void
    {
        $this->assertTrue(FOSSBilling\Tools::normalizeBoolean('true'));
        $this->assertFalse(FOSSBilling\Tools::normalizeBoolean('false'));
        $this->assertTrue(FOSSBilling\Tools::normalizeBoolean('1'));
        $this->assertFalse(FOSSBilling\Tools::normalizeBoolean('0'));
    }

    public function testNormalizeBooleanRespectsDefaultForUnknownString(): void
    {
        $this->assertTrue(FOSSBilling\Tools::normalizeBoolean('maybe', true));
        $this->assertFalse(FOSSBilling\Tools::normalizeBoolean('maybe'));
    }

    public function testNormalizePortAcceptsIntegersAndNumericStrings(): void
    {
        $this->assertSame(1, FOSSBilling\Tools::normalizePort(1));
        $this->assertSame(443, FOSSBilling\Tools::normalizePort('443'));
        $this->assertSame(65535, FOSSBilling\Tools::normalizePort('65535'));
    }

    public function testNormalizePortTrimsWhitespace(): void
    {
        $this->assertSame(3306, FOSSBilling\Tools::normalizePort(' 3306 '));
        $this->assertSame(587, FOSSBilling\Tools::normalizePort("\n587\t"));
    }

    public function testNormalizePortRejectsInvalidValues(): void
    {
        $this->assertNull(FOSSBilling\Tools::normalizePort(0));
        $this->assertNull(FOSSBilling\Tools::normalizePort(65536));
        $this->assertNull(FOSSBilling\Tools::normalizePort('-1'));
        $this->assertNull(FOSSBilling\Tools::normalizePort('8443abc'));
        $this->assertNull(FOSSBilling\Tools::normalizePort(''));
        $this->assertNull(FOSSBilling\Tools::normalizePort(null));
        $this->assertNull(FOSSBilling\Tools::normalizePort([]));
    }

    public function testNormalizePortReturnsDefaultForInvalidValues(): void
    {
        $this->assertSame(3306, FOSSBilling\Tools::normalizePort(null, 3306));
        $this->assertSame(2087, FOSSBilling\Tools::normalizePort('invalid', 2087));
        $this->assertSame(8443, FOSSBilling\Tools::normalizePort(65536, 8443));
    }

    public function testIsValidHttpInterfaceAcceptsIpAndHostnameFormats(): void
    {
        $this->assertTrue(FOSSBilling\Tools::isValidHttpInterface('192.0.2.25'));
        $this->assertTrue(FOSSBilling\Tools::isValidHttpInterface('eth0'));
        $this->assertTrue(FOSSBilling\Tools::isValidHttpInterface('node-1.example.local'));
    }

    public function testIsValidHttpInterfaceRejectsInvalidFormat(): void
    {
        $this->assertFalse(FOSSBilling\Tools::isValidHttpInterface(''));
        $this->assertFalse(FOSSBilling\Tools::isValidHttpInterface('12345'));
        $this->assertFalse(FOSSBilling\Tools::isValidHttpInterface("x\"; passthru('id'); //"));
    }

    public function testIsHttpsIgnoresSpoofedForwardedProtoHeader(): void
    {
        $server = $_SERVER;
        $_SERVER = [
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];

        try {
            $this->assertFalse(FOSSBilling\Tools::isHTTPS());
        } finally {
            $_SERVER = $server;
        }
    }

    public function testIsHttpsStillSupportsHttpsServerSignals(): void
    {
        $server = $_SERVER;
        $_SERVER = [
            'REQUEST_SCHEME' => 'https',
        ];

        try {
            $this->assertTrue(FOSSBilling\Tools::isHTTPS());
        } finally {
            $_SERVER = $server;
        }
    }
}
