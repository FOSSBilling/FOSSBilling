<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Servicelicense\Event;

use FOSSBilling\Events\Event;

/** Dispatched before a license is reset; validator data is intentionally omitted. */
final class BeforeServiceLicenseResetEvent extends Event
{
    public function __construct(
        public readonly ?int $licenseId,
        public readonly ?int $clientId,
    ) {
    }
}
