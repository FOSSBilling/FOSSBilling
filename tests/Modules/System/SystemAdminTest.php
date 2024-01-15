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
}
