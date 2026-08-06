<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Subscription;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class SubscriptionRepository extends EntityRepository
{
    /**
     * Find a subscription by its gateway subscription id (`sid`).
     * Mirrors the legacy `findOne('Subscription', 'sid = :sid', ...)`.
     */
    public function findOneBySid(string $sid): ?Subscription
    {
        $subscription = $this->findOneBy(['sid' => $sid]);

        return $subscription instanceof Subscription ? $subscription : null;
    }

    /**
     * Build a QueryBuilder for subscription searches/listings.
     *
     * @param array $data optional filters: search, id, sid, status, gateway_id,
     *                    client_id, currency, date_from, date_to
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        $status = $data['status'] ?? null;
        if ($status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $gatewayId = $data['gateway_id'] ?? null;
        if ($gatewayId) {
            $qb->andWhere('s.payGatewayId = :gateway_id')->setParameter('gateway_id', (int) $gatewayId);
        }

        $clientId = $data['client_id'] ?? null;
        if ($clientId) {
            $qb->andWhere('s.clientId = :client_id')->setParameter('client_id', (int) $clientId);
        }

        $currency = $data['currency'] ?? null;
        if ($currency) {
            $qb->andWhere('s.currency = :currency')->setParameter('currency', $currency);
        }

        $dateFrom = $data['date_from'] ?? null;
        if ($dateFrom) {
            $timestamp = ctype_digit((string) $dateFrom) ? (int) $dateFrom : strtotime($dateFrom . ' 00:00:00');
            $qb->andWhere('s.createdAt >= :date_from')->setParameter('date_from', date('Y-m-d H:i:s', $timestamp));
        }

        $dateTo = $data['date_to'] ?? null;
        if ($dateTo) {
            $timestamp = ctype_digit((string) $dateTo) ? (int) $dateTo : strtotime($dateTo . ' 23:59:59');
            $qb->andWhere('s.createdAt <= :date_to')->setParameter('date_to', date('Y-m-d H:i:s', $timestamp));
        }

        $search = $data['search'] ?? null;
        if ($search) {
            $qb->andWhere('s.sid = :search OR s.id = :search_id')
                ->setParameter('search', $search)
                ->setParameter('search_id', (int) $search);
        }

        $id = $data['id'] ?? null;
        if ($id) {
            $qb->andWhere('s.id = :id')->setParameter('id', (int) $id);
        }

        $sid = $data['sid'] ?? null;
        if ($sid) {
            $qb->andWhere('s.sid = :sid')->setParameter('sid', $sid);
        }

        $qb->orderBy('s.id', 'DESC');

        return $qb;
    }
}
