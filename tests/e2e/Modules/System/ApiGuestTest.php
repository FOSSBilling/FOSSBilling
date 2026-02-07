<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Modules\System;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiGuestTest extends TestCase
{
    public function testTemplateExists(): void
    {
        $result = ApiClient::request('guest/system/template_exists', ['file' => 'layout_default.html.twig']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testTemplateDoesNotExist(): void
    {
        $result = ApiClient::request('guest/system/template_exists', ['file' => 'thisfiledoesnotexist.txt']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertFalse($result->getResult());
    }

    public function testPeriods(): void
    {
        $result = ApiClient::request('guest/system/periods');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testCountries(): void
    {
        $result = ApiClient::request('guest/system/countries');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testCountriesEunion(): void
    {
        $result = ApiClient::request('guest/system/countries_eunion');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testStates(): void
    {
        $result = ApiClient::request('guest/system/states');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testPhoneCodes(): void
    {
        $result = ApiClient::request('guest/system/phone_codes');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertIsArray($result->getResult());
    }
}
