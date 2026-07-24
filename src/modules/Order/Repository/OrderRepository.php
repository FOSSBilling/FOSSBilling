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
}
