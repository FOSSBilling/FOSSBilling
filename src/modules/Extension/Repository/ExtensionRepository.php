<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
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
    /**
     * Build a QueryBuilder for filtering installed extensions.
     *
     * Accepted keys in `$data`:
     *  - `type`   (string)  exact match on `type`
     *  - `status` (string)  exact match on `status`
     *  - `search` (string)  LIKE on `name`
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');

        if (!empty($data['type'])) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $data['type']);
        }

        if (!empty($data['status'])) {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $data['status']);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('e.name LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        $qb->orderBy('e.type', 'ASC')
            ->addOrderBy('e.status', 'DESC')
            ->addOrderBy('e.id', 'ASC');

        return $qb;
    }

    /**
     * Find a single extension by `(type, name)`.
     */
    public function findOneByTypeAndName(string $type, string $name): ?Extension
    {
        return $this->findOneBy(['type' => $type, 'name' => $name]);
    }

    /**
     * @return Extension[]
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }

    /**
     * Return all extensions of a given type that are currently installed.
     *
     * @return Extension[]
     */
    public function findInstalledByType(string $type): array
    {
        return $this->findBy(['type' => $type, 'status' => Extension::STATUS_INSTALLED]);
    }

    /**
     * @return string[]
     */
    public function findInstalledNamesByType(string $type): array
    {
        return array_map(
            static fn (Extension $extension): string => (string) $extension->getName(),
            $this->findInstalledByType($type),
        );
    }

    /**
     * Check whether an extension with the given type and name exists and is
     * currently marked as installed.
     */
    public function existsActiveByTypeAndName(string $type, string $name): bool
    {
        return $this->findOneBy([
            'type' => $type,
            'name' => $name,
            'status' => Extension::STATUS_INSTALLED,
        ]) !== null;
    }
}
