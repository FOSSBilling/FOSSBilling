<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Cron\Event;

use FOSSBilling\Events\Event;

/** Dispatched after the admin cron jobs finish. */
final class AfterAdminCronRunEvent extends Event
{
}
