<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Doctrine\DBAL\Connection;

/**
 * Bank debits and other pending gateway payments can take days to clear.
 * Until the gateway confirms one, its invoice stays unpaid but shows
 * "Payment pending": no reminders, no automatic charge, no credit applied,
 * and its orders are neither suspended nor removed. A payment still clearing
 * has gateway status processing; the gateway's answer
 * marks the invoice paid or ends the wait.
 */
final class PendingPayments
{
    /** SQL condition: the invoice in $column has a payment still clearing. */
    public static function sql(string $column, Connection $connection): string
    {
        $table = $connection->quoteSingleIdentifier('transaction');

        return "EXISTS (SELECT 1 FROM {$table} pp WHERE pp.invoice_id = {$column} AND pp.status IN ('received', 'processing', 'error') AND pp.txn_status = 'processing')";
    }

    /** The invoice's payment still clearing ({amount, currency, created_at}), or null. */
    public static function forInvoice(Connection $connection, int $invoiceId): ?array
    {
        // `transaction` is a reserved word (bare use is a syntax error on SQLite): quote it.
        $table = $connection->quoteSingleIdentifier('transaction');
        $row = $connection->fetchAssociative(
            "SELECT amount, currency, created_at FROM {$table} WHERE invoice_id = ? AND status IN ('received', 'processing', 'error') AND txn_status = 'processing' ORDER BY id DESC LIMIT 1",
            [$invoiceId]
        );
        if ($row === false || $row === []) {
            return null;
        }

        return $row;
    }

    /** Whether an unpaid invoice of the order has a payment still clearing. */
    public static function forOrder(Connection $connection, int $orderId): bool
    {
        $legacyReference = InvoiceItemRepository::normalizedLegacyOrderReference($connection, 'ii.rel_id');

        return (bool) $connection->fetchOne(
            "SELECT 1 FROM invoice_item ii JOIN invoice i ON i.id = ii.invoice_id WHERE ii.type = ? AND (ii.order_id = ? OR (ii.order_id IS NULL AND $legacyReference = ?)) AND i.status = ? AND " . self::sql('i.id', $connection) . ' LIMIT 1',
            ['order', $orderId, (string) $orderId, Invoice::STATUS_UNPAID]
        );
    }
}
