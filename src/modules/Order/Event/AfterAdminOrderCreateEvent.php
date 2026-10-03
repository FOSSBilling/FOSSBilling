<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an admin order has been created. */
final class AfterAdminOrderCreateEvent extends Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $clientId,
        public readonly int $productId,
        public readonly string $serviceType,
    ) {
    }
}
