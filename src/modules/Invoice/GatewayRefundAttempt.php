<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice;

use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Exception\InformationException;

/** A refund intent survives rollback of the credit-note transaction. */
final class GatewayRefundAttempt
{
    private const string TABLE = 'invoice_gateway_refund_attempt';

    /** The caller must hold the per-invoice refund lock, outside the credit-note transaction. */
    public static function prepare(Connection $db, int $invoiceId, string $fingerprint, bool $replaySafe): string
    {
        if ($db->isTransactionActive()) {
            throw new InformationException('Cannot prepare a gateway refund inside a database transaction.');
        }
        $row = $db->fetchAssociative('SELECT id, fingerprint, created_at FROM ' . self::TABLE . " WHERE invoice_id = ? AND state = 'pending' ORDER BY created_at LIMIT 1", [$invoiceId]);
        if ($row !== false) {
            if (!$replaySafe || !hash_equals((string) $row['fingerprint'], $fingerprint)
                || strtotime((string) $row['created_at']) < time() - 40 * 86400) {
                throw new InformationException('A previous PayPal refund may have been sent but was not recorded. Reconcile it with PayPal before making another refund.');
            }

            return (string) $row['id'];
        }
        $id = bin2hex(random_bytes(16));
        $db->insert(self::TABLE, ['id' => $id, 'invoice_id' => $invoiceId, 'fingerprint' => $fingerprint, 'state' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);

        return $id;
    }

    /** @return array{id: string, fingerprint: string, created_at: string}|null */
    public static function pending(Connection $db, int $invoiceId): ?array
    {
        $row = $db->fetchAssociative('SELECT id, fingerprint, created_at FROM ' . self::TABLE . " WHERE invoice_id = ? AND state = 'pending' ORDER BY created_at LIMIT 1", [$invoiceId]);

        return $row === false || $row === [] ? null : $row;
    }

    public static function dismiss(Connection $db, int $invoiceId, string $id): void
    {
        if ($db->update(self::TABLE, ['state' => 'not_refunded'], ['id' => $id, 'invoice_id' => $invoiceId, 'state' => 'pending']) !== 1) {
            throw new InformationException('This refund attempt is no longer pending.');
        }
    }

    /** Called inside the credit-note transaction. */
    public static function complete(Connection $db, string $id): void
    {
        if ($db->update(self::TABLE, ['state' => 'completed'], ['id' => $id, 'state' => 'pending']) !== 1) {
            throw new InformationException('The gateway refund attempt could not be completed.');
        }
    }
}
