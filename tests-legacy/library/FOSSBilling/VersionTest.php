<?php

declare(strict_types=1);

use FOSSBilling\Version;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class VersionTest extends PHPUnit\Framework\TestCase
{
    public function testDefaultDevelopmentVersionIsPreview(): void
    {
        self::assertTrue(Version::isPreviewVersion('0.0.1'));
    }

    public function testReleaseVersionIsNotPreview(): void
    {
        self::assertFalse(Version::isPreviewVersion('1.2.3'));
    }

    public function testInvalidVersionIsPreview(): void
    {
        self::assertTrue(Version::isPreviewVersion('abcdef1'));
    }
}
