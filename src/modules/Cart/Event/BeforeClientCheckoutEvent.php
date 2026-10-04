<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Cart\Event;

use FOSSBilling\Events\Event;

final class BeforeClientCheckoutEvent extends Event
{
    public function __construct(
        public readonly int $cartId,
        public readonly int $clientId,
        public readonly ?string $ip,
    ) {
    }
}
