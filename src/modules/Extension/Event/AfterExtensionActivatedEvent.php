<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Extension\Event;

use FOSSBilling\Events\Event;

final class AfterExtensionActivatedEvent extends Event
{
    public function __construct(
        public readonly ?int $extensionId,
        public readonly ?string $type,
        public readonly ?string $name,
    ) {
    }
}
