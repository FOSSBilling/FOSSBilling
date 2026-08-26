<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\Doctrine\RowLock;

class InvoiceRepository extends EntityRepository
{
    public function findByHash(?string $hash): ?Invoice
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        $invoice = $this->findOneBy(['hash' => $hash]);

        return $invoice instanceof Invoice ? $invoice : null;
    }

    public function existsByGatewayId(int $gatewayId): bool
    {
        return (bool) $this->createQueryBuilder('i')
            ->select('1')
            ->andWhere('IDENTITY(i.gateway) = :gateway_id')
            ->setParameter('gateway_id', $gatewayId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Build a QueryBuilder for invoice searches/listings.
     *
     * Mirrors the legacy `Box\Mod\Invoice\Service::getSearchQuery` filters,
     * returning the Invoice entity directly so the caller can use
     * `paginateMappedQuery` and skip the per-row `find()` lookup that the
     * raw-SQL path required.
     *
     * @param array $data optional filters: search, id, nr, client_id, client,
     *                    status, approved, currency, created_at, date_from,
     *                    date_to, paid_at, order_id
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i');

        $orderId = $data['order_id'] ?? null;
        if ($orderId) {
            $qb->andWhere('i.id IN (SELECT IDENTITY(ii.invoice) FROM ' . InvoiceItem::class . ' ii WHERE ii.relId = :order_id AND ii.type = :item_type)')
                ->setParameter('order_id', (int) $orderId)
                ->setParameter('item_type', InvoiceItem::TYPE_ORDER);
        }

        $id = $data['id'] ?? null;
        if ($id) {
            $qb->andWhere('i.id = :id')->setParameter('id', (int) $id);
        }

        $idNr = $data['nr'] ?? null;
        if ($idNr) {
            $qb->andWhere('i.id = :id_nr OR i.nr = :id_nr')->setParameter('id_nr', $idNr);
        }

        $approved = $data['approved'] ?? null;
        if ($approved !== null && $approved !== '') {
            $qb->andWhere('i.approved = :approved')->setParameter('approved', \FOSSBilling\Utils\Normalizer::normalizeBoolean($approved));
        }

        $status = $data['status'] ?? null;
        if ($status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }

        $currency = $data['currency'] ?? null;
        if ($currency) {
            $qb->andWhere('i.currency = :currency')->setParameter('currency', $currency);
        }

        $clientId = $data['client_id'] ?? null;
        if ($clientId) {
            $qb->andWhere('i.clientId = :client_id')->setParameter('client_id', (int) $clientId);
        }

        $client = $data['client'] ?? null;
        if ($client) {
            $qb->andWhere('i.clientId IN (SELECT c.id FROM ' . Client::class . ' c WHERE c.firstName LIKE :client_search OR c.lastName LIKE :client_search OR c.id = :client OR c.email = :client)')
                ->setParameter('client_search', $client . '%')
                ->setParameter('client', $client);
        }

        $createdAt = $data['created_at'] ?? null;
        if ($createdAt) {
            $day = date('Y-m-d', (int) strtotime((string) $createdAt));
            $nextDay = date('Y-m-d', (int) strtotime($createdAt . ' +1 day'));
            $qb->andWhere('i.createdAt >= :created_at_start AND i.createdAt < :created_at_end')
                ->setParameter('created_at_start', $day . ' 00:00:00')
                ->setParameter('created_at_end', $nextDay . ' 00:00:00');
        }

        $dateFrom = $data['date_from'] ?? null;
        if ($dateFrom) {
            $qb->andWhere('i.createdAt >= :date_from')
                ->setParameter('date_from', date('Y-m-d H:i:s', (int) strtotime((string) $dateFrom)));
        }

        $dateTo = $data['date_to'] ?? null;
        if ($dateTo) {
            $qb->andWhere('i.createdAt <= :date_to')
                ->setParameter('date_to', date('Y-m-d H:i:s', (int) strtotime($dateTo . ' 23:59:59')));
        }

        $paidAt = $data['paid_at'] ?? null;
        if ($paidAt) {
            $day = date('Y-m-d', (int) strtotime((string) $paidAt));
            $nextDay = date('Y-m-d', (int) strtotime($paidAt . ' +1 day'));
            $qb->andWhere('i.paidAt >= :paid_at_start AND i.paidAt < :paid_at_end')
                ->setParameter('paid_at_start', $day . ' 00:00:00')
                ->setParameter('paid_at_end', $nextDay . ' 00:00:00');
        }

        $search = $data['search'] ?? null;
        if ($search) {
            $searchNumeric = (int) preg_replace('/[^0-9]/', '', (string) $search);
            $qb->andWhere('i.id = :search_numeric_id OR i.nr LIKE :search_like OR i.id LIKE :search OR i.id IN (SELECT IDENTITY(ii.invoice) FROM ' . InvoiceItem::class . ' ii WHERE ii.title LIKE :search_like)')
                ->setParameter('search_numeric_id', $searchNumeric)
                ->setParameter('search_like', '%' . $search . '%')
                ->setParameter('search', $search);
        }

        $qb->orderBy('i.id', 'DESC');

        return $qb;
    }

    /**
     * Aggregate invoice-item totals for the given invoice ids.
     *
     * Replaces the correlated subqueries (`list_subtotal`,
     * `list_taxable_subtotal`) of the legacy summary search with one grouped
     * query bounded by the page size. Invoices without items do not appear in
     * the result; callers default missing ids to zero.
     *
     * @param int[] $invoiceIds
     *
     * @return array<int, array{subtotal: float, taxable_subtotal: float}> keyed by invoice id
     */
    public function getInvoiceTotals(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(ii.invoice) AS invoice_id, SUM(COALESCE(ii.price, 0) * COALESCE(ii.quantity, 1)) AS subtotal, SUM(CASE WHEN ii.taxed = true THEN (COALESCE(ii.price, 0) * COALESCE(ii.quantity, 1)) ELSE 0 END) AS taxable_subtotal')
            ->from(InvoiceItem::class, 'ii')
            ->where('IDENTITY(ii.invoice) IN (:invoice_ids)')
            ->setParameter('invoice_ids', $invoiceIds)
            ->groupBy('ii.invoice');

        $totals = [];
        foreach ($qb->getQuery()->getScalarResult() as $row) {
            $totals[(int) $row['invoice_id']] = [
                'subtotal' => (float) $row['subtotal'],
                'taxable_subtotal' => (float) $row['taxable_subtotal'],
            ];
        }

        return $totals;
    }

    /**
     * Must be called within a transaction, held for as long as the status is acted on.
     */
    public function lockAndGetStatus(int $invoiceId): ?string
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \FOSSBilling\Exception\BaseException('Invoice status cannot be locked outside of a transaction.');
        }

        $status = $connection->fetchOne(
            'SELECT status FROM invoice WHERE id = :id' . RowLock::suffix($connection),
            ['id' => $invoiceId],
        );

        return $status === false ? null : (string) $status;
    }

    /**
     * @return Invoice[]
     */
    public function findPaid(): array
    {
        return $this->findBy(['status' => Invoice::STATUS_PAID], ['id' => 'DESC']);
    }

    /**
     * @return Invoice[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    /**
     * Approved, unpaid invoices that have not been reminded and were
     * created before the given cutoff timestamp.
     *
     * @return Invoice[]
     */
    public function findUnpaidApprovedNotRemindedBefore(int $cutoffTimestamp): array
    {
        $cutoff = new \DateTime();
        $cutoff->setTimestamp($cutoffTimestamp);

        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.approved = true')
            ->andWhere('i.remindedAt IS NULL')
            ->andWhere('i.createdAt < :cutoff')
            ->setParameter('status', Invoice::STATUS_UNPAID)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    /**
     * Paid invoices that contain an invoice item referencing the given
     * rel_id (typically an order id).
     *
     * @return Invoice[]
     */
    public function findPaidByRelId(string|int $relId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.id IN (SELECT IDENTITY(ii.invoice) FROM ' . InvoiceItem::class . ' ii WHERE ii.relId = :relId AND ii.type = :type)')
            ->setParameter('status', Invoice::STATUS_PAID)
            ->setParameter('relId', (string) $relId)
            ->setParameter('type', InvoiceItem::TYPE_ORDER)
            ->getQuery()
            ->getResult();
    }

    /**
     * Unpaid invoices whose due date is more than the given number of days
     * in the past. Used by the cron cleanup that expires stale unpaid
     * invoices.
     *
     * @return Invoice[]
     */
    public function findUnpaidOlderThan(int $days): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.dueAt IS NOT NULL')
            ->andWhere('DATE_DIFF(CURRENT_TIMESTAMP(), i.dueAt) > :days')
            ->setParameter('status', Invoice::STATUS_UNPAID)
            ->setParameter('days', $days)
            ->getQuery()
            ->getResult();
    }

    /**
     * Latest invoice that has a non-null nr — fallback for invoice
     * number generation.
     */
    public function findLatestWithNr(): ?Invoice
    {
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.nr IS NOT NULL')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }
}
