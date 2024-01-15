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

        // And then validate the folder is actually empty
        $this->assertDirectoryExists(APP_PATH . DIRECTORY_SEPARATOR . 'Cache');
        $this->assertFalse(scandir(APP_PATH . DIRECTORY_SEPARATOR . 'Cache'));
    }
}
