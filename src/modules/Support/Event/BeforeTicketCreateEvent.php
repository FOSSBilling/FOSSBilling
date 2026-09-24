<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support\Event;

use FOSSBilling\Events\Event;

/** Raised before an admin or client creates a ticket. */
final class BeforeTicketCreateEvent extends Event
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly TicketActorRole $actor,
        public readonly int $clientId,
        public readonly array $input,
    ) {
    }
}
