<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\ClientPasswordReset;
use Doctrine\ORM\EntityRepository;

class ClientPasswordResetRepository extends EntityRepository
{
    /**
     * @return list<ClientPasswordReset>
     */
    public function findExpiredBefore(\DateTimeInterface $cutoff): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    public function findOneByHash(string $hash): ?ClientPasswordReset
    {
        $reset = $this->findOneBy(['hash' => $hash]);

        return $reset instanceof ClientPasswordReset ? $reset : null;
    }
}
