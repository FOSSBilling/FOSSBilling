<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an invoice reminder has been recorded. */
final class AfterAdminInvoiceReminderSentEvent extends Event
{
    public function __construct(public readonly int $invoiceId)
    {
    }
}
