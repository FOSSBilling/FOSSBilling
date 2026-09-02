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

class Patch114 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Enforce unique session_id on cart at the DB level (matches the Cart entity
        // UniqueConstraint and CartRepository::findBySessionId()'s existing assumption of at
        // most one cart per session). structure.sql has only ever had a plain index here.
        //
        // Reconcile any duplicate session_ids before adding the unique index: keep the
        // highest-id (most recently created) row per duplicated session_id - the one a
        // continuing checkout would actually be using - and delete the rest along with their
        // now-orphaned cart_product rows (cart_product.cart_id has no DB-level foreign key).
        // NULL session_id rows are left untouched: MySQL treats multiple NULLs as distinct
        // under a UNIQUE index, so they never violate it.
        $duplicateCartIdsToRemove = $patcher->fetchFirstColumn(
            'SELECT c.id FROM cart c
             INNER JOIN (
                 SELECT session_id, MAX(id) AS keep_id
                 FROM cart
                 WHERE session_id IS NOT NULL
                 GROUP BY session_id
                 HAVING COUNT(*) > 1
             ) d ON d.session_id = c.session_id AND c.id <> d.keep_id'
        );

        if ($duplicateCartIdsToRemove !== []) {
            $placeholders = implode(',', array_fill(0, count($duplicateCartIdsToRemove), '?'));
            $patcher->executeSql("DELETE FROM `cart_product` WHERE `cart_id` IN ({$placeholders})", $duplicateCartIdsToRemove);
            $patcher->executeSql("DELETE FROM `cart` WHERE `id` IN ({$placeholders})", $duplicateCartIdsToRemove);
        }

        $indexes = $patcher->fetchAll(sprintf('SHOW INDEX FROM `%s`', $patcher->quoteIdentifier('cart')));
        $sessionIdIndex = null;
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? null) === 'session_id_idx') {
                $sessionIdIndex = $index;

                break;
            }
        }

        if ($sessionIdIndex === null) {
            return;
        }

        if (((int) $sessionIdIndex['Non_unique']) !== 0) {
            $patcher->executeSql('ALTER TABLE `cart` DROP INDEX `session_id_idx`');
            $patcher->executeSql('ALTER TABLE `cart` ADD UNIQUE INDEX `session_id_idx` (`session_id`)');
        }
    }
}
