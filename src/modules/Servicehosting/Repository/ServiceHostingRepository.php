<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Repository;

use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Doctrine\ORM\EntityRepository;

class ServiceHostingRepository extends EntityRepository
{
    /**
     * @return ServiceHosting[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    public function findOneByServerId(int $serverId): ?ServiceHosting
    {
        $result = $this->findOneBy(['serviceHostingServerId' => $serverId]);

        return $result instanceof ServiceHosting ? $result : null;
    }

    public function findOneByHpId(int $hpId): ?ServiceHosting
    {
        $result = $this->findOneBy(['serviceHostingHpId' => $hpId]);

        return $result instanceof ServiceHosting ? $result : null;
    }
}
