<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense\Repository;

use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Doctrine\ORM\EntityRepository;

class ServiceLicenseRepository extends EntityRepository
{
    public function findByLicenseKey(string $licenseKey): ?ServiceLicense
    {
        $license = $this->findOneBy(['licenseKey' => $licenseKey]);

        return $license instanceof ServiceLicense ? $license : null;
    }
}
