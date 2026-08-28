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

class Patch26 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Migration steps from BoxBilling to FOSSBilling - added favicon settings.
        $patcher->executeSql(
            'INSERT INTO setting (param, value, public, category, hash, created_at, updated_at) VALUES (:param, :value, 0, :category, :hash, :created_at, :updated_at)',
            [
                'param' => 'company_favicon',
                'value' => 'public/branding/favicon.ico',
                'category' => null,
                'hash' => null,
                'created_at' => '2023-01-08 12:00:00',
                'updated_at' => '2023-01-08 12:00:00',
            ]
        );
    }
}
