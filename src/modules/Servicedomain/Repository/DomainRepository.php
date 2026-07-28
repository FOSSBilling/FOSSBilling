<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

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
