<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Cron\Event;

use FOSSBilling\Events\Event;

/** Dispatched immediately before the admin cron jobs run. */
final class BeforeAdminCronRunEvent extends Event
{
}
