<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff\Repository;

use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminPasswordReset;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\Doctrine\RowLock;
use FOSSBilling\InformationException;

class AdminPasswordResetRepository extends EntityRepository
{
    public function findOneByHash(string $hash): ?AdminPasswordReset
    {
        $reset = $this->findOneBy(['hash' => $hash]);

        return $reset instanceof AdminPasswordReset ? $reset : null;
    }

    public function changePassword(Admin $admin, string $passwordHash, ?string $resetHash = null): void
    {
        $em = $this->getEntityManager();
        $em->wrapInTransaction(function () use ($em, $admin, $passwordHash, $resetHash): void {
            $current = $this->lockAdmin($admin);
            if ($current === null) {
                throw new InformationException('Admin not found');
            }
            if ($resetHash !== null) {
                $connection = $em->getConnection();
                // Read the capability again under the identity lock, never from the identity map.
                $createdAt = $connection->fetchOne(
                    'SELECT created_at FROM admin_password_reset WHERE admin_id = ? AND hash = ?' . RowLock::suffix($connection),
                    [$admin->getId(), $resetHash]
                );
                $created = is_string($createdAt) ? strtotime($createdAt) : false;
                if ($created === false || $created + 900 < time()
                    || $current['status'] !== Admin::STATUS_ACTIVE || $current['system_name'] === Admin::SYSTEM_CRON) {
                    throw new InformationException('The link has expired or you have already confirmed the password reset.');
                }
            }

            $this->deleteResetsForAdmin((int) $admin->getId());
            $admin->setPass($passwordHash);
            $em->persist($admin);
        });
    }

    public function replaceReset(Admin $admin, string $hash, ?string $ip): bool
    {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($em, $admin, $hash, $ip): bool {
            $current = $this->lockAdmin($admin);
            if ($current === null || $current['status'] !== Admin::STATUS_ACTIVE || $current['system_name'] === Admin::SYSTEM_CRON) {
                return false;
            }

            $this->deleteResetsForAdmin((int) $admin->getId());
            $reset = (new AdminPasswordReset())->setAdmin($admin)->setHash($hash)->setIp($ip);
            $em->persist($reset);

            return true;
        });
    }

    /**
     * @return array{status: mixed, system_name: mixed}|null
     */
    private function lockAdmin(Admin $admin): ?array
    {
        $connection = $this->getEntityManager()->getConnection();
        // A deferred SQLite transaction needs a write before it can safely read credentials.
        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $connection->executeStatement('UPDATE admin SET id = id WHERE id = ?', [$admin->getId()]);
        }
        $current = $connection->fetchAssociative(
            'SELECT status, system_name FROM admin WHERE id = ?' . RowLock::suffix($connection),
            [$admin->getId()]
        );
        if ($current === false) {
            return null;
        }

        return ['status' => $current['status'], 'system_name' => $current['system_name']];
    }

    public function deleteResetsForAdmin(int $adminId): int
    {
        return (int) $this->getEntityManager()->getConnection()->delete('admin_password_reset', ['admin_id' => $adminId]);
    }
}
