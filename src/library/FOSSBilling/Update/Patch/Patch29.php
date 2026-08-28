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

class Patch29 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Patch to update email templates to use format_date/format_datetime filters
        // instead of removed bb_date/bb_datetime filters.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/948
        $patcher->executeSql('UPDATE email_template SET content = REPLACE(content, :search, :replace)', [
            'search' => 'bb_date',
            'replace' => 'format_date',
        ]);

        $patcher->executeSql('UPDATE email_template SET content = REPLACE(content, :search, :replace)', [
            'search' => 'bb_datetime',
            'replace' => 'format_datetime',
        ]);
    }
}
