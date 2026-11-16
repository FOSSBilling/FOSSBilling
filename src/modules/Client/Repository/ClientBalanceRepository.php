<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\Core\Doctrine\RowLock;

class ClientBalanceRepository extends EntityRepository
{
    /** Match historical receipts or hold ambiguous funds for staff review. */
    public function reconcileHistoricalGatewayReceiptsForInvoice(int $invoiceId): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $txTable = $connection->quoteSingleIdentifier('transaction');
        // Match historical receipts only when their provider fingerprint and
        // original transaction reference identify one processed transaction.
        $clientId = $connection->fetchOne('SELECT client_id FROM invoice WHERE id = ?', [$invoiceId]);
        $receipts = $connection->fetchAllAssociative("SELECT id, type, rel_id, description, amount, gateway_receipt_key FROM client_balance WHERE client_id = ? AND amount > 0 AND gateway_transaction_id IS NULL AND (gateway_receipt_key IS NOT NULL OR (type = 'transaction' AND description LIKE 'Stripe %') OR (type = 'PayPal' AND description LIKE 'PayPal transaction %'))", [$clientId]);
        $stripeIds = [];
        $paypalIds = [];
        foreach ($receipts as $receipt) {
            if ($receipt['type'] === 'transaction' && ctype_digit((string) $receipt['rel_id'])) {
                $stripeIds[] = (int) $receipt['rel_id'];
            } elseif ($receipt['type'] === 'PayPal') {
                $provider = $this->extractGatewayProviderId((string) $receipt['description']);
                if ($provider !== null && $provider !== '') {
                    $paypalIds[] = $provider;
                }
            }
        }
        $transactions = $receipts === [] ? [] : $connection->executeQuery(
            "SELECT t.id, t.invoice_id, t.txn_id, t.amount, t.status, t.gateway_id, g.gateway FROM $txTable t LEFT JOIN invoice i ON i.id = t.invoice_id JOIN pay_gateway g ON g.id = t.gateway_id WHERE i.client_id = ? OR (t.invoice_id IS NULL AND ((g.gateway = 'Stripe' AND t.id IN (?)) OR (g.gateway = 'PayPalEmail' AND t.txn_id IN (?))))",
            [$clientId, array_values(array_unique($stripeIds)), array_values(array_unique($paypalIds))],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ArrayParameterType::STRING],
        )->fetchAllAssociative();
        foreach ($receipts as $receipt) {
            $provider = $this->extractGatewayProviderId((string) $receipt['description']);
            $matches = [];
            foreach ($transactions as $tx) {
                $sameReference = ($tx['gateway'] === 'Stripe' && $receipt['type'] === 'transaction' && (string) $receipt['rel_id'] === (string) $tx['id'])
                    || ($tx['gateway'] === 'PayPalEmail' && $receipt['type'] === 'PayPal' && $provider !== null && (string) $tx['txn_id'] === $provider);
                if ($tx['status'] === 'processed' && $sameReference && $provider !== null && $provider !== '' && $receipt['gateway_receipt_key'] !== null
                    && hash_equals((string) $receipt['gateway_receipt_key'], hash('sha256', $tx['gateway_id'] . ':' . $provider))
                    && round((float) $receipt['amount'], 2) === round((float) $tx['amount'], 2)) {
                    $matches[] = $tx;
                }
            }
            if (count($matches) === 1 && !$connection->fetchOne('SELECT id FROM client_balance WHERE gateway_transaction_id = ?', [$matches[0]['id']])) {
                $connection->executeStatement('UPDATE client_balance SET gateway_transaction_id = ? WHERE id = ? AND gateway_transaction_id IS NULL', [$matches[0]['id'], $receipt['id']]);

                continue;
            }
            // An unresolved receipt could belong to this invoice only when a
            // processed transaction has the same amount. Never release it by
            // making an unsupported association.
            foreach ($transactions as $tx) {
                // A retained provider reference can rule out an unrelated
                // same-amount attempt without guessing the receipt's owner.
                if ($receipt['type'] === 'transaction' && $receipt['rel_id'] !== null && $receipt['rel_id'] !== ''
                    && (string) $receipt['rel_id'] !== (string) $tx['id']) {
                    continue;
                }
                if ($receipt['type'] === 'PayPal' && $provider !== null && $tx['txn_id'] !== null && $tx['txn_id'] !== ''
                    && (string) $tx['txn_id'] !== $provider) {
                    continue;
                }
                if ((int) $tx['invoice_id'] === $invoiceId && round((float) $tx['amount'], 2) === round((float) $receipt['amount'], 2)) {
                    throw new \FOSSBilling\Core\Exception\InformationException('A gateway receipt needs staff reconciliation before this invoice can be settled. Use invoice/transaction_reconcile_receipt after checking the provider payment.');
                }
            }
        }
    }

    private function extractGatewayProviderId(string $description): ?string
    {
        foreach (['Stripe transaction ', 'Stripe subscription initial payment ', 'Stripe subscription recurring payment ', 'PayPal transaction '] as $prefix) {
            if (str_starts_with($description, $prefix)) {
                return substr($description, strlen($prefix));
            }
        }

        return null;
    }

    /** Receipt-backed funds attached to an invoice, excluding manual payments. */
    public function getGatewayReceiptSumForInvoice(int $invoiceId): float
    {
        $connection = $this->getEntityManager()->getConnection();
        $txTable = $connection->quoteSingleIdentifier('transaction');

        return (float) $connection->fetchOne("SELECT COALESCE(SUM(b.amount), 0) FROM client_balance b JOIN $txTable t ON t.id = b.gateway_transaction_id WHERE t.invoice_id = ? AND t.status = ? AND b.gateway_receipt_key IS NOT NULL", [$invoiceId, 'processed']);
    }

    /** @return array<int, float> */
    public function getUnpaidDepositReceiptReservations(array $clientIds, int $exceptInvoiceId = 0): array
    {
        if ($clientIds === []) {
            return [];
        }
        $connection = $this->getEntityManager()->getConnection();
        $txTable = $connection->quoteSingleIdentifier('transaction');
        $rows = $connection->executeQuery("SELECT i.client_id, SUM(b.amount) AS amount FROM client_balance b JOIN $txTable t ON t.id = b.gateway_transaction_id JOIN invoice i ON i.id = t.invoice_id WHERE i.client_id IN (?) AND i.status = ? AND i.id <> ? AND t.status = ? AND b.gateway_receipt_key IS NOT NULL AND EXISTS (SELECT 1 FROM invoice_item ii WHERE ii.invoice_id = i.id AND ii.type = ?) GROUP BY i.client_id", [$clientIds, 'unpaid', $exceptInvoiceId, 'processed', 'deposit'], [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING])->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['client_id']] = (float) $row['amount'];
        }
        // Reserve unlinked old receipts while they may belong to an unpaid
        // deposit or to a transaction whose processing has not completed.
        $legacy = $connection->executeQuery("SELECT b.client_id, SUM(b.amount) AS amount FROM client_balance b WHERE b.client_id IN (?) AND b.amount > 0 AND b.gateway_transaction_id IS NULL AND (b.gateway_receipt_key IS NOT NULL OR (b.type = 'transaction' AND b.description LIKE 'Stripe %') OR (b.type = 'PayPal' AND b.description LIKE 'PayPal transaction %')) AND EXISTS (SELECT 1 FROM $txTable t JOIN invoice i ON i.id = t.invoice_id WHERE i.client_id = b.client_id AND i.status = ? AND t.amount = b.amount AND ((b.type = 'transaction' AND (b.rel_id IS NULL OR b.rel_id = '' OR b.rel_id = TRIM(CAST(t.id AS CHAR(20))))) OR (b.type = 'PayPal' AND (t.txn_id IS NULL OR t.txn_id = '' OR t.txn_id = SUBSTR(b.description, 20)))) AND ((t.status = ? AND EXISTS (SELECT 1 FROM invoice_item ii WHERE ii.invoice_id = i.id AND ii.type = ?)) OR t.status IN (?, ?, ?))) AND NOT EXISTS (SELECT 1 FROM $txTable pt JOIN invoice pi ON pi.id = pt.invoice_id JOIN pay_gateway pg ON pg.id = pt.gateway_id WHERE pi.client_id = b.client_id AND pi.id <> ? AND pi.status = ? AND pt.status = ? AND pt.amount = b.amount AND NOT EXISTS (SELECT 1 FROM invoice_item paid_item WHERE paid_item.invoice_id = pi.id AND paid_item.type = ?) AND ((pg.gateway = 'Stripe' AND b.type = 'transaction' AND b.rel_id = TRIM(CAST(pt.id AS CHAR(20)))) OR (pg.gateway = 'PayPalEmail' AND b.type = 'PayPal' AND pt.txn_id = SUBSTR(b.description, 20)))) GROUP BY b.client_id", [$clientIds, 'unpaid', 'processed', 'deposit', 'received', 'processing', 'error', $exceptInvoiceId, 'unpaid', 'processed', 'deposit'], [\Doctrine\DBAL\ArrayParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING])->fetchAllAssociative();
        foreach ($legacy as $row) {
            $clientId = (int) $row['client_id'];
            $result[$clientId] = ($result[$clientId] ?? 0.0) + (float) $row['amount'];
        }

        return $result;
    }

    public function getClientBalanceSum(int $clientId): float
    {
        $result = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT SUM(amount) FROM client_balance WHERE client_id = :client_id',
            ['client_id' => $clientId],
        );

        return (float) ($result ?? 0);
    }

    /**
     * Must be called within a transaction, held until the deduction has been written.
     */
    public function getClientBalanceSumForUpdate(int $clientId): float
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \FOSSBilling\Core\Exception\BaseException('Client balance cannot be locked outside of a transaction.');
        }

        // The balance sums insert-only rows, so a concurrent deduction inserts rather than updates
        // and there is nothing for the sum to serialize against. Lock the client row as the mutex.
        $connection->fetchOne(
            'SELECT id FROM client WHERE id = :client_id' . RowLock::suffix($connection),
            ['client_id' => $clientId],
        );

        // A locking read, because a plain one is served from the transaction snapshot, which under
        // REPEATABLE READ can predate the deduction we just waited on above.
        // PostgreSQL rejects locking clauses on aggregate queries outright, so the SUM goes
        // without FOR UPDATE there. That loses nothing: under PostgreSQL's default READ COMMITTED
        // isolation every statement sees a fresh snapshot, so once the client-row mutex above is
        // held, this read already reflects everything committed before it. The locking read only
        // matters where the transaction snapshot can predate the mutex wait (MySQL/MariaDB
        // REPEATABLE READ).
        $platform = $connection->getDatabasePlatform();
        $sumLock = ($platform instanceof SQLitePlatform || $platform instanceof PostgreSQLPlatform) ? '' : ' FOR UPDATE';

        $result = $connection->fetchOne(
            'SELECT SUM(amount) FROM client_balance WHERE client_id = :client_id' . $sumLock,
            ['client_id' => $clientId],
        );

        return (float) ($result ?? 0);
    }
}
