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
    public function findOneByGatewayAndClient(int $gatewayId, int $clientId, bool $testMode = false): ?PayGatewayCustomer
    {
        return $this->createQueryBuilder('c')
            ->andWhere('IDENTITY(c.payGateway) = :gateway_id')
            ->andWhere('c.clientId = :client_id')
            ->andWhere('c.testMode = :test_mode')
            ->setParameter('gateway_id', $gatewayId)
            ->setParameter('client_id', $clientId)
            ->setParameter('test_mode', $testMode)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The client's saved payment method on a gateway: a row whose method was
     * kept for automatic charges with a usable method reference. Null when
     * the client never saved one (or it was removed).
     */
    public function findSaved(int $gatewayId, bool $testMode, int $clientId): ?PayGatewayCustomer
    {
        return $this->createQueryBuilder('c')
            ->andWhere('IDENTITY(c.payGateway) = :gateway_id')
            ->andWhere('c.clientId = :client_id')
            ->andWhere('c.testMode = :test_mode')
            ->andWhere('c.savedCards = :saved')
            ->andWhere('c.defaultPaymentMethod IS NOT NULL')
            ->andWhere("c.defaultPaymentMethod <> ''")
            ->setParameter('gateway_id', $gatewayId)
            ->setParameter('client_id', $clientId)
            ->setParameter('test_mode', $testMode)
            ->setParameter('saved', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
