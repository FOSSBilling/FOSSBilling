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
    public function findAddonsExcluding(int $groupId, int $clientId, int $excludeOrderId): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.groupId = :groupId')
            ->andWhere('o.clientId = :clientId')
            ->andWhere('o.id != :excludeId')
            ->andWhere('o.groupMaster IS NULL OR o.groupMaster = false')
            ->setParameter('groupId', (string) $groupId)
            ->setParameter('clientId', $clientId)
            ->setParameter('excludeId', $excludeOrderId)
            ->getQuery()
            ->getResult();
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
}
