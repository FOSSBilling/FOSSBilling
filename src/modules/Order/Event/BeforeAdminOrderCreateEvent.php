<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched before an admin order is created. Input is observational; listeners cannot rewrite it. */
final class BeforeAdminOrderCreateEvent extends Event
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly int $clientId,
        public readonly int $productId,
        public readonly string $serviceType,
        public readonly array $input,
    ) {
    }
}
