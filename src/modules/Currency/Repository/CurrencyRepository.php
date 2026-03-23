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

namespace Box\Mod\Currency\Repository;

use Box\Mod\Currency\Entity\Currency;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class CurrencyRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for searching currencies.
     *
     * @param array $data Array of filters
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        // Apply search filter if provided
        if (!empty($data['search'])) {
            $qb->andWhere('c.code LIKE :search OR c.title LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        $qb->orderBy('c.code', 'ASC');

        return $qb;
    }

    /**
     * Find a currency by its code.
     */
    public function findOneByCode(string $code): ?Currency
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Get the default currency.
     *
     * Returns null if no currency is marked as default. Callers should handle
     * this case appropriately (e.g., by throwing an exception or using a fallback).
     *
     * Note: Doctrine's identity map provides automatic caching within a request.
     * If the default currency is changed via `Service::setAsDefault()`, that method
     * clears the identity map to ensure subsequent calls return fresh data.
     */
    public function findDefault(): ?Currency
    {
        return $this->findOneBy(['isDefault' => true]);
    }

    /**
     * Get all currency code/title pairs.
     */
    public function getPairs(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.code', 'c.title')
            ->orderBy('c.code', 'ASC');

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $result) {
            $pairs[$result['code']] = $result['title'];
        }

        return $pairs;
    }

    /**
     * Get conversion rate by currency code.
     * Returns the rate as a float for calculations, or null if currency not found.
     *
     * @param string $code Currency code
     *
     * @return float|null The conversion rate as a float, or null if not found
     */
    public function getRateByCode(string $code): ?float
    {
        try {
            $rate = $this->createQueryBuilder('c')
                ->select('c.conversionRate')
                ->where('c.code = :code')
                ->setParameter('code', $code)
                ->getQuery()
                ->getSingleScalarResult();

            return $rate !== null ? (float) $rate : null;
        } catch (\Doctrine\ORM\NoResultException|\Doctrine\ORM\NonUniqueResultException) {
            return null;
        }
    }

    /**
     * Set all currencies to non-default.
     *
     * @return int Number of affected rows
     */
    public function clearDefaultFlags(): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isDefault', ':isDefault')
            ->setParameter('isDefault', false)
            ->getQuery()
            ->execute();
    }
}
