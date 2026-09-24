<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched before an admin-requested transaction process operation begins. */
final class BeforeAdminTransactionProcessEvent extends Event
{
    public function __construct(
        public readonly int $transactionId,
    ) {
    }
}
