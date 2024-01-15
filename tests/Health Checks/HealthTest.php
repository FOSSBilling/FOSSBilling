<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testIsFOSSBillingWorking(): void
    {
        $response = Request::makeRequest('guest/system/company');
        $this->assertEquals(200, $response->getHttpCode());
        $this->assertTrue($response->wasSuccessful(), "The API request failed with the following message: " . $response->getError());
    }

    public function testStartingPatchNotBehind(): void
    {
        $response = Request::makeRequest('admin/system/is_behind_on_patches');
        $this->assertEquals(200, $response->getHttpCode());
        $this->assertTrue($response->wasSuccessful(), "The API request failed with the following message: " . $response->getError());
        $this->assertNotTrue($response->getResult());
    }
}
