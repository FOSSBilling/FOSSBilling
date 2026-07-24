<?php

declare(strict_types=1);

namespace Box\Mod\Order\Repository;

use Box\Mod\Order\Entity\OrderStatus;
use Doctrine\ORM\EntityRepository;

class OrderStatusRepository extends EntityRepository
{
    /**
     * @return OrderStatus[]
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findBy(['clientOrderId' => $orderId]);
    }

    public function rmByOrderId(int $orderId): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM client_order_status WHERE client_order_id = :order_id',
            ['order_id' => $orderId]
        );
    }
}
