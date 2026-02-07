<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Library\E2E;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ApiClient
{
    private static ?string $baseUrl = null;
    private static ?string $apiKey = null;
    private static string $cookiePath;

    public static function setBaseUrl(string $url): void
    {
        self::$baseUrl = rtrim($url, '/');
    }

    public static function setApiKey(string $key): void
    {
        self::$apiKey = $key;
    }

    private static function getCookiePath(): string
    {
        if (!isset(self::$cookiePath)) {
            self::$cookiePath = Path::join(sys_get_temp_dir(), 'fossbilling_e2e_cookies.txt');
        }

        return self::$cookiePath;
    }

    public static function request(
        string $endpoint,
        array $payload = [],
        string $role = 'admin',
        ?string $apiKey = null,
        string $method = 'POST'
    ): \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse {
        $baseUrl = self::$baseUrl ?? (getenv('APP_URL') ?: 'http://localhost');
        $baseUrl = rtrim($baseUrl, '/');
        $apiKey = $apiKey ?? self::$apiKey ?? getenv('TEST_API_KEY');

        $url = rtrim($baseUrl, '/') . '/api/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_COOKIEJAR => self::getCookiePath(),
            CURLOPT_COOKIEFILE => self::getCookiePath(),
            CURLOPT_USERPWD => "$role:$apiKey",
        ]);

        if (strcasecmp($method, 'POST') === 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse($httpCode, $output);
    }

    public static function resetCookies(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove(self::getCookiePath());
    }

    public static function get(string $endpoint, array $payload = [], ?string $role = null): \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse
    {
        return self::request($endpoint, $payload, $role ?? 'admin', null, 'GET');
    }

    public static function post(string $endpoint, array $payload = [], ?string $role = null): \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse
    {
        return self::request($endpoint, $payload, $role ?? 'admin', null, 'POST');
    }
}
