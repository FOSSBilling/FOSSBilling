<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Repository;

use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Doctrine\ORM\EntityRepository;

class DomainRepository extends EntityRepository
{
    /**
     * @return ServiceDomain[]
     */
    public function findByTldRegistrarId(int $tldRegistrarId): array
    {
        return $this->findBy(['tldRegistrarId' => $tldRegistrarId]);
    }

    /**
     * @return ServiceDomain[]
     */
    public function findByTld(string $tld): array
    {
        return $this->findBy(['tld' => $tld]);
    }
}
