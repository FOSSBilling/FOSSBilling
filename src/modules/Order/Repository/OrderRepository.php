<?php

declare(strict_types=1);

namespace Box\Mod\Order\Repository;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Order\Entity\Order;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\Core\Doctrine\RowLock;
use FOSSBilling\Core\Doctrine\SqlExpr;

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

    /**
     * Must be called within a transaction, held for as long as the invoice
     * link is acted on.
     */
    public function lockAndGetUnpaidInvoiceId(int $orderId, bool $requireOrder = false): ?int
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \FOSSBilling\Core\Exception\BaseException('Order link cannot be locked outside of a transaction.');
        }

        $row = $connection->fetchAssociative(
            'SELECT unpaid_invoice_id FROM client_order WHERE id = :id' . RowLock::suffix($connection),
            ['id' => $orderId],
        );

        if ($row === false && $requireOrder) {
            throw new \FOSSBilling\Core\Exception\InformationException('Order not found');
        }
        if ($row === false || $row['unpaid_invoice_id'] === null) {
            return null;
        }

        return (int) $row['unpaid_invoice_id'];
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
        $connection = $this->getEntityManager()->getConnection();
        // The grace period is per-row (order override, falling back to the product's), so it
        // can't be reduced to a single bound parameter the way :now below is - see SqlExpr.
        $graceDays = SqlExpr::greatestOfTwo($connection, 'COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0)', '0');
        $expiresPlusGrace = SqlExpr::addDays($connection, 'o.expires_at', $graceDays);

        $ids = $connection->fetchFirstColumn(
            <<<SQL
                SELECT o.id
                FROM client_order o
                LEFT JOIN product p ON p.id = o.product_id
                WHERE o.status = :status
                  AND o.expires_at IS NOT NULL
                  AND {$expiresPlusGrace} <= :now
                ORDER BY o.id
                SQL,
            ['status' => Order::STATUS_ACTIVE, 'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
        );

        return $ids === [] ? [] : $this->findBy(['id' => array_map(intval(...), $ids)]);
    }

    /**
     * @return array<int, array{id: int, suspension_at: string}>
     */
    public function getDueSuspensionWarnings(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        $graceDays = SqlExpr::greatestOfTwo($connection, 'COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0)', '0');
        $suspensionAt = SqlExpr::addDays($connection, 'o.expires_at', $graceDays);

        $now = new \DateTimeImmutable();
        $rows = $connection->fetchAllAssociative(
            <<<SQL
                SELECT due.id, due.suspension_at
                FROM (
                    SELECT
                        o.id,
                        {$graceDays} AS grace_days,
                        {$suspensionAt} AS suspension_at
                    FROM client_order o
                    LEFT JOIN product p ON p.id = o.product_id
                    WHERE o.status = :status
                      AND o.expires_at IS NOT NULL
                ) due
                WHERE due.grace_days > 0
                  AND due.suspension_at > :now
                  AND due.suspension_at <= :tomorrow
                ORDER BY due.id
                SQL,
            [
                'status' => Order::STATUS_ACTIVE,
                'now' => $now->format('Y-m-d H:i:s'),
                'tomorrow' => $now->modify('+1 day')->format('Y-m-d H:i:s'),
            ]
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
        $connection = $this->getEntityManager()->getConnection();

        // Destructive-caller guard: the NOT EXISTS below excludes orders with any
        // paid invoice item, matched through invoice_item.order_id. Until the
        // backfill that populates it has recorded completion (see
        // Patcher::backfillInvoiceItemOrderIds()), a paid order with only legacy
        // (rel_id) references would read as never-paid and be deleted. Defer
        // instead: a skipped run only delays cleanup of orders that are already
        // overdue, while a wrongful deletion cannot be undone.
        try {
            $backfilled = $connection->fetchOne(
                'SELECT value FROM setting WHERE param = :param',
                ['param' => 'invoice_item_order_id_backfill_done']
            );
        } catch (\Throwable) {
            // Fail closed like the missing flag: without proof the linkage is
            // complete, a destructive batch must not run.
            return [];
        }
        // The completion records the derivation rule version (see
        // Patcher::backfillInvoiceItemOrderIds()): an older version means a repeat
        // scan is still converging, so cleanup waits for it like a missing flag.
        if ($backfilled !== \FOSSBilling\Core\Update\Patcher::INVOICE_ITEM_ORDER_BACKFILL_RULE_VERSION) {
            return [];
        }

        // DATEDIFF(NOW(), X) > :days, compared only by calendar date, is equivalent to
        // X < cutoff, where cutoff is midnight $days days ago - a portable stand-in that also
        // lets $days collapse to a single bound parameter computed once in PHP.
        $cutoff = (new \DateTimeImmutable('today'))->modify("-{$days} days")->format('Y-m-d H:i:s');

        $ids = $connection->fetchFirstColumn(
            <<<'SQL'
                SELECT o.id
                FROM client_order o
                WHERE o.status = :status
                  AND NOT EXISTS (
                      SELECT 1
                      FROM invoice_item ii
                      INNER JOIN invoice pi ON pi.id = ii.invoice_id
                      WHERE ii.order_id = o.id AND ii.type = :item_type AND pi.status = :paid_status
                  )
                  AND (
                      EXISTS (
                          SELECT 1 FROM invoice i
                          WHERE i.id = o.unpaid_invoice_id
                            AND i.status = :unpaid_status
                            AND COALESCE(i.due_at, o.created_at) < :cutoff
                      )
                      OR (
                          NOT EXISTS (
                              SELECT 1 FROM invoice i
                              WHERE i.id = o.unpaid_invoice_id AND i.status = :unpaid_status
                          )
                          AND o.created_at < :cutoff
                      )
                  )
                ORDER BY o.id
                SQL,
            [
                'status' => Order::STATUS_PENDING_SETUP,
                'item_type' => InvoiceItem::TYPE_ORDER,
                'paid_status' => Invoice::STATUS_PAID,
                'unpaid_status' => Invoice::STATUS_UNPAID,
                'cutoff' => $cutoff,
            ]
        );

        return $ids === [] ? [] : $this->findBy(['id' => array_map(intval(...), $ids)]);
    }

    /**
     * Whether any paid invoice item references the order, through the typed
     * linkage or - for rows the backfill has not reached yet - the legacy
     * rel_id reference. Check the indexed typed link first, and only normalize
     * legacy references in rows that are still missing the typed link.
     *
     * Used as a last-moment re-check before a destructive batch acts: the
     * stale-order scan matches on the typed linkage alone, so an order whose
     * paid item missed the backfill (a concurrent write during its final page,
     * a manual row, or a downgraded writer) would otherwise read as never paid.
     */
    public function hasPaidInvoiceItem(int $orderId): bool
    {
        $connection = $this->getEntityManager()->getConnection();
        $query = 'SELECT 1 FROM invoice_item ii INNER JOIN invoice pi ON pi.id = ii.invoice_id
                  WHERE ii.type = :item_type AND pi.status = :paid_status';
        $params = ['order_id' => $orderId, 'item_type' => InvoiceItem::TYPE_ORDER, 'paid_status' => Invoice::STATUS_PAID];
        $legacyReference = InvoiceItemRepository::normalizedLegacyOrderReference($connection, 'ii.rel_id');
        $orderReference = InvoiceItemRepository::orderIdAsReference($connection, ':order_id');

        return $connection->fetchOne(
            "$query AND ii.order_id = :order_id
             UNION ALL
             $query AND ii.order_id IS NULL AND $legacyReference = $orderReference
             LIMIT 1",
            $params,
        ) !== false;
    }
}
