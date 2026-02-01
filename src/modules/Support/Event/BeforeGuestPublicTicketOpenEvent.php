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
 *
 * @since v0.8.0
 */
final class BeforeGuestPublicTicketOpenEvent extends Event
{
    public function __construct(
        public readonly string $email,
        public readonly string $ip,
        public readonly ?string $name = null,
        public readonly ?string $subject = null,
        public readonly ?string $message = null,
    ) {
        parent::__construct();
    }
}
