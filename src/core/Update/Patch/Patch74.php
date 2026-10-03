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

class Patch74 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Backfill hashes NULLed by the original revision of patch67.
        $expires = $patcher->computeInvoiceHashExpiration();
        $rows = $patcher->fetchAll(
            "SELECT id FROM invoice WHERE hash IS NULL OR LENGTH(hash) < 30 OR LENGTH(hash) > 60 OR CONVERT(hash USING utf8mb4) COLLATE utf8mb4_bin NOT REGEXP '^[a-f0-9]+$'"
        );
        foreach ($rows as $row) {
            $patcher->executeSql(
                'UPDATE invoice SET hash = :hash, hash_expires_at = :expires WHERE id = :id',
                [
                    'hash' => bin2hex(random_bytes(random_int(15, 30))),
                    'expires' => $expires,
                    'id' => $row['id'],
                ]
            );
        }
    }
}
