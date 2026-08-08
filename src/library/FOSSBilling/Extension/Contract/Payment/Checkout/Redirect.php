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
 * Send the browser to an external URL to complete payment.
 *
 * Construct via `Checkout::redirect()`, not directly.
 */
final readonly class Redirect extends Checkout
{
    public function __construct(public string $url)
    {
    }
}
