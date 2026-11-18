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
        $connection = $this->getEntityManager()->getConnection();
        $legacyReference = self::normalizedLegacyPaidOrderReference($connection, 'ii.rel_id');
        $id = $connection->fetchOne(
            "SELECT ii.id FROM invoice_item ii INNER JOIN invoice i ON i.id = ii.invoice_id
             WHERE ii.type = :type AND ii.task = :task
               AND (ii.order_id = :order_id OR (ii.order_id IS NULL AND $legacyReference = :legacy_order_id))
               AND ii.status IN (:pending, :failed) AND i.status = :paid
             ORDER BY ii.id LIMIT 1",
            [
                'type' => InvoiceItem::TYPE_ORDER,
                'task' => InvoiceItem::TASK_RENEW,
                'order_id' => $orderId,
                'legacy_order_id' => (string) $orderId,
                'pending' => InvoiceItem::STATUS_PENDING_SETUP,
                'failed' => InvoiceItem::STATUS_FAILED,
                'paid' => Invoice::STATUS_PAID,
            ],
        );
        $item = $id === false ? null : $this->find((int) $id);

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
