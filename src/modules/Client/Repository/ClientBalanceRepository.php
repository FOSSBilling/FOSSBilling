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

use Doctrine\ORM\EntityRepository;

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
            'SELECT id FROM client WHERE id = :client_id FOR UPDATE',
            ['client_id' => $clientId],
        );

        // A locking read, because a plain one is served from the transaction snapshot, which under
        // REPEATABLE READ can predate the deduction we just waited on above.
        $result = $connection->fetchOne(
            'SELECT SUM(amount) FROM client_balance WHERE client_id = :client_id FOR UPDATE',
            ['client_id' => $clientId],
        );

        return (float) ($result ?? 0);
    }
}
