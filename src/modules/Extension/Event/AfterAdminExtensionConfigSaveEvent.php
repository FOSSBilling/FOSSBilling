<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Extension\Event;

use FOSSBilling\Events\Event;

/** Dispatched after extension configuration is saved; values are intentionally omitted. */
final class AfterAdminExtensionConfigSaveEvent extends Event
{
    /** @param list<string> $configurationKeys */
    public function __construct(
        public readonly string $extensionName,
        public readonly array $configurationKeys,
    ) {
    }
}
