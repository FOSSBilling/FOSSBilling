<?php

declare(strict_types=1);

namespace Box\Mod\Servicelicense\Repository;

use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Doctrine\ORM\EntityRepository;

class ServiceLicenseRepository extends EntityRepository
{
    public function findOneByLicenseKey(string $key): ?ServiceLicense
    {
        $result = $this->findOneBy(['licenseKey' => $key]);

        return $result instanceof ServiceLicense ? $result : null;
    }
}
