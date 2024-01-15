<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testIsFOSSBillingWorking(): void
    {
        $response = Request::makeRequest('guest/system/company');
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
    }

    public function testStartingPatchNotBehind(): void
    {
        $response = Request::makeRequest('admin/system/is_behind_on_patches');
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
        $this->assertNotTrue($response->getResult()); // This should return false to indicate there are no patches available, meaning the `last_patch` number is correct for fresh installs.
    }
}
