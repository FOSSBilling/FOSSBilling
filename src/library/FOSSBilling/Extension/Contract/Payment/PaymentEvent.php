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
 * One thing that happened at the provider, as reported by a webhook.
 *
 * A gateway only classifies its own payload into one or more of these; core
 * settles them (applies payments, records refunds, starts/cancels
 * subscriptions) and dedupes on (gateway, reference) so no gateway has to
 * implement its own idempotency check.
 */
final readonly class PaymentEvent
{
    public function __construct(
        public EventKind $kind,
        /** The gateway's own id for the underlying charge/refund/subscription event. Core dedupes on this. */
        public string $reference,
        public float $amount,
        public string $currency,
        public ?int $invoiceId = null,
        public ?string $subscriptionReference = null,
        public ?float $fee = null,
    ) {
    }
}
