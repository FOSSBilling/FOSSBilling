<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    private array $test_urls = [
        '' => 200,
        'order' => 200,
        'news' => 200,
        'privacy-policy' => 200,
        'sitemap.xml' => 200,
        'contact-us' => 200,
        'login' => 200,
        'signup' => 200,
        'password-reset' => 200,
    ];

    public function testUrls(): void
    {
        $baseUrl = getenv('APP_URL');
        foreach ($this->test_urls as $url => $code) {
            $ch = curl_init($baseUrl . $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->assertEquals($code, $responseCode, "Failed asserting that the URL '{$url}' returned a {$code} response code.");
        }
    }

    public function testIsFOSSBillingWorking(): void
    {
        $result = Request::makeRequest('guest/system/company');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testStartingPatchNotBehind(): void
    {
        $result = Request::makeRequest('admin/system/is_behind_on_patches');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertFalse($result->getResult()); // This should return false to indicate there are no patches available, meaning the `last_patch` number is correct for fresh installs.
    }
}
