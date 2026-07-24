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

class ClientGroupRepository extends EntityRepository
{
    /**
     * @return array<int, string>
     */
    public function getIdTitlePairs(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, title FROM client_group ORDER BY id ASC'
        );

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = (string) $row['title'];
        }

        return $pairs;
    }
}
