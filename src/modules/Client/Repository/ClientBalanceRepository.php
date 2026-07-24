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
}
