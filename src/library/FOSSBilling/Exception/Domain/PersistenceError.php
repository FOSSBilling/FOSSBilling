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
 * Thrown when a persistence operation fails.
 *
 * This typically wraps database-level errors (constraint violations,
 * connection issues, etc.) in a domain-friendly way.
 *
 * API layer translates this to a 500 Internal Server Error response.
 */
class PersistenceError extends DomainError
{
    /**
     * @param string $message The error message
     */
    public function __construct(string $message)
    {
        parent::__construct($message, [], 0, false);
    }
}
