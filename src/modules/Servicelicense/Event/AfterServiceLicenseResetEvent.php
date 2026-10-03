<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Servicelicense\Event;

use FOSSBilling\Events\Event;

/** Dispatched after a license reset has been persisted. */
final class AfterServiceLicenseResetEvent extends Event
{
    public function __construct(
        public readonly ?int $licenseId,
        public readonly ?int $clientId,
    ) {
    }
}
