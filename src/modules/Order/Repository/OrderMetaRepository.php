<?php

declare(strict_types=1);

namespace Box\Mod\Order\Repository;

use Box\Mod\Order\Entity\OrderMeta;
use Doctrine\ORM\EntityRepository;

class OrderMetaRepository extends EntityRepository
{
    /**
     * @return array<string, string>
     */
    public function getPairsForOrder(int $orderId): array
    {
        $metas = $this->findBy(['clientOrderId' => $orderId]);
        $pairs = [];
        foreach ($metas as $meta) {
            $pairs[$meta->getName()] = $meta->getValue();
        }

        return $pairs;
    }

    public function findOneByOrderIdAndName(int $orderId, string $name): ?OrderMeta
    {
        $meta = $this->findOneBy(['clientOrderId' => $orderId, 'name' => $name]);

        return $meta instanceof OrderMeta ? $meta : null;
    }

    public function deleteByOrderId(int $orderId): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id',
            ['order_id' => $orderId]
        );
    }
}
