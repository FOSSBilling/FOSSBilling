<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class SystemAdminTest extends TestCase
{
    public function testClearCache(): void
    {
        // Clear the cache
        $response = Request::makeRequest('admin/system/clear_cache');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsBool($response->getResult());
    }

    public function testErrorReportingToggle(): void
    {
        // Get the starting value
        $before = Request::makeRequest('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($before);

        // Toggle the option
        $response = Request::makeRequest('admin/system/toggle_error_reporting');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertTrue($response->getResult());

        // Reset OPCache, wait a moment so a cached result doesn't give us issues
        sleep(1);

        // Check that it was correctly switched
        $after = Request::makeRequest('admin/system/error_reporting_enabled')->getResult();
        $this->assertIsBool($after);
        $this->assertNotEquals($after, $before);

        // Ensure we don't leave error reporting on (it shouldn't report anyways, but this is best practice)
        if ($after) {
            $response = Request::makeRequest('admin/system/toggle_error_reporting');
            $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
            $this->assertTrue($response->getResult());
        }
    }
}
