<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Repository;

use Box\Mod\Extension\Entity\Extension;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ExtensionRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.status = :status')
            ->setParameter('status', Extension::STATUS_INSTALLED)
            ->orderBy('e.type', 'ASC')
            ->addOrderBy('e.id', 'ASC');

        if (!empty($data['type'])) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $data['type']);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('e.name LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        return $qb;
    }

    public function findOneByTypeAndName(string $type, string $name): ?Extension
    {
        return $this->findOneBy([
            'type' => $type,
            'name' => $name,
        ]);
    }

    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }

    public function findInstalledByType(string $type): array
    {
        return $this->findBy([
            'type' => $type,
            'status' => Extension::STATUS_INSTALLED,
        ]);
    }

    public function findInstalledNamesByType(string $type): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.name')
            ->where('e.type = :type')
            ->andWhere('e.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', Extension::STATUS_INSTALLED)
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }

    public function findInstalledAndCoreNames(array $coreModules): array
    {
        $installedNames = $this->findInstalledNamesByType('mod');

        return array_unique(array_merge($coreModules, $installedNames));
    }

    public function hasInstalledExtension(string $type, string $name): bool
    {
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.type = :type')
            ->andWhere('e.name = :name')
            ->andWhere('e.status = :status')
            ->setParameter('type', $type)
            ->setParameter('name', $name)
            ->setParameter('status', Extension::STATUS_INSTALLED)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
