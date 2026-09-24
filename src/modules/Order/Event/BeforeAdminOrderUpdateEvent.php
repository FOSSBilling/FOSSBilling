<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched before the order is updated. Input is observational; listeners cannot rewrite it. */
final class BeforeAdminOrderUpdateEvent extends Event
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly int $orderId,
        public readonly array $input,
    ) {
    }
}
