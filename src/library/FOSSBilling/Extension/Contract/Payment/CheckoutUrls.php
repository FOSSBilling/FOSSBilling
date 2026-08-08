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
 * The URLs a gateway needs to send the browser back to FOSSBilling.
 *
 * `AdapterAbstract`'s constructor used to demand four of these
 * (return_url/cancel_url/notify_url/redirect_url) inside the merchant's
 * config array. `redirect_url` was always `notify_url` with a `redirect=1`
 * flag appended by core (see `ServicePayGateway::getCallbackRedirect()`), so
 * a gateway never needed to be handed a fourth URL to reach the same
 * endpoint — it is folded back into `callback` here.
 */
final readonly class CheckoutUrls
{
    public function __construct(
        /** Where the browser goes after a successful payment. */
        public string $return,
        /** Where the browser goes if the customer cancels. */
        public string $cancel,
        /** Where the gateway posts/redirects payment notifications. */
        public string $callback,
    ) {
    }
}
