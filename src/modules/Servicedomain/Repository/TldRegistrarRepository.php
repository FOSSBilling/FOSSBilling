<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Repository;

use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Doctrine\ORM\EntityRepository;

class TldRegistrarRepository extends EntityRepository
{
    /**
     * @return array<int, string>
     */
    public function getIdNamePairs(): array
    {
        $result = $this->createQueryBuilder('tr')
            ->select('tr.id, tr.name')
            ->orderBy('tr.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($result as $row) {
            $pairs[(int) $row['id']] = $row['name'];
        }

        return $pairs;
    }

    public function findActiveRegistrar(): ?TldRegistrar
    {
        $result = $this->findOneBy([], ['id' => 'ASC']);

        return $result instanceof TldRegistrar && $result->getConfig() !== null ? $result : null;
    }
}
