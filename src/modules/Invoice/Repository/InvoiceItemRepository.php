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
use Box\Mod\Invoice\Entity\InvoiceItem;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class InvoiceItemRepository extends EntityRepository
{
    /** Strip leading zeros and compare the remaining bytes as text. */
    public static function normalizedLegacyOrderReference(Connection $connection, string $reference): string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => "TRIM(LEADING CAST('0' AS BINARY) FROM CAST($reference AS BINARY))",
            $platform instanceof PostgreSQLPlatform => "LTRIM($reference, '0') COLLATE \"C\"",
            default => "LTRIM($reference, '0') COLLATE BINARY",
        };
    }

    public static function orderIdAsReference(Connection $connection, string $orderId): string
    {
        return $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? "CAST($orderId AS BINARY)" : "CAST($orderId AS TEXT)";
    }

    /** Match historical payment evidence with optional trailing ASCII spaces. */
    public static function normalizedLegacyPaidOrderReference(Connection $connection, string $reference): string
    {
        return self::normalizedLegacyOrderReference($connection, "RTRIM($reference)");
    }

    /**
     * @return InvoiceItem[]
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->findBy(['invoice' => $this->getEntityManager()->getReference(Invoice::class, $invoiceId)]);
    }

    public function findPaidPendingRenewal(int $orderId): ?InvoiceItem
    {
        $item = $this->createQueryBuilder('ii')
            ->join('ii.invoice', 'i')
            ->where('ii.type = :type AND ii.task = :task AND ii.relId = :orderId')
            ->andWhere('ii.status IN (:statuses) AND i.status = :paid')
            ->setParameter('type', InvoiceItem::TYPE_ORDER)
            ->setParameter('task', InvoiceItem::TASK_RENEW)
            ->setParameter('orderId', (string) $orderId)
            ->setParameter('statuses', [InvoiceItem::STATUS_PENDING_SETUP, InvoiceItem::STATUS_FAILED])
            ->setParameter('paid', Invoice::STATUS_PAID)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return $item instanceof InvoiceItem ? $item : null;
    }

    /**
     * First invoice item of the given type for an invoice (legacy
     * RedBeanPHP `findOne` first-match semantics).
     */
    public function findOneByInvoiceIdAndType(int $invoiceId, string $type): ?InvoiceItem
    {
        $item = $this->findOneBy(['invoice' => $this->getEntityManager()->getReference(Invoice::class, $invoiceId), 'type' => $type]);

        return $item instanceof InvoiceItem ? $item : null;
    }

    /**
     * @return InvoiceItem[]
     */
    public function findFailed(): array
    {
        return $this->findBy(['status' => InvoiceItem::STATUS_FAILED], ['id' => 'DESC']);
    }

    /**
     * Build a QueryBuilder for invoice-item searches/listings.
     *
     * @param array $data filter and pagination parameters (unused for now)
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        return $this->createQueryBuilder('ii')
            ->orderBy('ii.id', \SortDirection::Descending);
    }
}
