<?php

declare(strict_types=1);

namespace Box\Mod\Redirect;

use FOSSBilling\Exception;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testValidateTargetAllowsRelativePath(): void
    {
        $service = new Service();
        $this->assertSame('/new-page', $service->validateTarget('/new-page'));
    }

    public function testValidateTargetAllowsRelativePathWithoutSlash(): void
    {
        $service = new Service();
        $this->assertSame('new-page', $service->validateTarget('new-page'));
    }

    public function testValidateTargetAllowsHttpUrl(): void
    {
        $service = new Service();
        $this->assertSame('http://example.com/page', $service->validateTarget('http://example.com/page'));
    }

    public function testValidateTargetAllowsHttpsUrl(): void
    {
        $service = new Service();
        $this->assertSame('https://example.com/page', $service->validateTarget('https://example.com/page'));
    }

    public function testValidateTargetTrimsWhitespace(): void
    {
        $service = new Service();
        $this->assertSame('/new-page', $service->validateTarget('  /new-page  '));
    }

    public function testValidateTargetRejectsEmptyString(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget('');
    }

    public function testValidateTargetRejectsWhitespaceOnly(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget('   ');
    }

    public function testValidateTargetRejectsJavascriptScheme(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget('javascript:alert(1)');
    }

    public function testValidateTargetRejectsJavascriptSchemeMixedCase(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget('JaVaScRiPt:alert(1)');
    }

    public function testValidateTargetRejectsDataScheme(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget('data:text/html,<h1>test</h1>');
    }

    public function testValidateTargetRejectsFtpScheme(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget('ftp://example.com/file');
    }

    public function testValidateTargetRejectsCrLfInjection(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget("/new-page\r\nLocation: https://evil.com");
    }

    public function testValidateTargetRejectsLfInjection(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget("/new-page\nLocation: https://evil.com");
    }

    public function testValidateTargetRejectsCrInjection(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validateTarget("/new-page\rLocation: https://evil.com");
    }

    public function testValidatePathStripsSlashes(): void
    {
        $service = new Service();
        $this->assertSame('old-page', $service->validatePath('/old-page/'));
    }

    public function testValidatePathAllowsNestedPath(): void
    {
        $service = new Service();
        $this->assertSame('some/nested/path', $service->validatePath('/some/nested/path/'));
    }

    public function testValidatePathRejectsEmptyString(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validatePath('');
    }

    public function testValidatePathRejectsSlashOnly(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validatePath('/');
    }

    public function testValidatePathRejectsPathTraversal(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validatePath('../config');
    }

    public function testValidatePathRejectsEmbeddedPathTraversal(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validatePath('foo/../../etc/passwd');
    }

    public function testValidatePathRejectsCrLf(): void
    {
        $service = new Service();
        $this->expectException(Exception::class);
        $service->validatePath("old-page\r\nX-Header: evil");
    }
}
