<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Library\E2E\Traits;

use PHPUnit\Framework\TestCase;

trait ApiAssertions
{
    public static function assertApiSuccess(ApiResponse $response): void
    {
        $hasError = $response->getResponse()['error'] ?? false;
        $hasResult = $response->hasResult();

        if ($hasError) {
            $message = $response->getErrorMessage();
            $code = $response->getErrorCode();
            TestCase::assertFalse($hasError, "API request failed: {$message} (Code: {$code})");
        }

        TestCase::assertTrue($hasResult, 'API response should contain a result');
    }

    public static function assertApiError(ApiResponse $response, string $expectedMessage = ''): void
    {
        $hasError = $response->getResponse()['error'] ?? false;
        TestCase::assertTrue($hasError, 'API response should contain an error');

        if ($expectedMessage) {
            $message = $response->getErrorMessage();
            TestCase::assertStringContainsStringIgnoringCase($expectedMessage, $message);
        }
    }

    public static function assertApiResultIsArray(ApiResponse $response): void
    {
        self::assertApiSuccess($response);
        TestCase::assertIsArray($response->getResult(), 'API result should be an array');
    }

    public static function assertApiResultIsInt(ApiResponse $response): void
    {
        self::assertApiSuccess($response);
        TestCase::assertIsInt($response->getResult(), 'API result should be an integer');
    }

    public static function assertApiResultIsString(ApiResponse $response): void
    {
        self::assertApiSuccess($response);
        TestCase::assertIsString($response->getResult(), 'API result should be a string');
    }

    public static function assertApiResultIsBool(ApiResponse $response): void
    {
        self::assertApiSuccess($response);
        TestCase::assertIsBool($response->getResult(), 'API result should be a boolean');
    }
}
