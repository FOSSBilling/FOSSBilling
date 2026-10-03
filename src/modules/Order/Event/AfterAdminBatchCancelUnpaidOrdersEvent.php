<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched after unpaid orders have been removed in a batch. */
final class AfterAdminBatchCancelUnpaidOrdersEvent extends Event
{
}
