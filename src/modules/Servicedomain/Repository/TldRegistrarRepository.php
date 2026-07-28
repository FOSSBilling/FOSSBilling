<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain\Repository;

use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Doctrine\ORM\EntityRepository;

class TldRegistrarRepository extends EntityRepository
{
    /**
     * @return array<int, string>
     */
    public function getIdNamePairs(): array
    {
        $result = $this->createQueryBuilder('tr')
            ->select('tr.id, tr.name')
            ->orderBy('tr.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($result as $row) {
            $pairs[(int) $row['id']] = $row['name'];
        }

        return $pairs;
    }

    public function findActiveRegistrar(): ?TldRegistrar
    {
        return $this->createQueryBuilder('tr')
            ->where('tr.config IS NOT NULL')
            ->orderBy('tr.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
