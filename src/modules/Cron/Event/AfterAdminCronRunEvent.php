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
 * Event fired after the admin cron job completes.
 *
 * Listeners can use this event to perform post-cron tasks such as:
 * - Logging cron completion
 * - Cleanup of temporary files created during cron
 * - Sending notifications about cron results
 */
final class AfterAdminCronRunEvent extends Event
{
    public function __construct()
    {
        parent::__construct();
    }
}
