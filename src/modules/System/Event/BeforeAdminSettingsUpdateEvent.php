<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\System\Event;

use FOSSBilling\Events\Event;

/** Dispatched before admin system settings are validated and persisted. */
final class BeforeAdminSettingsUpdateEvent extends Event
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly array $data,
    ) {
    }
}
