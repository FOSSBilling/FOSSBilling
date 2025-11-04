<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\ClientPasswordReset;
use Doctrine\ORM\EntityRepository;

/**
 * Repository for ClientPasswordReset entity.
 *
 * @extends EntityRepository<ClientPasswordReset>
 */
class ClientPasswordResetRepository extends EntityRepository
{
    /**
     * Find password reset by hash.
     *
     * @param string $hash Reset hash
     */
    public function findOneByHash(string $hash): ?ClientPasswordReset
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    /**
     * Find password reset by client ID.
     *
     * @param int $clientId Client ID
     */
    public function findOneByClientId(int $clientId): ?ClientPasswordReset
    {
        return $this->findOneBy(['client_id' => $clientId]);
    }

    /**
     * Delete expired password reset requests.
     *
     * @param \DateTime $before Delete requests created before this date
     *
     * @return int Number of deleted records
     */
    public function deleteExpired(\DateTime $before): int
    {
        $qb = $this->createQueryBuilder('cpr')
            ->delete()
            ->where('cpr.created_at < :before')
            ->setParameter('before', $before);

        return $qb->getQuery()->execute();
    }
}
