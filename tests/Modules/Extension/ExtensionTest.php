<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class ExtensionTest extends TestCase
{
    public function testCanActivateExtension(): void
    {
        $response = Request::makeRequest('admin/extension/activate', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
    }

    public function testCanDeactivateExtension(): void
    {
        $response = Request::makeRequest('admin/extension/deactivate', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
    }

    /*
    public function testCanInstallExtension(): void
    {
        $response = Request::makeRequest('admin/extension/install', ['type' => 'mod', 'id' => 'example']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
    }
    */

    public function testLanguageManagement(): void
    {
        $response = Request::makeRequest('admin/extension/languages');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertNotCount(0, $response->getResult()); // A fresh install should never display 0 enabled languages

        $response = Request::makeRequest('admin/extension/languages', ['disabled' => true]);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertCount(0, $response->getResult()); // There should be no disabled languages on a fresh install

        // Disable the en_US language
        $response = Request::makeRequest('admin/extension/toggle_language', ['locale_id' => 'en_US']);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());

        // Validate it's now listed under the disabled languages
        $response = Request::makeRequest('admin/extension/languages', ['disabled' => true, 'details' => false]);
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertContains('en_US', $response->getResult(), 'The en_US language was not disabled');
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
                $this->greaterThanOrEqual(25, $completionResult->getResult());
            }
        }
    }
}
