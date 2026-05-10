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
        $this->assertSame(740, $result->getErrorCode(), 'Invalid theme action should return error code 740.');

        $errorMessage = $result->getErrorMessage();
        $this->assertIsString($errorMessage, 'Error message should be a string.');
        $this->assertNotSame('', trim($errorMessage), 'Error message should not be empty.');
        $this->assertStringContainsString('non_existing_action', $errorMessage, 'Error message should reference the invalid action or endpoint.');
        $this->assertStringContainsString('does not exist', $errorMessage, 'Invalid theme action should report a missing endpoint/action.');
    }
}
