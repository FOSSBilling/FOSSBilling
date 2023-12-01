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
     *
     * @return bool `true` if crons were run, `false` if they aren't yet late
     */
    public function run()
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
     *
     * @return array
     */
    public function settings()
    {
        return $this->getMod()->getConfig();
    }

    /**
     * Tells if cron is late.
     *
     * @return bool
     */
    public function is_late()
    {
        return $this->getService()->isLate();
    }
}
