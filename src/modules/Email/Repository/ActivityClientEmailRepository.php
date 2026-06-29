<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Repository;

use Box\Mod\Email\Entity\ActivityClientEmail;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ActivityClientEmailRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for the per-client email history listing.
     *
     * Accepted keys in `$data`:
     *  - `client_id` (int)     filter to one client
     *  - `search`    (string)  LIKE on subject or sender
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.id', 'DESC');

        if (!empty($data['client_id'])) {
            $qb->andWhere('e.clientId = :client_id')
                ->setParameter('client_id', (int) $data['client_id']);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('(e.subject LIKE :search OR e.sender LIKE :search OR e.recipients LIKE :search)')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        return $qb;
    }

    /**
     * Find a single email owned by the given client.
     */
    public function findOneForClientById(int $clientId, int $id): ?ActivityClientEmail
    {
        return $this->findOneBy(['id' => $id, 'clientId' => $clientId]);
    }

    /**
     * @return ActivityClientEmail[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }
}
