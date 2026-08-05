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

use Box\Mod\Invoice\Entity\PayGateway;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PayGatewayRepository extends EntityRepository
{
    /**
     * @return PayGateway[]
     */
    public function findEnabledOrderedByIdDesc(): array
    {
        return $this->findBy(['enabled' => true], ['id' => 'DESC']);
    }

    /**
     * Find a single enabled gateway by its adapter code (the `gateway` column).
     */
    public function findEnabledByGateway(string $gateway): ?PayGateway
    {
        $result = $this->findOneBy(['gateway' => $gateway, 'enabled' => true]);

        return $result instanceof PayGateway ? $result : null;
    }

    /**
     * Build a QueryBuilder for the gateway search/listing.
     *
     * @param array $data optional filters: search, enabled, allow_single,
     *                    allow_recurrent, test_mode
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('pg');

        $search = $data['search'] ?? null;
        if ($search) {
            $qb->andWhere('(pg.name LIKE :search OR pg.gateway LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $enabled = $data['enabled'] ?? null;
        if ($enabled !== null && $enabled !== '') {
            $qb->andWhere('pg.enabled = :enabled')
                ->setParameter('enabled', (bool) $enabled);
        }

        $allowSingle = $data['allow_single'] ?? null;
        if ($allowSingle !== null && $allowSingle !== '') {
            $qb->andWhere('pg.allowSingle = :allow_single')
                ->setParameter('allow_single', (bool) $allowSingle);
        }

        $allowRecurrent = $data['allow_recurrent'] ?? null;
        if ($allowRecurrent !== null && $allowRecurrent !== '') {
            $qb->andWhere('pg.allowRecurrent = :allow_recurrent')
                ->setParameter('allow_recurrent', (bool) $allowRecurrent);
        }

        $testMode = $data['test_mode'] ?? null;
        if ($testMode !== null && $testMode !== '') {
            $qb->andWhere('pg.testMode = :test_mode')
                ->setParameter('test_mode', (bool) $testMode);
        }

        $qb->orderBy('pg.gateway', 'ASC');

        return $qb;
    }
}
