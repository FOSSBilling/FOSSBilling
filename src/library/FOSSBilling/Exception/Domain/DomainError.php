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

use FOSSBilling\Exception;

/**
 * Base class for domain errors.
 *
 * Domain errors represent business logic failures that can be translated
 * to appropriate API responses. They map 1:1 to current API error payloads.
 */
abstract class DomainError extends Exception
{
    // Base domain error - specific implementations provide context
}
