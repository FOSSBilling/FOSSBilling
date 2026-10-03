<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Cart\Event;

use FOSSBilling\Events\Event;

/** Dispatched before a product and its bundled cart items are added. */
final class BeforeProductAddedToCartEvent extends Event
{
    public function __construct(
        public readonly int $cartId,
        public readonly int $productId,
    ) {
    }
}
