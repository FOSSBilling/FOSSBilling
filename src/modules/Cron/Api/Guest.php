<?php

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
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

class Guest extends \Api_Abstract
{
    /**
     * Runs cron if the guest API cron endpoint is enabled via the module's settings
     */
    public function run(): bool
    {
        $config = $this->getMod()->getConfig();
        $allowGuest = $config['guest_cron'] ?? false;
        if (!$allowGuest) {
            throw new InformationException('You do not have permission to perform this action', [], 403);
        }

        return $this->getService()->runCrons();
    }

    /**
     * Get cron settings.
     */
    public function settings(): array
    {
        return $this->getMod()->getConfig();
    }

    /**
     * Tells if cron is late.
     */
    public function is_late(): bool
    {
        return $this->getService()->isLate();
    }
}
