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

use Box\Mod\Invoice\Entity\PayGatewayProduct;
use Doctrine\ORM\EntityRepository;

class PayGatewayProductRepository extends EntityRepository
{
    public function findOneByGatewayAndCacheKey(int $gatewayId, string $cacheKey): ?PayGatewayProduct
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.payGatewayId = :gateway_id')
            ->andWhere('p.cacheKey = :cache_key')
            ->setParameter('gateway_id', $gatewayId)
            ->setParameter('cache_key', $cacheKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
