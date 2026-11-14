<?php

declare(strict_types=1);

namespace Box\Mod\Order\Repository;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Doctrine\ORM\EntityRepository;

class OrderMetaRepository extends EntityRepository
{
    /**
     * @return array<string, string>
     */
    public function getPairsForOrder(int $orderId): array
    {
        $metadata = $this->findBy(['order' => $this->getEntityManager()->getReference(Order::class, $orderId)]);
        $pairs = [];
        foreach ($metadata as $meta) {
            $pairs[$meta->getName()] = $meta->getValue();
        }

        return $pairs;
    }

    public function findOneByOrderIdAndName(int $orderId, string $name): ?OrderMeta
    {
        $meta = $this->findOneBy(['order' => $this->getEntityManager()->getReference(Order::class, $orderId), 'name' => $name]);

        return $meta instanceof OrderMeta ? $meta : null;
    }

    public function deleteByOrderId(int $orderId): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id',
            ['order_id' => $orderId]
        );
    }

    public function deleteEditableByOrderId(int $orderId): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND (name IS NULL OR name <> :reserved)',
            ['order_id' => $orderId, 'reserved' => \Box\Mod\Order\Service::META_DELETION_STATE],
        );
    }

    public function deleteByOrderIdAndName(int $orderId, string $name): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND name = :name',
            ['order_id' => $orderId, 'name' => $name]
        );
    }
}
