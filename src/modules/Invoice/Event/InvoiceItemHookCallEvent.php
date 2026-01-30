<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired when a TYPE_HOOK_CALL invoice item is executed.
 * This replaces the legacy dynamic hook call.
 */
final class InvoiceItemHookCallEvent extends Event
{
    public function __construct(
        public readonly string $task,
        public readonly array $params,
    ) {
        parent::__construct();
    }
}
