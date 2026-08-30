<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Doctrine\ORM\EntityRepository;

class PayGatewayCustomerRepository extends EntityRepository
{
    public function findOneByGatewayAndClient(int $gatewayId, int $clientId): ?PayGatewayCustomer
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.payGatewayId = :gateway_id')
            ->andWhere('c.clientId = :client_id')
            ->setParameter('gateway_id', $gatewayId)
            ->setParameter('client_id', $clientId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
