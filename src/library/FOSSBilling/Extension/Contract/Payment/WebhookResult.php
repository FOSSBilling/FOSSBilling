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
 * What a gateway's HandlesWebhooks::handleWebhook() returns.
 */
final readonly class WebhookResult
{
    /**
     * @param list<PaymentEvent> $events
     */
    public function __construct(
        public array $events,
        /** Body to send back to the provider, if it expects one other than a bare 200. */
        public ?string $responseBody = null,
    ) {
    }

    /**
     * The webhook was recognised but carries nothing for core to settle
     * (an informational event, or one this gateway doesn't act on).
     */
    public static function ignore(?string $responseBody = null): self
    {
        return new self([], $responseBody);
    }
}
