<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Notification\Event;

use FOSSBilling\Events\Event;

final class AfterAdminNotificationAddEvent extends Event
{
    public function __construct(
        public readonly int $notificationId,
    ) {
    }
}
