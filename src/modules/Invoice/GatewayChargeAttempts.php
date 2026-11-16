<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice;

use Doctrine\DBAL\Connection;

/** Durable intent, written under the invoice charge lock before any provider request. */
final class GatewayChargeAttempts
{
    public static function referenceParameter(object $adapter): string
    {
        return match (true) {
            $adapter instanceof \Payment_Adapter_PayPalEmail => 'paypal_capture',
            $adapter instanceof \Payment_Adapter_PayPalAutoPay => 'paypal_txn',
            default => 'payment_intent',
        };
    }

    public static function pending(Connection $db, int $invoiceId): ?array
    {
        return $db->fetchAssociative("SELECT * FROM invoice_gateway_charge_attempt WHERE invoice_id = ? AND state = 'pending' ORDER BY created_at, id LIMIT 1", [$invoiceId]) ?: null;
    }

    /** @param-out string $ownerToken */
    public static function begin(Connection $db, int $invoiceId, int $gatewayId, ?string &$ownerToken = null): string
    {
        if ($db->isTransactionActive()) {
            throw new \LogicException('A payment attempt must be committed before contacting the gateway.');
        }
        $id = bin2hex(random_bytes(16));
        $ownerToken = bin2hex(random_bytes(16));
        $db->insert('invoice_gateway_charge_attempt', [
            'id' => $id, 'invoice_id' => $invoiceId, 'gateway_id' => $gatewayId,
            'state' => 'pending', 'owner_token' => $ownerToken, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /** Recovery keeps the provider request ID but gives local writes a new owner. */
    public static function takeOwnership(Connection $db, string $id, ?string $expectedToken): string
    {
        $token = bin2hex(random_bytes(16));
        $predicate = $expectedToken === null ? 'owner_token IS NULL' : 'owner_token = ?';
        $parameters = [$token, $id];
        if ($expectedToken !== null) {
            $parameters[] = $expectedToken;
        }
        if ($db->executeStatement("UPDATE invoice_gateway_charge_attempt SET owner_token = ? WHERE id = ? AND state = 'pending' AND {$predicate}", $parameters) !== 1) {
            throw new \FOSSBilling\Core\Payment\OwnershipLost('The payment attempt changed during recovery. Reload it before continuing.');
        }

        return $token;
    }

    /** Called under ProcessingFence with the captured ownership token. */
    public static function recordPayment(Connection $db, string $id, string $paymentId): void
    {
        if ($db->executeStatement("UPDATE invoice_gateway_charge_attempt SET payment_id = ? WHERE id = ? AND state = 'pending' AND payment_id IS NULL", [$paymentId, $id]) !== 1) {
            throw new \LogicException('The payment attempt has changed. Reconcile it before continuing.');
        }
    }

    public static function resolve(Connection $db, string $id, string $state, ?array $resolution = null): void
    {
        if (!in_array($state, ['settled', 'failed'], true)) {
            throw new \InvalidArgumentException('Invalid payment resolution.');
        }
        $db->executeStatement("UPDATE invoice_gateway_charge_attempt SET state = ?, resolved_at = ?, resolution = ? WHERE id = ? AND state = 'pending'", [$state, date('Y-m-d H:i:s'), $resolution === null ? null : json_encode($resolution, JSON_THROW_ON_ERROR), $id]);
    }
}
