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
 * An inbound webhook / IPN request, as a gateway sees it.
 *
 * `headers` is new relative to the old `$data` array IPN gateways used to
 * receive — signature verification (Stripe) used to have to reach into
 * `$_SERVER` for it.
 */
final readonly class WebhookRequest
{
    /**
     * @param array<string, mixed>  $query   $_GET
     * @param array<string, mixed>  $body    $_POST
     * @param array<string, string> $headers
     */
    public function __construct(
        public array $query,
        public array $body,
        public string $rawBody,
        public array $headers,
    ) {
    }
}
