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
}
