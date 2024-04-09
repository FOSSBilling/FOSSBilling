<?php

declare(strict_types=1);

namespace ExtensionTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class GuestTest extends TestCase
{
    public function testTheme(): void
    {
        $result = Request::makeRequest('guest/extension/theme');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());

        $this->assertArrayHasKey('name', $result->getResult());
        $this->assertArrayHasKey('version', $result->getResult());
        $this->assertEquals('FOSSBilling', $result->getResult()['author']);
    }

    public function testSettings(): void
    {
        $result = Request::makeRequest('guest/extension/settings', ['ext' => 'index']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testSettingsMissingExt(): void
    {
        $result = Request::makeRequest('guest/extension/settings', ['ext']);
        $this->assertFalse($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertEquals('Parameter ext is missing', $result->getErrorMessage());
    }

    public function testExtensionIsActive(): void
    {
        $result = Request::makeRequest('guest/extension/is_on', ['mod' => 'index']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testExtensionIsNotActive(): void
    {
        $result = Request::makeRequest('guest/extension/is_on', ['mod' => 'serviceapikey']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertFalse($result->getResult());
    }
}
