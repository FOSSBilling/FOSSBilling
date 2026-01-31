<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

/**
 * Exception thrown when a product type does not support a requested action.
 */
class ProductTypeActionNotSupportedException extends Exception
{
    public function __construct(string $code, string $action)
    {
        parent::__construct(
            'Product type "%s" does not support action "%s".',
            [$code, $action]
        );
    }
}
