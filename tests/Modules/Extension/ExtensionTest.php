<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class ExtensionTest extends TestCase
{
    public function testCanActivateExtension(): void
    {
        $response = Request::makeRequest('admin/extension/activate', 'POST', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
    }

    public function testCanDeactivateExtension(): void
    {
        $response = Request::makeRequest('admin/extension/deactivate', 'POST', ['type' => 'mod', 'id' => 'massmailer']);
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
    }

    /*public function testCanInstallExtension(): void
    {
        $response = Request::makeRequest('admin/extension/install', 'POST', ['type' => 'mod', 'id' => 'example']);
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
    }*/

    public function testLanguageManagement(): void
    {
        $response = Request::makeRequest('admin/extension/languages');
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
        $this->assertNotCount(0, $response->getResult()); // A fresh install should never display 0 enabled languages

        $response = Request::makeRequest('admin/extension/languages', 'POST', ['disabled' => true]);
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
        $this->assertCount(0, $response->getResult()); // There should be no disabled languages on a fresh install

        // Disable the en_US language
        $response = Request::makeRequest('admin/extension/toggle_language', 'POST', ['locale_id' => 'en_US']);
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());

        // Validate it's now listed under the disabled languages
        $response = Request::makeRequest('admin/extension/languages', 'POST', ['disabled' => true, 'details' => false]);
        $this->assertTrue($response->wasSuccessful(), 'The API request failed with the following message: ' . $response->getError());
        $this->assertContains('en_US', $response->getResult(), 'The en_US language was not disabled');
    }

    public function testLanguageCompletion(): void
    {
        $response = Request::makeRequest('admin/extension/languages', 'POST', ['disabled' => true, 'details' => false]);
        // Enable any languages that are disabled
        foreach ($response->getResult() as $locale) {
            Request::makeRequest('admin/extension/toggle_language', 'POST', ['locale_id' => $locale]);
        }

        $response = Request::makeRequest('admin/extension/languages', 'POST', ['disabled' => true, 'details' => false]);
        $this->assertEmpty($response->getResult()); // There should now be no disabled languages

        // Get a list of all languages, validate they have an expected completion level
        $response = Request::makeRequest('admin/extension/languages');
        foreach ($response->getResult() as $locale) {
            $completionResult = Request::makeRequest('admin/extension/locale_completion', 'POST', ['locale_id' => $locale]);
            if ($locale === 'en_US') {
                $this->assertEquals(100, $completionResult->getResult());
            } else {
                $this->greaterThanOrEqual(25, $completionResult->getResult());
            }
        }
    }
}
