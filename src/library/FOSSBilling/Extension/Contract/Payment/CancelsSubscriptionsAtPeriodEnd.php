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
 * Can stop a subscription from renewing at the provider, without ending the
 * customer's already-paid-for current period.
 *
 * A separate interface from CancelsSubscriptions because a gateway may
 * plausibly support one and not the other.
 */
interface CancelsSubscriptionsAtPeriodEnd
{
    /**
     * @param string $reference the gateway's own subscription id
     */
    public function cancelSubscriptionAtPeriodEnd(string $reference): void;
}
