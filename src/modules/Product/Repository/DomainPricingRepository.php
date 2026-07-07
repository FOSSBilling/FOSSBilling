<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product\Repository;

use Doctrine\DBAL\Connection;

class DomainPricingRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<mixed, array<'active'|'allow_register'|'allow_transfer'|'min_years'|'price_registration'|'price_renew'|'price_transfer'|'registrar'|'tld', mixed>>
     */
    public function getActivePricingByTld(): array
    {
        $pricing = [];

        $query = $this->connection->createQueryBuilder();
        $query
            ->select('t.*', 'r.name')
            ->from('tld', 't')
            ->leftJoin('t', 'tld_registrar', 'r', 'r.id = t.tld_registrar_id')
            ->where('t.active = 1')
            ->orderBy('t.id', 'ASC');

        $results = $query->executeQuery()->fetchAllAssociative();
        foreach ($results as $tld) {
            $pricing[$tld['tld']] = [
                'tld' => $tld['tld'],
                'price_registration' => $tld['price_registration'],
                'price_renew' => $tld['price_renew'],
                'price_transfer' => $tld['price_transfer'],
                'active' => $tld['active'],
                'allow_register' => $tld['allow_register'],
                'allow_transfer' => $tld['allow_transfer'],
                'min_years' => $tld['min_years'],
                'registrar' => [
                    'id' => $tld['tld_registrar_id'],
                    'title' => $tld['name'],
                ],
            ];
        }

        return $pricing;
    }
}
