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
        // Scalar reads bypass the identity map: claims must reflect what the
        // preceding lock holder committed, even in a long-running worker.
        return $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            'SELECT name, value FROM client_order_meta WHERE client_order_id = ?',
            [$orderId]
        );
    }

    public function findOneByOrderIdAndName(int $orderId, string $name): ?OrderMeta
    {
        $meta = $this->findOneBy(['order' => $this->getEntityManager()->getReference(Order::class, $orderId), 'name' => $name]);

        return $meta instanceof OrderMeta ? $meta : null;
    }

    public function isDeletionCheckpoint(OrderMeta $meta): bool
    {
        return $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT id FROM client_order_meta WHERE id = :id AND name = :reserved',
            ['id' => $meta->getId(), 'reserved' => \Box\Mod\Order\Service::META_DELETION_STATE],
        ) !== false;
    }

    /** Compare checkpoint state using the metadata column's actual collation. */
    public function getLifecycleCheckpointRows(int $orderId): array
    {
        $service = \Box\Mod\Order\Service::class;
        $conditions = ['name IN (:cancel, :claim, :phase, :options)'];
        $parameters = ['order_id' => $orderId, 'cancel' => $service::META_CANCEL_AT_PERIOD_END, 'claim' => $service::META_SCHEDULED_CANCEL_CLAIM, 'phase' => $service::META_SCHEDULED_CANCEL_PHASE, 'options' => $service::META_SCHEDULED_CANCEL_OPTIONS];
        foreach ([$service::META_PAID_RENEWAL_OUTCOME_PREFIX, $service::META_FREE_RENEWAL_CLAIM_PREFIX, $service::META_FREE_RENEWAL_RETRY_PREFIX] as $i => $prefix) {
            $conditions[] = 'SUBSTRING(name, 1, ' . strlen($prefix) . ') = :prefix' . $i;
            $parameters['prefix' . $i] = $prefix;
        }

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, name, value FROM client_order_meta WHERE client_order_id = :order_id AND (' . implode(' OR ', $conditions) . ') ORDER BY id',
            $parameters,
        );
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
