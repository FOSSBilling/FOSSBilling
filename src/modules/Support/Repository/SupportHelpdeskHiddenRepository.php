<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Repository;

use Doctrine\ORM\EntityRepository;

class SupportHelpdeskHiddenRepository extends EntityRepository
{
    /**
     * @return list<int>
     */
    public function findAllIds(): array
    {
        return array_map(
            intval(...),
            $this->createQueryBuilder('h')
                ->select('h.helpdeskId')
                ->orderBy('h.helpdeskId')
                ->getQuery()
                ->getSingleColumnResult()
        );
    }
}
