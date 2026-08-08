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
 * What a PaymentEvent means. Core decides how to settle it; a gateway only
 * classifies its own webhook payload.
 */
enum EventKind
{
    case Captured;
    case Refunded;
    case SubscriptionStarted;
    case SubscriptionCancelled;
}
