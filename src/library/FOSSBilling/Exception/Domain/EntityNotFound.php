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
 * Thrown when an entity is not found in the database.
 *
 * API layer translates this to a 404 Not Found response.
 */
class EntityNotFound extends DomainError
{
    /**
     * @param string $entityType The type of entity that was not found
     * @param mixed  $identifier The identifier used to search for the entity
     */
    public function __construct(string $entityType, mixed $identifier)
    {
        // Extract simple class name from fully qualified class name
        $simpleClassName = basename(str_replace('\\', '/', $entityType));

        parent::__construct(
            sprintf('%s not found: %s', $simpleClassName, (string) $identifier)
        );
    }
}
