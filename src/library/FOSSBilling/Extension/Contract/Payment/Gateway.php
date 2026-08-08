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
 * The one method every payment gateway must implement.
 *
 * Replaces `getHtml()`, `singlePayment()`, `recurrentPayment()`,
 * `getServiceUrl()` and `getType()`. Everything `getConfig()` used to carry
 * moves to the manifest (extension.json settings) or becomes a capability
 * interface, so there is no `describe()` here.
 *
 * Merchant settings are constructor state (`new Gateway($settings)`);
 * per-payment data arrives as the argument to checkout().
 */
interface Gateway
{
    public function checkout(CheckoutRequest $request): Checkout;
}
