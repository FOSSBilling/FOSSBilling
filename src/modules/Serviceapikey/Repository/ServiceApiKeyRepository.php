<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serviceapikey\Repository;

use Box\Mod\Serviceapikey\Entity\ServiceApiKey;
use Doctrine\ORM\EntityRepository;

class ServiceApiKeyRepository extends EntityRepository
{
    public function findByApiKey(string $apiKey): ?ServiceApiKey
    {
        $service = $this->findOneBy(['apiKey' => $apiKey]);

        return $service instanceof ServiceApiKey ? $service : null;
    }
}
