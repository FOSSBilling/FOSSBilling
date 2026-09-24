<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched after a transaction update has been flushed. */
final class AfterAdminTransactionUpdateEvent extends Event
{
    public function __construct(
        public readonly int $transactionId,
    ) {
    }
}
