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

final class ApiAdminTest extends TestCase
{
    public function testCanActivateExtension(): void
    {
        $result = ApiClient::request('admin/extension/activate', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
    }

    public function testCanDeactivateExtension(): void
    {
        $result = ApiClient::request('admin/extension/deactivate', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
    }

    public function testLanguageManagement(): void
    {
        $result = ApiClient::request('admin/extension/languages');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertNotCount(0, $result->getResult());

        $result = ApiClient::request('admin/extension/languages', ['disabled' => true]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertCount(0, $result->getResult());

        $result = ApiClient::request('admin/extension/toggle_language', ['locale_id' => 'en_US']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());

        $result = ApiClient::request('admin/extension/languages', ['disabled' => true, 'details' => false]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertContains('en_US', $result->getResult());

        $result = ApiClient::request('admin/extension/languages');
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
        $this->assertNotContains('en_US', $result->getResult());

        $result = ApiClient::request('admin/extension/toggle_language', ['locale_id' => 'en_US']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePhpUnitMessage());
    }

    public function testLanguageCompletion(): void
    {
        $locales = ApiClient::request('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult();
        foreach ($locales as $locale) {
            ApiClient::request('admin/extension/toggle_language', ['locale_id' => $locale]);
        }

        $this->assertEmpty(ApiClient::request('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult());

        $locales = ApiClient::request('admin/extension/languages', ['details' => false])->getResult();
        foreach ($locales as $locale) {
            $completionResult = ApiClient::request('admin/extension/locale_completion', ['locale_id' => $locale]);
            if ($locale === 'en_US') {
                $this->assertEquals(100, $completionResult->getResult());
            } else {
                $this->assertGreaterThanOrEqual(25, $completionResult->getResult());
            }
        }
    }
}
