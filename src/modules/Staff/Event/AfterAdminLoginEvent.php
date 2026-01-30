<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired after a successful admin login.
 */
final class AfterAdminLoginEvent extends Event
{
    public function __construct(
        public readonly int $adminId,
        public readonly string $ip,
    ) {
        parent::__construct();
    }
}
