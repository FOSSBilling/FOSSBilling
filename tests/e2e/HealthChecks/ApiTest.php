<?php

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\HealthChecks;

use FOSSBilling\Tests\Library\E2E\TestCase;
use FOSSBilling\Tests\Library\E2E\ApiClient;

final class ApiTest extends TestCase
{
    private array $testUrls = [
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

    public function testUrlsReturnOkStatus(): void
    {
        $baseUrl = $this->getBaseUrl();
        foreach ($this->testUrls as $url => $expectedCode) {
            $ch = curl_init($baseUrl . $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->assertEquals($expectedCode, $responseCode, "URL '{$url}' should return {$expectedCode}");
        }
    }

    public function testApiIsFunctional(): void
    {
        $result = ApiClient::request('guest/system/company');
        $this->assertApiSuccess($result);
        $this->assertIsArray($result->getResult());
    }

    public function testSystemIsUpToDate(): void
    {
        $result = ApiClient::request('admin/system/is_behind_on_patches');
        $this->assertApiSuccess($result);
        $this->assertFalse($result->getResult());
    }
}
