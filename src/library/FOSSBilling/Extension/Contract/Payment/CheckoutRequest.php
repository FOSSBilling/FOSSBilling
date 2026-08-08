<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment;

/**
 * Per-payment data handed to Gateway::checkout().
 *
 * Merchant settings live on the gateway instance itself (constructor state);
 * this is everything that varies from one checkout to the next.
 */
final readonly class CheckoutRequest
{
    public function __construct(
        public InvoiceView $invoice,
        public bool $subscription,
        public CheckoutUrls $urls,
        public bool $testMode,
    ) {
    }
}
