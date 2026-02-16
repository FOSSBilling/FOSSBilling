<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Cron\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired before the admin cron job runs.
 *
 * Listeners can use this event to perform periodic tasks such as:
 * - Cleaning up old data
 * - Syncing external services
 * - Updating exchange rates
 * - Any other scheduled maintenance tasks
 *
 * @since v0.8.0
 */
final class BeforeAdminCronRunEvent extends Event
{
    public function __construct()
    {
        parent::__construct();
    }
}
