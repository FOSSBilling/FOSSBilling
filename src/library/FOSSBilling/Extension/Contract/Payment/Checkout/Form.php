<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment\Checkout;

use FOSSBilling\Extension\Contract\Payment\Checkout;

/**
 * An auto-submitting form that posts payment fields to the gateway.
 *
 * Construct via `Checkout::form()`, not directly.
 */
final readonly class Form extends Checkout
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public string $action,
        public array $fields,
        public string $method = 'POST',
    ) {
    }
}
