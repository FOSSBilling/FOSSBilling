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

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\Doctrine\RowLock;

class ClientBalanceRepository extends EntityRepository
{
    public function getClientBalanceSum(int $clientId): float
    {
        $result = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT SUM(amount) FROM client_balance WHERE client_id = :client_id',
            ['client_id' => $clientId],
        );

        return (float) ($result ?? 0);
    }

    /**
     * Must be called within a transaction, held until the deduction has been written.
     */
    public function getClientBalanceSumForUpdate(int $clientId): float
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \FOSSBilling\Exception('Client balance cannot be locked outside of a transaction.');
        }

        // The balance sums insert-only rows, so a concurrent deduction inserts rather than updates
        // and there is nothing for the sum to serialize against. Lock the client row as the mutex.
        $connection->fetchOne(
            'SELECT id FROM client WHERE id = :client_id' . RowLock::suffix($connection),
            ['client_id' => $clientId],
        );

        // A locking read, because a plain one is served from the transaction snapshot, which under
        // REPEATABLE READ can predate the deduction we just waited on above.
        // PostgreSQL rejects locking clauses on aggregate queries outright, so the SUM goes
        // without FOR UPDATE there. That loses nothing: under PostgreSQL's default READ COMMITTED
        // isolation every statement sees a fresh snapshot, so once the client-row mutex above is
        // held, this read already reflects everything committed before it. The locking read only
        // matters where the transaction snapshot can predate the mutex wait (MySQL/MariaDB
        // REPEATABLE READ).
        $platform = $connection->getDatabasePlatform();
        $sumLock = ($platform instanceof SQLitePlatform || $platform instanceof PostgreSQLPlatform) ? '' : ' FOR UPDATE';

        $result = $connection->fetchOne(
            'SELECT SUM(amount) FROM client_balance WHERE client_id = :client_id' . $sumLock,
            ['client_id' => $clientId],
        );

        return (float) ($result ?? 0);
    }
}
