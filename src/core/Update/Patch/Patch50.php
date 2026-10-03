<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch50 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $patcher->migrateEncryptedColumn('email_template', 'id', 'vars', 'vars IS NOT NULL AND vars != :empty', [
            'empty' => '',
        ]);
        $patcher->migrateEncryptedColumn('extension_meta', 'id', 'meta_value', 'meta_key = :meta_key AND meta_value IS NOT NULL AND meta_value != :empty', [
            'meta_key' => 'config',
            'empty' => '',
        ]);
    }
}
