<?php

declare(strict_types=1);

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
