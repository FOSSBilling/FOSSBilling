<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * API client for E2E testing.
 */
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
        string $method = 'POST',
    ): ApiResponse {
        $baseUrl = self::$baseUrl ?? (getenv('APP_URL') ?: 'http://localhost');
        $baseUrl = rtrim($baseUrl, '/');
        $apiKey ??= self::$apiKey ?? getenv('TEST_API_KEY');

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

        return new ApiResponse($httpCode, $output);
    }

    public static function resetCookies(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove(self::getCookiePath());
    }

    public static function get(string $endpoint, array $payload = [], ?string $role = null): ApiResponse
    {
        return self::request($endpoint, $payload, $role ?? 'admin', null, 'GET');
    }

    public static function post(string $endpoint, array $payload = [], ?string $role = null): ApiResponse
    {
        return self::request($endpoint, $payload, $role ?? 'admin', null, 'POST');
    }
}

/**
 * API Response wrapper for E2E tests.
 */
class ApiResponse
{
    private array $decodedResponse = [];

    public function __construct(
        private readonly int $code,
        private readonly string $rawResponse,
    ) {
        $decoded = json_decode($this->rawResponse, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }
        $this->decodedResponse = $decoded ?? [];
    }

    public function getHttpCode(): int
    {
        return $this->code;
    }

    public function getResponse(): array
    {
        return $this->decodedResponse;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }

    public function wasSuccessful(): bool
    {
        return $this->decodedResponse && !$this->decodedResponse['error'];
    }

    public function getErrorMessage(): string
    {
        return $this->decodedResponse['error']['message'] ?? 'None';
    }

    public function getErrorCode(): int
    {
        return (int) ($this->decodedResponse['error']['code'] ?? 0);
    }

    public function getError(): string
    {
        return $this->getErrorMessage() . ' (Code ' . $this->getErrorCode() . ')';
    }

    public function getResult(): mixed
    {
        return $this->decodedResponse['result'] ?? null;
    }

    public function hasResult(): bool
    {
        return array_key_exists('result', $this->decodedResponse);
    }

    public function isArrayResult(): bool
    {
        return is_array($this->decodedResponse['result'] ?? null);
    }

    public function isIntResult(): bool
    {
        return is_int($this->decodedResponse['result'] ?? null);
    }

    public function isBoolResult(): bool
    {
        return is_bool($this->decodedResponse['result'] ?? null);
    }

    public function isStringResult(): bool
    {
        return is_string($this->decodedResponse['result'] ?? null);
    }
}

/**
 * Helper function for API requests.
 */
function api(string $endpoint, array $payload = [], string $role = 'admin'): ApiResponse
{
    return ApiClient::request($endpoint, $payload, $role);
}

/**
 * Assert that an API response was successful.
 */
function assertApiSuccess(ApiResponse $response): void
{
    expect($response->wasSuccessful())
        ->toBeTrue($response->getError());
}

/**
 * Assert that an API response has an integer result.
 */
function assertApiResultIsInt(ApiResponse $response): void
{
    expect($response->isIntResult())
        ->toBeTrue('Expected integer result, got: ' . gettype($response->getResult()));
}

/**
 * Assert that an API response has an array result.
 */
function assertApiResultIsArray(ApiResponse $response): void
{
    expect($response->isArrayResult())
        ->toBeTrue('Expected array result, got: ' . gettype($response->getResult()));
}
