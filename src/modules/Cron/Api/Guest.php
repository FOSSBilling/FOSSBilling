<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Cron checker.
 */

namespace Box\Mod\Cron\Api;

use FOSSBilling\InformationException;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Runs cron if the guest API cron endpoint is enabled via the module's settings.
     */
    public function run(array $data = []): bool
    {
        $config = $this->getMod()->getConfig();
        $allowGuest = $config['guest_cron'] ?? false;
        if (!$allowGuest) {
            throw new InformationException('You do not have permission to perform this action', [], 403);
        }

        $cronHash = $config['cron_hash'] ?? '';
        if ($cronHash === '') {
            throw new InformationException('Guest cron is enabled but no security hash has been configured', [], 403);
        }

        $providedHash = $data['hash'] ?? '';
        if (!is_string($providedHash) || !hash_equals($cronHash, $providedHash)) {
            throw new InformationException('You do not have permission to perform this action', [], 403);
        }

        if (!is_null($this->getService()->getLastExecutionTime())) {
            $t1 = new \DateTime($this->getService()->getLastExecutionTime());
            $t2 = new \DateTime('-1min');

            if ($t1 >= $t2) {
                return false;
            }
        }

        return $this->getService()->runCrons();
    }
}
