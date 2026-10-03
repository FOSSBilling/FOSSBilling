<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Order\Event;

use FOSSBilling\Events\Event;

/** Dispatched before the batch job processes old suspended orders for cancellation. */
final class BeforeAdminBatchCancelSuspendedOrdersEvent extends Event
{
}
