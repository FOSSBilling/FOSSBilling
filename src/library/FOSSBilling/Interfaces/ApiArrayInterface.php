<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Interfaces;

interface ApiArrayInterface
{
    /**
     * Convert the entity to an array suitable for API output.
     *
     * Implementations may declare additional optional parameters (e.g. an identity
     * model) to tailor output based on who is calling. PHP allows implementations
     * to extend the interface signature with optional parameters, so callers that
     * know the concrete type may pass context; callers that only have the interface
     * type should call with no arguments.
     */
    public function toApiArray(): array;
}
