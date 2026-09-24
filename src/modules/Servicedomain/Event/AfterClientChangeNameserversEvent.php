<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Servicedomain\Event;

use FOSSBilling\Events\Event;

/** Dispatched after nameservers have been persisted following the registrar update. */
final class AfterClientChangeNameserversEvent extends Event
{
    public function __construct(
        public readonly ?int $domainId,
        public readonly ?int $clientId,
        public readonly ?string $ns1,
        public readonly ?string $ns2,
        public readonly ?string $ns3,
        public readonly ?string $ns4,
    ) {
    }
}
