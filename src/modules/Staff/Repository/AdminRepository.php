<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff\Repository;

use Box\Mod\Staff\Entity\Admin;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class AdminRepository extends EntityRepository
{
    public function findOneByEmailAndActive(string $email): ?Admin
    {
        $admin = $this->findOneBy(['email' => $email, 'status' => Admin::STATUS_ACTIVE]);

        return $admin instanceof Admin ? $admin : null;
    }

    /**
     * @return array<int, string>
     */
    public function getIdNamePairs(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, name FROM admin ORDER BY name ASC'
        );

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = (string) $row['name'];
        }

        return $pairs;
    }

    public function findOneByApiToken(?string $apiToken): ?Admin
    {
        if ($apiToken === null || $apiToken === '') {
            return null;
        }

        $admin = $this->findOneBy(['apiToken' => $apiToken]);

        return $admin instanceof Admin ? $admin : null;
    }

    public function findCronAdmin(): ?Admin
    {
        $admin = $this->findOneBy(['systemName' => Admin::SYSTEM_CRON]);

        return $admin instanceof Admin ? $admin : null;
    }

    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $status = $data['status'] ?? null;
        $noCron = !isset($data['no_cron']) || $data['no_cron'];

        if ($id !== null && $id !== '') {
            $qb->andWhere('a.id = :id')
                ->setParameter('id', (int) $id);
        }

        if ($search) {
            $qb->andWhere('(a.name LIKE :name OR a.email LIKE :email)')
                ->setParameter('name', "%{$search}%")
                ->setParameter('email', "%{$search}%");
        }

        if ($status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($noCron) {
            $qb->andWhere('(a.systemName IS NULL OR a.systemName != :cronName)')
                ->setParameter('cronName', Admin::SYSTEM_CRON);
        }

        return $qb->orderBy('a.id', 'ASC');
    }
}
