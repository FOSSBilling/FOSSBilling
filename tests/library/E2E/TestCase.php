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

use FOSSBilling\Tests\Library\E2E\Traits\ApiAssertions;
use FOSSBilling\Tests\Library\E2E\Traits\ApiResponse;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use ApiAssertions;

    protected function setUp(): void
    {
        if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
            $this->markTestSkipped('E2E tests require APP_URL and TEST_API_KEY environment variables');
        }
        ApiClient::resetCookies();
    }

    protected function getBaseUrl(): string
    {
        $url = getenv('APP_URL');
        return $url !== false ? rtrim($url, '/') : 'http://localhost';
    }

    protected function getApiKey(): ?string
    {
        return getenv('TEST_API_KEY');
    }

    protected function getAdminRole(): string
    {
        return 'admin';
    }

    protected function getClientRole(): string
    {
        return 'client';
    }

    protected function requestAdmin(string $endpoint, array $payload = []): \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse
    {
        return ApiClient::request($endpoint, $payload, $this->getAdminRole());
    }

    protected function requestClient(string $endpoint, array $payload = []): \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse
    {
        return ApiClient::request($endpoint, $payload, $this->getClientRole());
    }

    protected function requestGuest(string $endpoint, array $payload = []): \FOSSBilling\Tests\Library\E2E\Traits\ApiResponse
    {
        return ApiClient::request($endpoint, $payload, 'guest');
    }
}
