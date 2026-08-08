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
 * Can take a recurring payment at all.
 *
 * A marker interface: the work happens in checkout() when
 * CheckoutRequest::$subscription is true. This is deliberately not the same
 * capability as being able to cancel a subscription — see
 * CancelsSubscriptions and CancelsSubscriptionsAtPeriodEnd.
 */
interface SupportsSubscriptions
{
}
