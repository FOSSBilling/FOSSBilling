<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Tools\DemoSeed;

/**
 * Minimal admin-API client for the demo seeder.
 *
 * Every call authenticates as the admin user over HTTP basic auth and posts
 * form-encoded parameters, matching FOSSBilling's legacy API surface.
 */
final readonly class ApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws \RuntimeException on transport, JSON or API errors
     */
    public function call(string $endpoint, array $params = []): mixed
    {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => "admin:{$this->apiKey}",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
        ]);
        $out = curl_exec($ch);
        if ($out === false) {
            $err = curl_error($ch);

            throw new \RuntimeException("cURL failed for {$endpoint}: {$err}");
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $decoded = json_decode((string) $out, true);
        if (!is_array($decoded) || !array_key_exists('result', $decoded) || !array_key_exists('error', $decoded)) {
            throw new \RuntimeException("Invalid JSON from {$endpoint} (HTTP {$code}): " . substr((string) $out, 0, 300));
        }
        if (!empty($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? 'Unknown API error';
            $errorCode = $decoded['error']['code'] ?? 0;

            throw new \RuntimeException("API {$endpoint} failed: {$msg} (code {$errorCode})");
        }

        return $decoded['result'];
    }

    /**
     * Like call(), but converts failures into a `['__error' => message]` array
     * so seeding can continue past individual record errors.
     *
     * @param array<string, mixed> $params
     */
    public function callSafe(string $endpoint, array $params = []): mixed
    {
        try {
            return $this->call($endpoint, $params);
        } catch (\Throwable $e) {
            return ['__error' => $e->getMessage()];
        }
    }
}
