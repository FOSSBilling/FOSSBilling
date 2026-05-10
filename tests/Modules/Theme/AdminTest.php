<?php

declare(strict_types=1);

namespace ThemeTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    public function testGetCurrentClientTheme(): void
    {
        $result = Request::makeRequest('admin/theme/get_current');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $data = $result->getResult();
        $this->assertIsArray($data);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('author', $data);
        $this->assertEquals('FOSSBilling', $data['author']);
    }

    public function testGetCurrentAdminTheme(): void
    {
        $result = Request::makeRequest('admin/theme/get_current', ['client' => false]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $data = $result->getResult();
        $this->assertIsArray($data);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('author', $data);
        $this->assertEquals('FOSSBilling', $data['author']);
    }

    public function testInvalidThemeActionReturnsError(): void
    {
        $result = Request::makeRequest('admin/theme/non_existing_action');
        $this->assertSame(404, $result->getStatusCode(), 'Invalid theme action should return HTTP 404.');
        $this->assertFalse($result->wasSuccessful(), 'Invalid theme action should not be successful.');
        // API not-found actions/endpoints are mapped to error code 740 (see src/library/Api/Handler.php).
        $expectedNotFoundErrorCode = 740;
        $this->assertSame($expectedNotFoundErrorCode, $result->getErrorCode(), 'Invalid theme action should return the API not-found error code.');

        $errorMessage = $result->getErrorMessage();
        $this->assertIsString($errorMessage, 'Error message should be a string.');
        $this->assertNotSame('', trim($errorMessage), 'Error message should not be empty.');
    }
}
