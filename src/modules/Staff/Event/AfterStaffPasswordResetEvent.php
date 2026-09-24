<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Staff\Event;

use FOSSBilling\Events\Event;

/** Dispatched after a staff account password reset has completed. */
final class AfterStaffPasswordResetEvent extends Event
{
    public function __construct(
        public readonly int $adminId,
    ) {
    }
}
