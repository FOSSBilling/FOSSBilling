<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Extension\Event;

use FOSSBilling\Events\Event;

/** Dispatched before extension files and module data are uninstalled. */
final class BeforeAdminUninstallExtensionEvent extends Event
{
    public function __construct(
        public readonly string $extensionType,
        public readonly string $extensionName,
    ) {
    }
}
