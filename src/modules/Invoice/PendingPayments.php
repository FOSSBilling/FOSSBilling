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
use Doctrine\DBAL\Connection;

/**
 * Bank debits (Stripe BECS, SEPA and Bacs Direct Debit) take days to clear.
 * Until the gateway confirms one, its invoice stays unpaid but shows
 * "Payment pending": no reminders, no automatic charge, no credit applied,
 * and its orders are neither suspended nor removed. A debit still clearing
 * is a received transaction with txn_status processing; the gateway's answer
 * marks the invoice paid or ends the wait.
 */
final class PendingPayments
{
    // A debit the gateway never answers for stops counting after this (SEPA takes up to 14 business days).
    private const int DAYS = 21;

    /** SQL condition: the invoice in $column has a payment still clearing. */
    public static function sql(string $column, Connection $connection): string
    {
        $table = $connection->quoteSingleIdentifier('transaction');

        return "EXISTS (SELECT 1 FROM {$table} pp WHERE pp.invoice_id = {$column} AND pp.status = 'received' AND pp.txn_status = 'processing' AND pp.created_at > '" . self::since() . "')";
    }

    /** The invoice's payment still clearing ({amount, currency, created_at}), or null. */
    public static function forInvoice(Connection $connection, int $invoiceId): ?array
    {
        // `transaction` is a reserved word (bare use is a syntax error on SQLite): quote it.
        $table = $connection->quoteSingleIdentifier('transaction');
        $row = $connection->fetchAssociative(
            "SELECT amount, currency, created_at FROM {$table} WHERE invoice_id = ? AND status = 'received' AND txn_status = 'processing' AND created_at > ? ORDER BY id DESC LIMIT 1",
            [$invoiceId, self::since()]
        );
        if ($row === false || $row === []) {
            return null;
        }

        return $row;
    }

    /** Whether an unpaid invoice of the order has a payment still clearing. */
    public static function forOrder(Connection $connection, int $orderId): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM invoice_item ii JOIN invoice i ON i.id = ii.invoice_id WHERE ii.type = ? AND ii.rel_id = ? AND i.status = ? AND ' . self::sql('i.id', $connection) . ' LIMIT 1',
            ['order', (string) $orderId, Invoice::STATUS_UNPAID]
        );
    }

    private static function since(): string
    {
        return date('Y-m-d H:i:s', strtotime('-' . self::DAYS . ' days'));
    }
}
