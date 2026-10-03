<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Profile\Event;

use FOSSBilling\Events\Event;

/** Dispatched after a client's profile data is saved. */
final class AfterClientProfileUpdateEvent extends Event
{
    public function __construct(
        public readonly int $clientId,
    ) {
    }
}
