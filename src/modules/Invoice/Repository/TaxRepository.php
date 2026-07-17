<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Tax;
use Doctrine\ORM\EntityRepository;

class TaxRepository extends EntityRepository
{
    public function findByCountryAndState(?string $state, ?string $country): ?Tax
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.state = :state')
            ->andWhere('t.country = :country')
            ->setParameter('state', $state)
            ->setParameter('country', $country)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof Tax ? $result : null;
    }

    public function findByCountry(?string $country): ?Tax
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.country = :country')
            ->setParameter('country', $country)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof Tax ? $result : null;
    }

    public function findGlobal(): ?Tax
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.state IS NULL OR t.state = :empty')
            ->andWhere('t.country IS NULL OR t.country = :empty')
            ->setParameter('empty', '')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof Tax ? $result : null;
    }
}
