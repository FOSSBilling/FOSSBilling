<?php

declare(strict_types=1);

namespace Box\Mod\Order\Repository;

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
    public function getSoonExpiringActiveOrders(int $daysUntilExpiration): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.status = :status')
            ->andWhere('o.expiresAt IS NOT NULL')
            ->andWhere('o.expiresAt <= :expiry_date')
            ->setParameter('status', Order::STATUS_ACTIVE)
            ->setParameter('expiry_date', new \DateTime('+' . $daysUntilExpiration . ' days'))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function getExpired(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.expiresAt IS NOT NULL')
            ->andWhere('o.expiresAt <= :now')
            ->setParameter('status', Order::STATUS_ACTIVE)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
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
     * given number of days (falling back to the order's own creation date if
     * that invoice has no due date set), or - if that invoice is no longer a
     * live unpaid one (already removed by the invoice module's own "Remove
     * Unpaid Invoices After" cleanup, canceled, refunded, or simply never
     * linked) - because the order itself has sat untouched that long. Orders
     * that any paid invoice ever referenced are excluded, since a paid order
     * can legitimately stay pending_setup for a long time awaiting manual
     * setup. Used by the cron cleanup that removes stale, never-paid orders.
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
                            AND DATEDIFF(NOW(), COALESCE(i.due_at, o.created_at)) > :days
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
                'item_type' => \Model_InvoiceItem::TYPE_ORDER,
                'paid_status' => \Model_Invoice::STATUS_PAID,
                'unpaid_status' => \Model_Invoice::STATUS_UNPAID,
                'days' => $days,
            ]
        );

        return $ids === [] ? [] : $this->findBy(['id' => array_map(intval(...), $ids)]);
    }
}
