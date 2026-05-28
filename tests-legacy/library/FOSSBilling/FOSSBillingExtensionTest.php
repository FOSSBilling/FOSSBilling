<?php

declare(strict_types=1);

use FOSSBilling\Twig\Extension\FOSSBillingExtension;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class FOSSBillingExtensionTest extends PHPUnit\Framework\TestCase
{
    private FOSSBillingExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new FOSSBillingExtension(null);
    }

    public function testTransWithoutValues(): void
    {
        $result = $this->extension->trans('Hello World');

        $this->assertSame('Hello World', $result);
    }

    public function testTransWithPlaceholderReplacement(): void
    {
        $result = $this->extension->trans('Hello %name%', ['%name%' => 'World']);

        $this->assertSame('Hello World', $result);
    }

    public function testTransWithMultiplePlaceholders(): void
    {
        $result = $this->extension->trans('Dear %title% %name%', ['%title%' => 'Mr.', '%name%' => 'Smith']);

        $this->assertSame('Dear Mr. Smith', $result);
    }

    public function testTransWithNullValues(): void
    {
        $result = $this->extension->trans('Hello %name%', null);

        $this->assertSame('Hello %name%', $result);
    }

    public function testAvatarRendersDataUriBackground(): void
    {
        $result = $this->extension->avatar('user@example.org', 32, 'avatar avatar-sm');

        $this->assertStringStartsWith('<span class="db-avatar avatar avatar-sm" style="width: 32px; height: 32px; background-image: url(data:image/svg+xml;charset=utf-8,', $result);
        $this->assertStringContainsString('background-repeat: no-repeat;', $result);
    }

    public function testAvatarIsDeterministic(): void
    {
        $first = $this->extension->avatar('user@example.org', 32, 'avatar avatar-sm');
        $second = $this->extension->avatar('user@example.org', 32, 'avatar avatar-sm');

        $this->assertSame($first, $second);
    }

    public function testAvatarReturnsFallbackWithoutEmail(): void
    {
        $result = $this->extension->avatar(null, 32, 'avatar', '<strong>Fallback</strong>');

        $this->assertSame('&lt;strong&gt;Fallback&lt;/strong&gt;', $result);
    }
}
