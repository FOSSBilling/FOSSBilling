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

use Box\Mod\Invoice\Entity\Tax;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class TaxRepository extends EntityRepository
{
    /**
     * Find a tax rule matching the exact state and country.
     *
     * A null state or country must not match, preserving the legacy
     * RedBeanPHP `state = ? AND country = ?` semantics where binding
     * NULL never satisfies an equality comparison.
     */
    public function findOneByStateAndCountry(?string $state, ?string $country): ?Tax
    {
        if ($state === null || $country === null) {
            return null;
        }

        $tax = $this->findOneBy(['state' => $state, 'country' => $country]);

        return $tax instanceof Tax ? $tax : null;
    }

    /**
     * Find the first tax rule matching the given country, regardless of state.
     */
    public function findOneByCountry(?string $country): ?Tax
    {
        if ($country === null) {
            return null;
        }

        $tax = $this->findOneBy(['country' => $country]);

        return $tax instanceof Tax ? $tax : null;
    }

    /**
     * Find the global tax rule (no state and no country restriction).
     */
    public function findGlobalRate(): ?Tax
    {
        $tax = $this->createQueryBuilder('t')
            ->where('(t.state IS NULL OR t.state = :empty)')
            ->andWhere('(t.country IS NULL OR t.country = :empty)')
            ->setParameter('empty', '')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $tax instanceof Tax ? $tax : null;
    }

    /**
     * Build a QueryBuilder for the tax rule search/listing.
     *
     * @param array $data filter and pagination parameters (unused for now)
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC');
    }
}
