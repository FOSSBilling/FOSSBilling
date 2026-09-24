<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/** Dispatched before a transaction is created with a filtered, observational input snapshot. */
final class BeforeAdminTransactionCreateEvent extends Event
{
    /** @param array<string, bool|float|int|string|null> $input */
    public function __construct(
        public readonly array $input,
    ) {
    }
}
