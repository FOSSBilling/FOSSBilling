<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Activity\Repository;

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Doctrine\ORM\EntityRepository;
use FOSSBilling\InformationException;

class ActivityAdminHistoryRepository extends EntityRepository
{
    public function findOneByIdOrFail(int $id): ActivityAdminHistory
    {
        $history = $this->find($id);
        if (!$history instanceof ActivityAdminHistory) {
            throw new InformationException('Event not found');
        }

        return $history;
    }
}
