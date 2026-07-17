<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Repository;

use Box\Mod\Servicedomain\Entity\Tld;
use Doctrine\ORM\EntityRepository;

class TldRepository extends EntityRepository
{
    /**
     * @return Tld[]
     */
    public function findAllActive(): array
    {
        return $this->findBy(['active' => true], ['id' => 'ASC']);
    }

    public function findOneByTld(string $tld): ?Tld
    {
        $result = $this->findOneBy(['tld' => $tld]);

        return $result instanceof Tld ? $result : null;
    }

    public function findOneActiveById(int $id): ?Tld
    {
        $result = $this->findOneBy(['id' => $id, 'active' => true]);

        return $result instanceof Tld ? $result : null;
    }

    /**
     * @return array<int, string>
     */
    public function getIdTldPairs(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.id, t.tld')
            ->where('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($result as $row) {
            $pairs[(int) $row['id']] = $row['tld'];
        }

        return $pairs;
    }
}
