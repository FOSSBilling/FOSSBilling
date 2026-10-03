<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\System\Event;

use FOSSBilling\Events\Event;

/** Dispatched after admin system settings have been persisted. */
final class AfterAdminSettingsUpdateEvent extends Event
{
}
