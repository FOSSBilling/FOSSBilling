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
 * Implemented by a gateway that receives asynchronous notifications
 * (webhooks / IPN) from its provider.
 *
 * A gateway parses; core settles. This replaces `getInvoiceId()`,
 * `isIpnValid()`, `getTransaction()`, `processTransaction()` and the
 * `setOutput()`/`getOutput()` side channel. A gateway knows what a webhook
 * payload means; it has no access to invoices, credit or transaction rows —
 * core applies the events this returns.
 */
interface HandlesWebhooks
{
    public function handleWebhook(WebhookRequest $request): WebhookResult;
}
