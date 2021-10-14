<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Cron checker
 */

namespace Box\Mod\Cron\Api;

class Guest extends \Api_Abstract
{
    /**
     * Run cron if is late and web based cron is enabled
     *
     * @return bool
     */
    public function check()
    {
        return false;
    }

    /**
     * Get cron settings
     *
     * @return array
     */
    public function settings()
    {
        return $this->getMod()->getConfig();
    }

    /**
     * Tells if cron is late
     *
     * @return bool
     */
    public function is_late()
    {
        return $this->getService()->isLate();
    }
}