<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched before unpaid orders are removed in a batch. */
final class BeforeAdminBatchCancelUnpaidOrdersEvent extends Event
{
}
