<?php

declare(strict_types=1);

namespace Box\Mod\Order\Repository;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\Entity\Order;
use Doctrine\ORM\EntityRepository;

class OrderRepository extends EntityRepository
{
    /**
     * @return Order[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    /**
     * @return Order[]
     */
    public function findByUnpaidInvoiceId(int $invoiceId): array
    {
        return $this->findBy(['unpaidInvoiceId' => $invoiceId]);
    }

    public function findForClientById(int $clientId, int $orderId): ?Order
    {
        $order = $this->findOneBy(['id' => $orderId, 'clientId' => $clientId]);

        return $order instanceof Order ? $order : null;
    }

    public function findOneByProductId(int $productId): ?Order
    {
        $order = $this->findOneBy(['productId' => $productId]);

        return $order instanceof Order ? $order : null;
    }

    /**
     * @return Order[]
     */
    public function findByProductId(int $productId): array
    {
        return $this->findBy(['productId' => $productId]);
    }

    public function findOneByServiceTypeAndServiceId(string $serviceType, int $serviceId): ?Order
    {
        $order = $this->findOneBy(['serviceType' => $serviceType, 'serviceId' => $serviceId]);

        return $order instanceof Order ? $order : null;
    }

    public function findMasterByGroupAndClient(string $groupId, int $clientId): ?Order
    {
        $order = $this->findOneBy(['groupId' => $groupId, 'groupMaster' => true, 'clientId' => $clientId]);

        return $order instanceof Order ? $order : null;
    }

    public function findOneByGroupIdAndServiceType(string $groupId, string $serviceType): ?Order
    {
        $order = $this->findOneBy(['groupId' => $groupId, 'serviceType' => $serviceType]);

        return $order instanceof Order ? $order : null;
    }

    /**
     * @return Order[]
     */
    public function findAddonsExcluding(string $groupId, int $clientId, int $excludeOrderId): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.groupId = :groupId')
            ->andWhere('o.clientId = :clientId')
            ->andWhere('o.id != :excludeId')
            ->andWhere('(o.groupMaster IS NULL OR o.groupMaster = false)')
            ->setParameter('groupId', $groupId)
            ->setParameter('clientId', $clientId)
            ->setParameter('excludeId', $excludeOrderId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function getExpired(): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            <<<'SQL'
                SELECT o.id
                FROM client_order o
                LEFT JOIN product p ON p.id = o.product_id
                WHERE o.status = :status
                  AND o.expires_at IS NOT NULL
                  AND DATE_ADD(
                      o.expires_at,
                      INTERVAL GREATEST(COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0), 0) DAY
                  ) <= NOW()
                ORDER BY o.id
                SQL,
            ['status' => Order::STATUS_ACTIVE]
        );

        return $ids === [] ? [] : $this->findBy(['id' => array_map(intval(...), $ids)]);
    }

    /**
     * @return array<int, array{id: int, suspension_at: string}>
     */
    public function getDueSuspensionWarnings(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT due.id, due.suspension_at
                FROM (
                    SELECT
                        o.id,
                        GREATEST(COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0), 0) AS grace_days,
                        DATE_ADD(
                            o.expires_at,
                            INTERVAL GREATEST(COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0), 0) DAY
                        ) AS suspension_at
                    FROM client_order o
                    LEFT JOIN product p ON p.id = o.product_id
                    WHERE o.status = :status
                      AND o.expires_at IS NOT NULL
                ) due
                WHERE due.grace_days > 0
                  AND due.suspension_at > NOW()
                  AND due.suspension_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)
                ORDER BY due.id
                SQL,
            ['status' => Order::STATUS_ACTIVE]
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'suspension_at' => (string) $row['suspension_at'],
        ], $rows);
    }

    /**
     * @return Order[]
     */
    public function findAddons(int $masterOrderId): array
    {
        return $this->findBy(['groupId' => (string) $masterOrderId]);
    }

    /**
     * Pending-setup orders that were never paid and have gone stale, either
     * because their linked unpaid invoice has been overdue for more than the
     * given number of days, or - if that invoice is no longer a live unpaid
     * one (already removed by the invoice module's own "Remove Unpaid
     * Invoices After" cleanup, canceled, refunded, or simply never linked) -
     * because the order itself has sat untouched that long. Orders that any
     * paid invoice ever referenced are excluded, since a paid order can
     * legitimately stay pending_setup for a long time awaiting manual setup.
     * Used by the cron cleanup that removes stale, never-paid orders.
     *
     * @return Order[]
     */
    public function getStaleUnpaid(int $days): array
    {
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            <<<'SQL'
                SELECT o.id
                FROM client_order o
                WHERE o.status = :status
                  AND NOT EXISTS (
                      SELECT 1
                      FROM invoice_item ii
                      INNER JOIN invoice pi ON pi.id = ii.invoice_id
                      WHERE ii.rel_id = o.id AND ii.type = :item_type AND pi.status = :paid_status
                  )
                  AND (
                      EXISTS (
                          SELECT 1 FROM invoice i
                          WHERE i.id = o.unpaid_invoice_id
                            AND i.status = :unpaid_status
                            AND DATEDIFF(NOW(), i.due_at) > :days
                      )
                      OR (
                          NOT EXISTS (
                              SELECT 1 FROM invoice i
                              WHERE i.id = o.unpaid_invoice_id AND i.status = :unpaid_status
                          )
                          AND DATEDIFF(NOW(), o.created_at) > :days
                      )
                  )
                ORDER BY o.id
                SQL,
            [
                'status' => Order::STATUS_PENDING_SETUP,
                'item_type' => InvoiceItem::TYPE_ORDER,
                'paid_status' => Invoice::STATUS_PAID,
                'unpaid_status' => Invoice::STATUS_UNPAID,
                'days' => $days,
            ]
        );

        return $ids === [] ? [] : $this->findBy(['id' => array_map(intval(...), $ids)]);
    }
}
