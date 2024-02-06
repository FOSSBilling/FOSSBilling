<?php

declare(strict_types=1);

namespace ExtensionTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    public function testCanActivateExtension(): void
    {
        $result = Request::makeRequest('admin/extension/activate', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }

    public function testCanDeactivateExtension(): void
    {
        $result = Request::makeRequest('admin/extension/deactivate', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }

    /*
    public function testCanInstallExtension(): void
    {
        $result = Request::makeRequest('admin/extension/install', ['type' => 'mod', 'id' => 'example']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }
    */

    public function testLanguageManagement(): void
    {
        $result = Request::makeRequest('admin/extension/languages');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertNotCount(0, $result->getResult()); // A fresh install should never display 0 enabled languages

        $result = Request::makeRequest('admin/extension/languages', ['disabled' => true]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertCount(0, $result->getResult()); // There should be no disabled languages on a fresh install

        // Disable the en_US language
        $result = Request::makeRequest('admin/extension/toggle_language', ['locale_id' => 'en_US']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());

        // Validate it's now listed under the disabled languages
        $result = Request::makeRequest('admin/extension/languages', ['disabled' => true, 'details' => false]);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertContains('en_US', $result->getResult(), 'The en_US language was not disabled');

        // Validate it's no longer listed under the enabled languages
        $result = Request::makeRequest('admin/extension/languages');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertNotContains('en_US', $result->getResult(), 'The en_US language was not disabled');

        // Enable it again
        $result = Request::makeRequest('admin/extension/toggle_language', ['locale_id' => 'en_US']);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
    }

    public function testLanguageCompletion(): void
    {
        // Enable any languages that are disabled
        $locales = Request::makeRequest('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult();
        foreach ($locales as $locale) {
            Request::makeRequest('admin/extension/toggle_language', ['locale_id' => $locale]);
        }

        $this->assertEmpty(Request::makeRequest('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult()); // There should now be no disabled languages

        // Get a list of all languages, validate they have an expected completion level
        $locales = Request::makeRequest('admin/extension/languages', ['details' => false])->getResult();
        foreach ($locales as $locale) {
            $completionResult = Request::makeRequest('admin/extension/locale_completion', ['locale_id' => $locale]);
            if ($locale === 'en_US') {
                $this->assertEquals(100, $completionResult->getResult());
            } else {
                $this->assertGreaterThanOrEqual(25, $completionResult->getResult());
            }
        }
    }
}
