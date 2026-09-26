<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an admin deletes an unpaid, unissued invoice. */
final class AfterAdminInvoiceDeleteEvent extends Event
{
    public function __construct(public readonly int $invoiceId)
    {
    }
}
