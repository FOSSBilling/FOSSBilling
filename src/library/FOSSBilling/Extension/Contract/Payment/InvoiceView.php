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
 * The invoice as a payment gateway sees it.
 *
 * A read-only copy rather than the model core stores, so that the storage
 * layer can change without breaking every gateway.
 */
final readonly class InvoiceView
{
    /**
     * @param list<array<string, mixed>> $lines
     */
    public function __construct(
        public int $id,
        public int $clientId,
        public ?int $gatewayId,
        public string $currency,
        public string $hash,
        public bool $approved,
        public ?string $status = null,
        public ?string $serie = null,
        public ?int $nr = null,
        public ?string $buyerEmail = null,
        public ?string $buyerFirstName = null,
        public ?string $buyerLastName = null,
        public float $total = 0.0,
        public float $subtotal = 0.0,
        public float $tax = 0.0,
        public array $lines = [],
    ) {
    }
}
