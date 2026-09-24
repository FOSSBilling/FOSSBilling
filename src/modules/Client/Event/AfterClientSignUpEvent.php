<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Client\Event;

use FOSSBilling\Events\Event;

/** Dispatched after a guest signup has persisted its new client. */
final class AfterClientSignUpEvent extends Event
{
    public function __construct(
        public readonly int $clientId,
    ) {
    }
}
