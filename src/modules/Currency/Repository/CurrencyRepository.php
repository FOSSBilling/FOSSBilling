<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Currency\Repository;

use Box\Mod\Currency\Entity\Currency;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\SortOptions;
use Symfony\Component\Intl\Currencies;

class CurrencyRepository extends EntityRepository
{
    private readonly Connection $connection;
    private bool $defaultCurrencyLoaded = false;
    private ?Currency $defaultCurrency = null;
    /** @var array<string, Currency|null> */
    private array $currenciesByCode = [];
    private bool $invalidateAfterFlush = false;

    /** @param ClassMetadata<Currency> $class */
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->connection = $em->getConnection();
        $em->getEventManager()->addEventListener(['onClear', 'onFlush', 'postFlush'], $this);
    }

    public function getClientCurrencyCode(int $clientId): ?string
    {
        $currencyCode = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT currency FROM client WHERE id = :client_id',
            ['client_id' => $clientId],
        );

        return is_string($currencyCode) && $currencyCode !== '' ? $currencyCode : null;
    }

    /**
     * Build a QueryBuilder for searching currencies.
     *
     * @param array $data Array of filters
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($data['search'])) {
            $qb->andWhere('c.code LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        $sort = SortOptions::fromArray($data, [
            'code' => 'c.code',
            'conversion_rate' => 'c.conversionRate',
            'id' => 'c.id',
            'created_at' => 'c.createdAt',
            'updated_at' => 'c.updatedAt',
        ]);
        if ($sort->isSorted()) {
            $qb->orderBy($sort->expression, $sort->direction);
            if ($sort->expression !== 'c.id') {
                $qb->addOrderBy('c.id', $sort->direction);
            }
        } else {
            $qb->orderBy('c.code', 'ASC');
        }

        return $qb;
    }

    /**
     * Find a currency by its code.
     */
    public function findOneByCode(string $code): ?Currency
    {
        if ($this->connection->isTransactionActive()) {
            $this->clearLookupCache();

            return $this->findOneBy(['code' => $code]);
        }

        if (!array_key_exists($code, $this->currenciesByCode)) {
            $this->currenciesByCode[$code] = $this->findOneBy(['code' => $code]);
        }

        return $this->currenciesByCode[$code];
    }

    public function onClear(OnClearEventArgs $event): void
    {
        if ($event->getObjectManager() === $this->getEntityManager()) {
            $this->clearLookupCache();
            $this->invalidateAfterFlush = false;
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if ($event->getObjectManager() !== $this->getEntityManager()) {
            return;
        }

        $unitOfWork = $this->getEntityManager()->getUnitOfWork();

        foreach ([
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        ] as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof Currency) {
                    $this->clearLookupCache();
                    $this->invalidateAfterFlush = true;

                    break 2;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($event->getObjectManager() === $this->getEntityManager() && $this->invalidateAfterFlush) {
            $this->clearLookupCache();
            $this->invalidateAfterFlush = false;
        }
    }

    private function clearLookupCache(): void
    {
        $this->defaultCurrency = null;
        $this->defaultCurrencyLoaded = false;
        $this->currenciesByCode = [];
    }

    /**
     * Get the default currency.
     *
     * Returns null if no currency is marked as default. Callers should handle
     * this case appropriately (e.g., by throwing an exception or using a fallback).
     *
     * Reused across module services sharing this EntityManager.
     */
    public function findDefault(): ?Currency
    {
        if ($this->connection->isTransactionActive()) {
            $this->clearLookupCache();

            return $this->findOneBy(['isDefault' => true]);
        }

        if (!$this->defaultCurrencyLoaded) {
            $this->defaultCurrency = $this->findOneBy(['isDefault' => true]);
            $this->defaultCurrencyLoaded = true;

            if ($this->defaultCurrency instanceof Currency) {
                $this->currenciesByCode[$this->defaultCurrency->getCode()] = $this->defaultCurrency;
            }
        }

        return $this->defaultCurrency;
    }

    public function getPairs(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.code')
            ->orderBy('c.code', 'ASC');

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $result) {
            $code = $result['code'];
            $pairs[$code] = Currencies::getName($code);
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
        $this->clearLookupCache();

        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isDefault', ':isDefault')
            ->setParameter('isDefault', false)
            ->getQuery()
            ->execute();
    }
}
