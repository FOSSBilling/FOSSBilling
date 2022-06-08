<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Cron management.
 */

namespace Box\Mod\Cron\Api;

class Admin extends \Api_Abstract
{
    /**
     * Returns cron job information. When it was last executed, where cron job
     * file is located.
     *
     * @return array
     */
    public function info($data)
    {
        return $this->getService()->getCronInfo();
    }

    /**
     * Run cron.
     *
     * @return bool
     */
    public function run($data)
    {
        return $this->getService()->runCrons();
    }
}
