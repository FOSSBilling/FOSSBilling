<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('cron', 'view');

        return $this->getService()->getCronInfo();
    }

    /**
     * Run cron.
     *
     * @return bool
     */
    public function run($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('cron', 'manage');

        return $this->getService()->runCrons();
    }
}
