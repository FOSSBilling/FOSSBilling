<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Profile\Event;

use FOSSBilling\Events\Event;

/** Dispatched before a client's profile data is validated and saved. */
final class BeforeClientProfileUpdateEvent extends Event
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly int $clientId,
        public readonly array $data,
    ) {
    }
}
