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

class ProductOrderRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRowsByProductId(int $productId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM client_order WHERE product_id = ?',
            [$productId]
        );
    }
}
