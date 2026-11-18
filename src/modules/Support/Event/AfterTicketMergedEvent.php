<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Event;

use FOSSBilling\Core\Events\Event;

final class AfterTicketMergedEvent extends Event
{
    /**
     * @param list<int> $mergedIds
     */
    public function __construct(
        public readonly int $ticketId,
        public readonly array $mergedIds,
    ) {
    }
}
