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

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Doctrine\ORM\EntityRepository;

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
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.approved = true')
            ->andWhere('i.remindedAt IS NULL')
            ->andWhere('i.createdAt < :cutoff')
            ->setParameter('status', Invoice::STATUS_UNPAID)
            ->setParameter('cutoff', new \DateTime('@' . $cutoffTimestamp))
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
            ->andWhere('i.id IN (SELECT ii.invoiceId FROM ' . InvoiceItem::class . ' ii WHERE ii.relId = :relId)')
            ->setParameter('status', Invoice::STATUS_PAID)
            ->setParameter('relId', (string) $relId)
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
