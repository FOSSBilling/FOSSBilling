<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class TransactionRepository extends EntityRepository
{
    /**
     * Find a transaction by gateway transaction id and gateway id.
     * Mirrors the legacy `findOne('Transaction', 'txn_id = ? AND gateway_id = ?', ...)`.
     */
    public function findOneByTxnIdAndGatewayId(string $txnId, int $gatewayId): ?Transaction
    {
        $transaction = $this->findOneBy(['txnId' => $txnId, 'gatewayId' => $gatewayId]);

        return $transaction instanceof Transaction ? $transaction : null;
    }

    /**
     * Find a transaction by gateway id and IPN hash.
     * Mirrors the legacy `findOne('Transaction', 'gateway_id = ? AND ipn_hash = ?', ...)`.
     */
    public function findOneByGatewayIdAndIpnHash(int $gatewayId, string $ipnHash): ?Transaction
    {
        $transaction = $this->findOneBy(['gatewayId' => $gatewayId, 'ipnHash' => $ipnHash]);

        return $transaction instanceof Transaction ? $transaction : null;
    }

    /**
     * Find a processed transaction by gateway transaction id.
     * Mirrors the legacy `findOne('Transaction', 'status = "processed" and txn_id = ?', ...)`.
     */
    public function findOneProcessedByTxnId(string $txnId): ?Transaction
    {
        $transaction = $this->findOneBy(['status' => Transaction::STATUS_PROCESSED, 'txnId' => $txnId]);

        return $transaction instanceof Transaction ? $transaction : null;
    }

    /**
     * Find an active (received/processing/processed) transaction with the
     * given gateway transaction id and gateway id, excluding a given id.
     *
     * Used by the Stripe adapter to detect a competing transaction recorded by
     * a webhook arriving before the redirect flow (or vice versa).
     * Mirrors `findOne('Transaction', 'txn_id = :txn_id AND gateway_id = :gateway_id AND id != :id AND status IN (:s1, :s2, :s3)', ...)`.
     */
    public function findActiveByTxnIdAndGatewayId(string $txnId, int $gatewayId, int $excludeId): ?Transaction
    {
        return $this->competingTransactionQuery($txnId, $gatewayId, $excludeId, [
            Transaction::STATUS_RECEIVED,
            Transaction::STATUS_PROCESSING,
            Transaction::STATUS_PROCESSED,
        ])->getQuery()->getOneOrNullResult();
    }

    /**
     * Find a processing or processed transaction with the given gateway
     * transaction id, optionally filtered by gateway id and excluding a given
     * id. Used by the Stripe adapter for redirect/webhook deduplication.
     */
    public function findProcessingOrProcessedByTxnId(string $txnId, ?int $gatewayId = null, ?int $excludeId = null): ?Transaction
    {
        return $this->competingTransactionQuery($txnId, $gatewayId, $excludeId, [
            Transaction::STATUS_PROCESSING,
            Transaction::STATUS_PROCESSED,
        ])->getQuery()->getOneOrNullResult();
    }

    /**
     * Build the QueryBuilder shared by the competing-transaction lookups
     * (Stripe redirect/webhook deduplication).
     *
     * @param string[] $statuses
     */
    public function competingTransactionQuery(string $txnId, ?int $gatewayId, ?int $excludeId, array $statuses): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.txnId = :txn_id')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('txn_id', $txnId)
            ->setParameter('statuses', $statuses)
            ->setMaxResults(1);

        if ($gatewayId !== null) {
            $qb->andWhere('t.gatewayId = :gateway_id')
                ->setParameter('gateway_id', $gatewayId);
        }

        if ($excludeId !== null) {
            $qb->andWhere('t.id != :exclude_id')
                ->setParameter('exclude_id', $excludeId);
        }

        return $qb;
    }
}
