<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Profile\Event;

use FOSSBilling\Events\Event;

/** Dispatched after an administrator's API key is changed. */
final class AfterAdminApiKeyChangeEvent extends Event
{
    public function __construct(
        public readonly int $adminId,
    ) {
    }
}
