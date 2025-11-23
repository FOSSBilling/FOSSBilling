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
     * Cache for default currency to avoid repeated database queries.
     * Reset per request lifecycle (static property).
     *
     * @var Currency|null
     */
    private static ?Currency $defaultCurrencyCache = null;

    /**
     * Build a QueryBuilder for searching currencies.
     *
     * @param array $data Array of filters
     * @return QueryBuilder
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
     * Find a currency by its code
     *
     * @param string $code
     * @return Currency|null
     */
    public function findOneByCode(string $code): ?Currency
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Get the default currency with in-memory caching.
     * Cache is automatically invalidated when default currency changes.
     *
     * @return Currency|null Returns null if no default currency is found
     */
    public function findDefault(): ?Currency
    {
        // Return cached value if available
        if (self::$defaultCurrencyCache !== null) {
            return self::$defaultCurrencyCache;
        }

        // Query database
        $currency = $this->findOneBy(['isDefault' => true]);

        if ($currency === null) {
            // Log warning - no currency marked as default
            error_log('Warning: No default currency found. Please configure a default currency in the system settings.');
            
            // Try fallback to currency with ID 1 (legacy behavior)
            $currency = $this->find(1);
            
            if ($currency === null) {
                // No default currency and ID 1 doesn't exist - system misconfiguration
                error_log('Critical: No default currency found and currency ID 1 does not exist. System requires at least one currency configured as default.');
            }
        }

        // Cache the result (even if null)
        self::$defaultCurrencyCache = $currency;

        return $currency;
    }

    /**
     * Invalidate the default currency cache.
     * Should be called whenever the default currency changes.
     *
     * @return void
     */
    public function invalidateDefaultCache(): void
    {
        self::$defaultCurrencyCache = null;
    }

    /**
     * Get all currency code/title pairs
     *
     * @return array
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
        } catch (\Doctrine\ORM\NoResultException) {
            return null;
        }
    }

    /**
     * Set all currencies to non-default
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
