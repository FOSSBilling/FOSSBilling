<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\System\Event;

use FOSSBilling\Events\Event;

/** Dispatched immediately before the core update files are installed. */
final class BeforeAdminUpdateCoreEvent extends Event
{
}
