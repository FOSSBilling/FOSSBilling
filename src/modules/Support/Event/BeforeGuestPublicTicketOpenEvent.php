<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired before a guest opens a public support ticket.
 * This event can be used to modify the ticket data before creation.
 *
 * @since v0.8.0
 */
final class BeforeGuestPublicTicketOpenEvent extends Event
{
    public function __construct(
        public readonly array $data,
        public readonly string $ip,
    ) {
        parent::__construct();
    }
}
