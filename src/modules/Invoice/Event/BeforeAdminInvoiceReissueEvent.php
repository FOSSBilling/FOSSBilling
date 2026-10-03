<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched before an invoice is reissued. */
final class BeforeAdminInvoiceReissueEvent extends Event
{
    public function __construct(public readonly int $invoiceId)
    {
    }
}
