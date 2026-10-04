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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientPasswordReset;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\Doctrine\RowLock;
use FOSSBilling\InformationException;

class ClientPasswordResetRepository extends EntityRepository
{
    /** Replace a password and revoke all recovery credentials in the same transaction. */
    public function changePassword(Client $client, string $passwordHash, ?string $resetHash = null): void
    {
        $em = $this->getEntityManager();
        $em->wrapInTransaction(function () use ($em, $client, $passwordHash, $resetHash): void {
            $connection = $em->getConnection();
            $clientId = (int) $client->getId();
            $status = $this->lockClient($clientId);

            if ($resetHash !== null) {
                // Re-read after acquiring the client mutex: a previously loaded entity may
                // already have been revoked by another password change or reset confirmation.
                $reset = $connection->fetchAssociative(
                    'SELECT created_at FROM client_password_reset WHERE client_id = :id AND hash = :hash' . RowLock::suffix($connection),
                    ['id' => $clientId, 'hash' => $resetHash],
                );
                if ($status !== Client::ACTIVE || $reset === false || strtotime((string) $reset['created_at']) + 900 < time()) {
                    throw new InformationException('The link has expired or you have already reset your password.');
                }
            }

            $connection->delete('client_password_reset', ['client_id' => $clientId]);
            $client->setPass($passwordHash);
            $em->persist($client);
        });
    }

    /** Issue a new credential without racing password changes or other reset requests. */
    public function createRequest(Client $client, string $hash, ?string $ip): void
    {
        $em = $this->getEntityManager();
        $em->wrapInTransaction(function () use ($em, $client, $hash, $ip): void {
            $clientId = (int) $client->getId();
            $this->lockClient($clientId);
            $em->getConnection()->delete('client_password_reset', ['client_id' => $clientId]);

            $reset = new ClientPasswordReset();
            $reset->setClient($client)->setHash($hash)->setIp($ip);
            $em->persist($reset);
        });
    }

    /** Must be held within the transaction until the credential mutation commits. */
    private function lockClient(int $clientId): mixed
    {
        $connection = $this->getEntityManager()->getConnection();
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            // A deferred SQLite transaction needs a write before it can serialize readers.
            $connection->executeStatement('UPDATE client SET updated_at = updated_at WHERE id = :id', ['id' => $clientId]);
        }

        return $connection->fetchOne('SELECT status FROM client WHERE id = :id' . RowLock::suffix($connection), ['id' => $clientId]);
    }

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
