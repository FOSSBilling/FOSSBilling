<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ClientRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($data['id'])) {
            $qb->andWhere('(c.id = :id OR c.aid = :id)')
                ->setParameter('id', $data['id']);
        }

        if (!empty($data['name'])) {
            $qb->andWhere('(c.first_name LIKE :name OR c.last_name LIKE :name)')
                ->setParameter('name', '%' . $data['name'] . '%');
        }

        if (!empty($data['email'])) {
            $qb->andWhere('c.email LIKE :email')
                ->setParameter('email', '%' . $data['email'] . '%');
        }

        if (!empty($data['company'])) {
            $qb->andWhere('c.company LIKE :company')
                ->setParameter('company', '%' . $data['company'] . '%');
        }

        if (!empty($data['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $data['status']);
        }

        if (!empty($data['group_id'])) {
            $qb->andWhere('c.client_group_id = :group_id')
                ->setParameter('group_id', $data['group_id']);
        }

        if (!empty($data['created_at'])) {
            $date = date('Y-m-d', strtotime((string) $data['created_at']));
            $start = new \DateTimeImmutable($date);
            $qb->andWhere('c.createdAt >= :created_from AND c.createdAt < :created_to')
                ->setParameter('created_from', $start)
                ->setParameter('created_to', $start->modify('+1 day'));
        }

        if (!empty($data['date_from'])) {
            $qb->andWhere('c.createdAt >= :date_from')
                ->setParameter('date_from', new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime((string) $data['date_from']))));
        }

        if (!empty($data['date_to'])) {
            $qb->andWhere('c.createdAt <= :date_to')
                ->setParameter('date_to', new \DateTimeImmutable(date('Y-m-d H:i:s', strtotime((string) $data['date_to']))));
        }

        if (!empty($data['search'])) {
            if (is_numeric($data['search'])) {
                $qb->andWhere('(c.id = :search_id OR c.aid = :search_id)')
                    ->setParameter('search_id', $data['search']);
            } else {
                $qb->andWhere("(c.company LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search OR CONCAT(CONCAT(c.first_name, ' '), c.last_name) LIKE :search)")
                    ->setParameter('search', '%' . $data['search'] . '%');
            }
        }

        return $qb->orderBy('c.createdAt', 'DESC');
    }

    /**
     * @param list<int> $clientIds
     *
     * @return array<int, array{balance: float, group: ?string}>
     */
    public function getListContext(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT c.id, COALESCE(SUM(cb.amount), 0) AS balance, cg.title AS group_title
             FROM client c
             LEFT JOIN client_balance cb ON cb.client_id = c.id
             LEFT JOIN client_group cg ON cg.id = c.client_group_id
             WHERE c.id IN (:ids)
             GROUP BY c.id, cg.title',
            ['ids' => $clientIds],
            ['ids' => ArrayParameterType::INTEGER],
        );

        $context = [];
        foreach ($rows as $row) {
            $context[(int) $row['id']] = [
                'balance' => (float) $row['balance'],
                'group' => $row['group_title'],
            ];
        }

        return $context;
    }
}
