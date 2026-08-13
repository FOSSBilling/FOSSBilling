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

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class TransactionRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for transaction searches/listings.
     *
     * Mirrors the legacy `Box\Mod\Invoice\ServiceTransaction::getSearchQuery`
     * filters and returns the Transaction entity together with the gateway
     * name, so the caller can use `paginateMappedQuery` and skip the per-row
     * gateway lookup that `ServiceTransaction::toApiArray` would perform.
     * Each result row hydrates as `[0 => Transaction, 'gateway' => string|null]`.
     *
     * @param array $data optional filters: id, search, invoice_hash, invoice_id,
     *                    gateway_id, client_id, status, currency, type, txn_id,
     *                    date_from, date_to
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('pg.name AS gateway')
            ->leftJoin('t.gateway', 'pg');

        $id = $data['id'] ?? null;
        if ($id) {
            $qb->andWhere('t.id = :id')->setParameter('id', (int) $id);
        }

        $status = $data['status'] ?? null;
        if ($status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        $invoiceHash = $data['invoice_hash'] ?? null;
        if ($invoiceHash) {
            $qb->andWhere('IDENTITY(t.invoice) IN (SELECT i.id FROM ' . Invoice::class . ' i WHERE i.hash = :hash)')
                ->setParameter('hash', $invoiceHash);
        }

        $invoiceId = $data['invoice_id'] ?? null;
        if ($invoiceId) {
            $qb->andWhere('IDENTITY(t.invoice) = :invoice_id')->setParameter('invoice_id', (int) $invoiceId);
        }

        $gatewayId = $data['gateway_id'] ?? null;
        if ($gatewayId) {
            $qb->andWhere('IDENTITY(t.gateway) = :gateway_id')->setParameter('gateway_id', (int) $gatewayId);
        }

        $clientId = $data['client_id'] ?? null;
        if ($clientId) {
            $qb->andWhere('IDENTITY(t.invoice) IN (SELECT i.id FROM ' . Invoice::class . ' i WHERE i.clientId = :client_id)')
                ->setParameter('client_id', (int) $clientId);
        }

        $currency = $data['currency'] ?? null;
        if ($currency) {
            $qb->andWhere('t.currency = :currency')->setParameter('currency', $currency);
        }

        $type = $data['type'] ?? null;
        if ($type) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        $txnId = $data['txn_id'] ?? null;
        if ($txnId) {
            $qb->andWhere('t.txnId = :txn_id')->setParameter('txn_id', $txnId);
        }

        $dateFrom = $data['date_from'] ?? null;
        if ($dateFrom) {
            $qb->andWhere('t.createdAt >= :date_from')
                ->setParameter('date_from', date('Y-m-d H:i:s', (int) strtotime((string) $dateFrom)));
        }

        $dateTo = $data['date_to'] ?? null;
        if ($dateTo) {
            $qb->andWhere('t.createdAt <= :date_to')
                ->setParameter('date_to', date('Y-m-d H:i:s', (int) strtotime($dateTo . ' 23:59:59')));
        }

        $search = $data['search'] ?? null;
        if ($search) {
            $qb->andWhere('t.note LIKE :note OR IDENTITY(t.invoice) LIKE :search_invoice_id OR t.txnId LIKE :search_txn_id OR t.ipn LIKE :ipn')
                ->setParameter('note', "%$search%")
                ->setParameter('search_invoice_id', "%$search%")
                ->setParameter('search_txn_id', "%$search%")
                ->setParameter('ipn', "%$search%");
        }

        $qb->orderBy('t.id', 'DESC');

        return $qb;
    }

    /**
     * Find a transaction by gateway transaction id and gateway id.
     * Mirrors the legacy `findOne('Transaction', 'txn_id = ? AND gateway_id = ?', ...)`.
     */
    public function findOneByTxnIdAndGatewayId(string $txnId, int $gatewayId): ?Transaction
    {
        $transaction = $this->findOneBy(['txnId' => $txnId, 'gateway' => $this->getEntityManager()->getReference(PayGateway::class, $gatewayId)]);

        return $transaction instanceof Transaction ? $transaction : null;
    }

    /**
     * Find a transaction by gateway id and IPN hash.
     * Mirrors the legacy `findOne('Transaction', 'gateway_id = ? AND ipn_hash = ?', ...)`.
     */
    public function findOneByGatewayIdAndIpnHash(int $gatewayId, string $ipnHash): ?Transaction
    {
        $transaction = $this->findOneBy(['gateway' => $this->getEntityManager()->getReference(PayGateway::class, $gatewayId), 'ipnHash' => $ipnHash]);

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
            $qb->andWhere('IDENTITY(t.gateway) = :gateway_id')
                ->setParameter('gateway_id', $gatewayId);
        }

        if ($excludeId !== null) {
            $qb->andWhere('t.id != :exclude_id')
                ->setParameter('exclude_id', $excludeId);
        }

        return $qb;
    }
}
