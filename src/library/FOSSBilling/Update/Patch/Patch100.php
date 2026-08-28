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

use FOSSBilling\System\Version;
use FOSSBilling\Update\Patcher;

class Patch100 implements PatchInterface
{
    public function getVersion(): int
    {
        return 100;
    }

    public function apply(Patcher $patcher): void
    {
        // Backfills stock reservations for orders that pre-date this version, since activation
        // no longer decrements stock itself (see Product\Service::reserveStockForOrder()).
        // Reservations are granted oldest-order-first per product and stop once stock runs out,
        // so an already-oversold product keeps as many orders "covered" as it has stock for.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/4130
        $orders = $patcher->fetchAll(
            "SELECT co.id AS order_id, co.product_id, co.quantity
             FROM client_order co
             INNER JOIN product p ON p.id = co.product_id
             WHERE co.status IN ('pending_setup', 'failed_setup')
               AND p.stock_control = 1
               AND NOT EXISTS (
                   SELECT 1 FROM client_order_meta m
                   WHERE m.client_order_id = co.id AND m.name = 'stock_reserved_qty'
               )
             ORDER BY co.product_id ASC, co.created_at ASC, co.id ASC"
        );
        // The NOT EXISTS above excludes already-backfilled orders, so this is safe to rerun
        // after a partial failure.

        if ($orders === []) {
            return;
        }

        // manual_update() (the only caller of this) applies patches without enabling
        // maintenance mode, so checkout can be reserving stock for other orders on the same
        // products the whole time this runs. Each order below is therefore reserved with the
        // same guarded, relative decrement real-time checkout uses (see
        // ProductRepository::decrementStockIfAvailable()) instead of computing a batch of
        // "remaining" values up front and overwriting quantity_in_stock with them - an
        // absolute write like that would silently erase whatever a concurrent checkout had
        // just decremented. The decrement's WHERE clause also re-checks the order's status and
        // reservation state at the moment of the attempt rather than trusting the snapshot
        // read above, so an order canceled or already reserved by then is skipped instead of
        // double-reserved.
        $now = date('Y-m-d H:i:s');
        $pdo = $patcher->getPdo();

        foreach ($orders as $order) {
            // Matches Product\Service::reserveStockForOrder(): a non-positive quantity is never
            // reserved, not rounded up to one.
            $quantity = (int) ($order['quantity'] ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $pdo->beginTransaction();

            try {
                $decrement = $pdo->prepare(
                    "UPDATE product p
                     INNER JOIN client_order co ON co.id = ?
                     SET p.quantity_in_stock = p.quantity_in_stock - ?, p.updated_at = ?
                     WHERE p.id = ?
                       AND p.quantity_in_stock >= ?
                       AND co.status IN ('pending_setup', 'failed_setup')
                       AND NOT EXISTS (
                           SELECT 1 FROM client_order_meta m
                           WHERE m.client_order_id = co.id AND m.name = 'stock_reserved_qty'
                       )"
                );
                $decrement->execute([
                    $order['order_id'],
                    $quantity,
                    $now,
                    $order['product_id'],
                    $quantity,
                ]);

                if ($decrement->rowCount() === 0) {
                    // Either out of stock, or the order stopped qualifying since the candidate
                    // list above was read - leave it unreserved, same as pre-patch behavior.
                    $pdo->rollBack();

                    continue;
                }

                $patcher->executeSql(
                    'INSERT INTO client_order_meta (client_order_id, name, value, created_at, updated_at)
                     VALUES (:order_id, :name, :value, :created_at, :updated_at)',
                    [
                        'order_id' => $order['order_id'],
                        'name' => 'stock_reserved_qty',
                        'value' => (string) $quantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();

                throw $e;
            }
        }
    }
}
