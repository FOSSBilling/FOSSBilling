<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testIsFOSSBillingWorking(): void
    {
        $result = Request::makeRequest('guest/system/company');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testStartingPatchNotBehind(): void
    {
        $result = Request::makeRequest('admin/system/is_behind_on_patches');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertNotTrue($result->getResult()); // This should return false to indicate there are no patches available, meaning the `last_patch` number is correct for fresh installs.
    }
}
