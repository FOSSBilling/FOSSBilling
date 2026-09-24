<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Client\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an admin created a new client. */
final class AfterAdminClientCreateEvent extends Event
{
    public function __construct(
        public readonly int $clientId,
    ) {
    }
}
