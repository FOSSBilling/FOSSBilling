<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Profile\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired before admin/staff updates their profile.
 *
 * @since v0.8.0
 */
final class BeforeAdminStaffProfileUpdateEvent extends Event
{
    public function __construct(
        public readonly int $adminId,
        public readonly array $data,
    ) {
        parent::__construct();
    }
}
