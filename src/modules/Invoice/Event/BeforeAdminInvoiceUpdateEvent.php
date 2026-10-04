<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched before an editable invoice is updated; submitted values are omitted. */
final class BeforeAdminInvoiceUpdateEvent extends Event
{
    /** @param list<string> $changedFields */
    public function __construct(
        public readonly int $invoiceId,
        public readonly array $changedFields,
    ) {
    }
}
