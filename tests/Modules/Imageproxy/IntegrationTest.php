<?php

declare(strict_types=1);

namespace ImageproxyTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Imageproxy module.
 * These tests require a running FOSSBilling instance and make actual API calls.
 */
final class IntegrationTest extends TestCase
{
    /**
     * Test that the module can be configured via the API.
     */
    public function testModuleConfiguration(): void
    {
        // Test updating configuration
        $result = Request::makeRequest('admin/imageproxy/update_config', [
            'max_size_mb' => 10,
            'timeout_seconds' => 7,
            'max_duration_seconds' => 15,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());

        // Verify config was saved
        $configResult = Request::makeRequest('admin/extension/config_get', [
            'ext' => 'mod_imageproxy',
        ]);

        $this->assertTrue($configResult->wasSuccessful(), $configResult->generatePHPUnitMessage());
        $config = $configResult->getResult();
        $this->assertEquals(10, $config['max_size_mb']);
        $this->assertEquals(7, $config['timeout_seconds']);
        $this->assertEquals(15, $config['max_duration_seconds']);

        // Reset to defaults
        Request::makeRequest('admin/imageproxy/update_config', [
            'max_size_mb' => 5,
            'timeout_seconds' => 5,
            'max_duration_seconds' => 10,
        ]);
    }

    /**
     * Test that invalid size limits are rejected.
     */
    public function testRejectInvalidSizeLimit(): void
    {
        $result = Request::makeRequest('admin/imageproxy/update_config', [
            'max_size_mb' => 100, // Over the 50 MB limit
            'timeout_seconds' => 5,
            'max_duration_seconds' => 10,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Max size must be between 1 and 50 MB', $result->getErrorMessage());
    }

    /**
     * Test that invalid timeout values are rejected.
     */
    public function testRejectInvalidTimeout(): void
    {
        $result = Request::makeRequest('admin/imageproxy/update_config', [
            'max_size_mb' => 5,
            'timeout_seconds' => 50, // Over the 30 second limit
            'max_duration_seconds' => 60,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Timeout must be between 1 and 30 seconds', $result->getErrorMessage());
    }

    /**
     * Test that max_duration less than timeout is rejected.
     */
    public function testRejectInvalidDuration(): void
    {
        $result = Request::makeRequest('admin/imageproxy/update_config', [
            'max_size_mb' => 5,
            'timeout_seconds' => 10,
            'max_duration_seconds' => 5, // Less than timeout
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Max duration must be greater than or equal to timeout', $result->getErrorMessage());
    }

    /**
     * Test migration of existing tickets to use proxified URLs.
     */
    public function testMigrateExistingTickets(): void
    {
        $result = Request::makeRequest('admin/imageproxy/migrate_existing_tickets');

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $stats = $result->getResult();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('processed', $stats);
        $this->assertArrayHasKey('updated', $stats);
        $this->assertArrayHasKey('images_found', $stats);
        $this->assertGreaterThanOrEqual(0, $stats['processed']);
        $this->assertGreaterThanOrEqual(0, $stats['updated']);
        $this->assertGreaterThanOrEqual(0, $stats['images_found']);
    }

    /**
     * Test reversion of proxified URLs back to originals.
     */
    public function testRevertProxifiedUrls(): void
    {
        $result = Request::makeRequest('admin/imageproxy/revert_proxified_urls');

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $stats = $result->getResult();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('processed', $stats);
        $this->assertArrayHasKey('reverted', $stats);
        $this->assertGreaterThanOrEqual(0, $stats['processed']);
        $this->assertGreaterThanOrEqual(0, $stats['reverted']);
    }
}


