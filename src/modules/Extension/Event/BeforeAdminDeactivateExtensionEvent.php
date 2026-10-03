<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Extension\Event;

use FOSSBilling\Events\Event;

/** Dispatched before an extension is deactivated. */
final class BeforeAdminDeactivateExtensionEvent extends Event
{
    public function __construct(
        public readonly int $extensionRecordId,
        public readonly ?string $extensionType,
        public readonly ?string $extensionName,
    ) {
    }
}
