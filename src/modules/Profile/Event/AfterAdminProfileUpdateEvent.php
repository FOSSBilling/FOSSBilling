<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Profile\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an administrator's profile data is saved. */
final class AfterAdminProfileUpdateEvent extends Event
{
    public function __construct(
        public readonly int $adminId,
    ) {
    }
}
