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
     * Get the default currency
     *
     * @return Currency|null
     */
    public function findDefault(): ?Currency
    {
        $currency = $this->findOneBy(['isDefault' => true]);

        if ($currency === null) {
            $currency = $this->find(1);
        }

        return $currency;
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
     * Get conversion rate by currency code
     *
     * @param string $code
     * @return string|null
     */
    public function getRateByCode(string $code): ?string
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.conversionRate')
            ->where('c.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        $rate = $result['conversionRate'] ?? null;
        
        return is_numeric($rate) ? $rate : 1;
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
