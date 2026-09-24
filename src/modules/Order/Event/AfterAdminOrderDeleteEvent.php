<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an order is deleted. */
final class AfterAdminOrderDeleteEvent extends Event
{
    public function __construct(public readonly int $orderId)
    {
    }
}
