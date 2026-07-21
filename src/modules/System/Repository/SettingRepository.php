<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Repository;

use Box\Mod\System\Entity\Setting;
use Doctrine\ORM\EntityRepository;

class SettingRepository extends EntityRepository
{
    public function findOneByParam(string $param): ?Setting
    {
        $setting = $this->findOneBy(['param' => $param]);

        return $setting instanceof Setting ? $setting : null;
    }
}
