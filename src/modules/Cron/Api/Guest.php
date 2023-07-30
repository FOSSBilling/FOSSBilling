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

class Guest extends \Api_Abstract
{
    /**
     * Run cron if is late and web based cron is enabled.
     *
     * @return bool
     */
    public function check()
    {
        return false;
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
