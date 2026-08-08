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
 * HTML rendered directly on the invoice payment page.
 *
 * Construct via `Checkout::html()`, not directly.
 */
final readonly class Html extends Checkout
{
    public function __construct(public string $html)
    {
    }
}
