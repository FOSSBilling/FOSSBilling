<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\PayGateway;
use Doctrine\ORM\EntityRepository;

class PayGatewayRepository extends EntityRepository
{
    /**
     * @return PayGateway[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true]);
    }

    /**
     * @return PayGateway[]
     */
    public function findEnabledOrderedByIdDesc(): array
    {
        return $this->findBy(['enabled' => true], ['id' => 'DESC']);
    }
}
