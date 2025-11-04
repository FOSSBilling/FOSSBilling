<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Exception\Domain;

/**
 * Thrown when domain validation rules are violated.
 *
 * API layer translates this to a 400 Bad Request response.
 */
class ValidationError extends DomainError
{
    /**
     * @param string               $message The validation error message
     * @param array<string, mixed> $context Additional context about the validation failure
     */
    public function __construct(string $message, private array $context = [])
    {
        parent::__construct($message);
    }

    /**
     * Get validation context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
