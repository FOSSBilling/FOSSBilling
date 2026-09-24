<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched for every unpaid, approved invoice overdue or due today. */
final class AfterInvoiceIsDueEvent extends Event
{
    /** @param list<int> $reminderIntervals */
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $daysPassed,
        public readonly array $reminderIntervals,
    ) {
    }
}
