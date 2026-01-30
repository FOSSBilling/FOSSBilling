<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Event;

use FOSSBilling\Events\Event;

/**
 * Event fired after admin uninstalls an extension.
 *
 * @since v0.8.0
 */
final class AfterAdminUninstallExtensionEvent extends Event
{
    public function __construct(
        public readonly string $extensionId,
        public readonly string $type,
    ) {
        parent::__construct();
    }
}
