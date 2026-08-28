<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch77 implements PatchInterface
{
    public function getVersion(): int
    {
        return 77;
    }

    public function apply(Patcher $patcher): void
    {
        // The email queue table was renamed from `mod_email_queue` to
        // `email_queue` when the `QueuedEmail` Doctrine entity was introduced.
        // Rename it for installations that still use the legacy table name.
        if ($patcher->tableExists('mod_email_queue') && !$patcher->tableExists('email_queue')) {
            $patcher->executeSql('RENAME TABLE `mod_email_queue` TO `email_queue`');
        }
    }
}
