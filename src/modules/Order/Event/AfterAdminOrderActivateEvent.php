<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an order has been provisioned and activated. */
final class AfterAdminOrderActivateEvent extends Event
{
    /** @param array<string, mixed> $resultParameters Additional template parameters returned by the service action. */
    public function __construct(
        public readonly int $orderId,
        public readonly array $resultParameters = [],
    ) {
    }
}
