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

class Patch67 implements PatchInterface
{
    public function getVersion(): int
    {
        return 67;
    }

    public function apply(Patcher $patcher): void
    {
        // Add hash_expires_at column to invoice table. New invoices (and resends of
        // existing ones) get a hash_expires_at value computed from the
        // invoice_hash_lifetime_days system setting. NULL means "never expires"
        // and is the default for pre-existing rows.
        if (!$patcher->tableHasColumn('invoice', 'hash_expires_at')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `hash_expires_at` DATETIME DEFAULT NULL AFTER `updated_at`');
        }

        $patcher->executeSql(
            'INSERT INTO setting (param, value, public, category, hash, created_at, updated_at)
             VALUES (:param, :value, 0, :category, :hash, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE value = :value, updated_at = :updated_at',
            [
                'param' => 'invoice_hash_lifetime_days',
                'value' => '90',
                'category' => null,
                'hash' => null,
                'created_at' => '2026-06-01 12:00:00',
                'updated_at' => '2026-06-01 12:00:00',
            ]
        );

        // Regenerate invoice hashes that fall outside the modern 30-60 lowercase
        // hex format enforced by the new guest API regex validation. The client
        // area, gateway return URLs, and email links all build URLs from the
        // hash, so NULLing it (as an earlier revision did) broke those URLs —
        // see https://github.com/FOSSBilling/FOSSBilling/issues/3791.
        $expires = $patcher->computeInvoiceHashExpiration();
        $rows = $patcher->fetchAll(
            "SELECT id FROM invoice WHERE hash IS NOT NULL
               AND (LENGTH(hash) < 30 OR LENGTH(hash) > 60 OR CONVERT(hash USING utf8mb4) COLLATE utf8mb4_bin NOT REGEXP '^[a-f0-9]+$')"
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
