<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product\Repository;

use Box\Mod\Product\Entity\PromoRedemption;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\Doctrine\RowLock;

class PromoRedemptionRepository extends EntityRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findClientSummary(int $clientId): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT id, first_name, last_name, email FROM client WHERE id = :id',
            ['id' => $clientId],
        );

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrderSummary(int $orderId): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT id, title, created_at FROM client_order WHERE id = :id',
            ['id' => $orderId],
        );

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findInvoiceSummary(int $invoiceId): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT id, serie, nr, status, created_at FROM invoice WHERE id = :id',
            ['id' => $invoiceId],
        );

        return $row !== false ? $row : null;
    }

    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('pr');

        if (array_key_exists('promo_id', $data)) {
            $qb->andWhere('IDENTITY(pr.promo) = :promoId')
                ->setParameter('promoId', $data['promo_id']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('pr.clientId = :clientId')
                ->setParameter('clientId', $data['client_id']);
        }

        if (!empty($data['client_order_id'])) {
            $qb->andWhere('pr.clientOrderId = :clientOrderId')
                ->setParameter('clientOrderId', $data['client_order_id']);
        }

        if (!empty($data['phase'])) {
            $qb->andWhere('pr.phase = :phase')
                ->setParameter('phase', $data['phase']);
        }

        if (!empty($data['status'])) {
            $qb->andWhere('pr.status = :status')
                ->setParameter('status', $data['status']);
        }

        $qb->orderBy('pr.id', 'DESC');

        return $qb;
    }

    public function clientHasActiveCheckoutApplication(int $promoId, int $clientId): bool
    {
        $count = (int) $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->where('IDENTITY(pr.promo) = :promoId')
            ->andWhere('pr.clientId = :clientId')
            ->andWhere('pr.phase = :phase')
            ->andWhere('pr.status IN (:statuses)')
            ->setParameter('promoId', $promoId)
            ->setParameter('clientId', $clientId)
            ->setParameter('phase', PromoRedemption::PHASE_CHECKOUT)
            ->setParameter('statuses', [PromoRedemption::STATUS_RESERVED, PromoRedemption::STATUS_COMMITTED])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Locking variant of clientHasActiveCheckoutApplication(), for use inside the checkout
     * transaction. Redemption rows are insert-only, so a plain COUNT cannot serialize concurrent
     * checkouts against each other: lock the client row as the mutex instead, then re-read.
     *
     * Must be called within a transaction, held until the checkout's redemption rows are written.
     */
    public function clientHasActiveCheckoutApplicationForUpdate(int $promoId, int $clientId): bool
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \FOSSBilling\Exception('Promo redemption cannot be locked outside of a transaction.');
        }

        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // SQLite has no SELECT ... FOR UPDATE, and a deferred transaction takes no lock at
            // all until the first write. Two concurrent checkouts could otherwise both pass the
            // read below under a shared lock before either takes a write lock. This no-op UPDATE
            // changes no values but forces lock escalation immediately, so only one checkout can
            // be mid-check at a time; the loser fails outright on the busy connection and rolls
            // back instead of double-redeeming.
            $connection->executeStatement(
                'UPDATE client SET updated_at = updated_at WHERE id = :client_id',
                ['client_id' => $clientId]
            );
        }

        // The mutex: every checkout for this client collides here, checkouts for other clients
        // do not.
        $connection->fetchOne(
            'SELECT id FROM client WHERE id = :client_id' . RowLock::suffix($connection),
            ['client_id' => $clientId]
        );

        // A locking read, because a plain one can be served from the transaction snapshot, which
        // under REPEATABLE READ can predate the checkout we just waited on above.
        $count = (int) $connection->fetchOne(
            'SELECT COUNT(pr.id) FROM promo_redemption pr'
            . ' WHERE pr.promo_id = :promo_id AND pr.client_id = :client_id'
            . ' AND pr.phase = :phase AND pr.status IN (:statuses)' . RowLock::suffix($connection),
            [
                'promo_id' => $promoId,
                'client_id' => $clientId,
                'phase' => PromoRedemption::PHASE_CHECKOUT,
                'statuses' => [PromoRedemption::STATUS_RESERVED, PromoRedemption::STATUS_COMMITTED],
            ],
            ['statuses' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        return $count > 0;
    }

    public function countByPromoId(int $promoId): int
    {
        return (int) $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->where('IDENTITY(pr.promo) = :promoId')
            ->setParameter('promoId', $promoId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{
     *     recorded_applications: int,
     *     checkout_applications: int,
     *     renewal_applications: int,
     *     active_checkout_applications: int,
     *     reserved_applications: int,
     *     committed_applications: int,
     *     released_applications: int,
     *     distinct_clients: int,
     *     orders_using_promo: int
     * }
     */
    public function getUsageStatsByPromoId(int $promoId): array
    {
        $stats = $this->createQueryBuilder('pr')
            ->select('COUNT(pr.id) AS recorded_applications')
            ->addSelect('SUM(CASE WHEN pr.phase = :checkoutPhase THEN 1 ELSE 0 END) AS checkout_applications')
            ->addSelect('SUM(CASE WHEN pr.phase = :renewalPhase THEN 1 ELSE 0 END) AS renewal_applications')
            ->addSelect('SUM(CASE WHEN pr.phase = :checkoutPhase AND pr.status IN (:activeStatuses) THEN 1 ELSE 0 END) AS active_checkout_applications')
            ->addSelect('SUM(CASE WHEN pr.status = :reservedStatus THEN 1 ELSE 0 END) AS reserved_applications')
            ->addSelect('SUM(CASE WHEN pr.status = :committedStatus THEN 1 ELSE 0 END) AS committed_applications')
            ->addSelect('SUM(CASE WHEN pr.status = :releasedStatus THEN 1 ELSE 0 END) AS released_applications')
            ->addSelect('COUNT(DISTINCT pr.clientId) AS distinct_clients')
            ->addSelect('COUNT(DISTINCT pr.clientOrderId) AS orders_using_promo')
            ->where('IDENTITY(pr.promo) = :promoId')
            ->setParameter('promoId', $promoId)
            ->setParameter('checkoutPhase', PromoRedemption::PHASE_CHECKOUT)
            ->setParameter('renewalPhase', PromoRedemption::PHASE_RENEWAL)
            ->setParameter('activeStatuses', [PromoRedemption::STATUS_RESERVED, PromoRedemption::STATUS_COMMITTED])
            ->setParameter('reservedStatus', PromoRedemption::STATUS_RESERVED)
            ->setParameter('committedStatus', PromoRedemption::STATUS_COMMITTED)
            ->setParameter('releasedStatus', PromoRedemption::STATUS_RELEASED)
            ->getQuery()
            ->getSingleResult();

        return [
            'recorded_applications' => (int) ($stats['recorded_applications'] ?? 0),
            'checkout_applications' => (int) ($stats['checkout_applications'] ?? 0),
            'renewal_applications' => (int) ($stats['renewal_applications'] ?? 0),
            'active_checkout_applications' => (int) ($stats['active_checkout_applications'] ?? 0),
            'reserved_applications' => (int) ($stats['reserved_applications'] ?? 0),
            'committed_applications' => (int) ($stats['committed_applications'] ?? 0),
            'released_applications' => (int) ($stats['released_applications'] ?? 0),
            'distinct_clients' => (int) ($stats['distinct_clients'] ?? 0),
            'orders_using_promo' => (int) ($stats['orders_using_promo'] ?? 0),
        ];
    }
}
