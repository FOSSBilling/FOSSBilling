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
        $this->assertIsArray($result->getResult());

        $this->assertArrayHasKey('name', $result->getResult());
        $this->assertArrayHasKey('version', $result->getResult());
        $this->assertArrayHasKey('author', $result->getResult());
        $this->assertEquals('FOSSBilling', $result->getResult()['author']);
    }

    public function testGetCurrentAdminTheme(): void
    {
        $result = Request::makeRequest('admin/theme/get_current', ['client' => false]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());

        $this->assertArrayHasKey('name', $result->getResult());
        $this->assertArrayHasKey('version', $result->getResult());
        $this->assertArrayHasKey('author', $result->getResult());
        $this->assertEquals('FOSSBilling', $result->getResult()['author']);
    }

    public function testInvalidThemeActionReturnsError(): void
    {
        $result = Request::makeRequest('admin/theme/non_existing_action');
        $this->assertFalse($result->wasSuccessful(), 'Invalid theme action should not be successful.');
    }
}
