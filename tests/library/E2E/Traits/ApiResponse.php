<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\E2E\Traits;

class ApiResponse
{
    private array $decodedResponse = [];

    public function __construct(
        private readonly int $code,
        private readonly string $rawResponse
    ) {
        $this->decodedResponse = json_decode($this->rawResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }
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
        return intval($this->decodedResponse['error']['code'] ?? 0);
    }

    public function getError(): string
    {
        return $this->getErrorMessage() . ' (Code ' . $this->getErrorCode() . ')';
    }

    public function generatePhpUnitMessage(): string
    {
        return 'The API request failed with the following message: ' . $this->getError();
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
