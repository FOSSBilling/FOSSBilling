<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\Extension;

use FOSSBilling\Tests\E2E\TestCase;
use FOSSBilling\Tests\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testTheme(): void
    {
        $result = ApiClient::request('guest/extension/theme');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
        $this->assertArrayHasKey('name', $result->getResult());
        $this->assertArrayHasKey('version', $result->getResult());
        $this->assertEquals('FOSSBilling', $result->getResult()['author']);
    }

    public function testSettings(): void
    {
        $result = ApiClient::request('guest/extension/settings', ['ext' => 'index']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testSettingsMissingExt(): void
    {
        $result = ApiClient::request('guest/extension/settings', ['ext']);
        $this->assertFalse($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertEquals('Parameter ext is missing', $result->getErrorMessage());
    }

    public function testExtensionIsActive(): void
    {
        $result = ApiClient::request('guest/extension/is_on', ['mod' => 'index']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testExtensionIsNotActive(): void
    {
        $result = ApiClient::request('guest/extension/is_on', ['mod' => 'serviceapikey']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertFalse($result->getResult());
    }
}
