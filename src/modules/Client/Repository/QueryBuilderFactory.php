<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Factory for creating QueryBuilders with proper eager/lazy loading strategies.
 *
 * This enforces the loading policy:
 * - List endpoints: EAGER loading (join fetch associations)
 * - Detail endpoints: LAZY loading (load on-demand)
 */
class QueryBuilderFactory
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Create QueryBuilder for client lists with EAGER loading.
     *
     * Joins client_group to prevent N+1 queries.
     */
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('c', 'g') // EAGER: select client and group
            ->from(Client::class, 'c')
            ->leftJoin('c.clientGroup', 'g'); // EAGER: join fetch group (placeholder - no relationship yet)
    }

    /**
     * Create QueryBuilder for single client detail with LAZY loading.
     *
     * Only selects the client entity - associations loaded on-demand.
     */
    public function createDetailQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('c') // LAZY: only client, associations loaded on-demand
            ->from(Client::class, 'c');
    }

    /**
     * Create QueryBuilder for client search/filtering.
     *
     * Uses EAGER loading since this is for list results.
     */
    public function createSearchQueryBuilder(): QueryBuilder
    {
        // For now, same as list - we'll add the group join once we set up relationships
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c');
    }
}
