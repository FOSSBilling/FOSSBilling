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
     * Returns cron job information including when it was last executed and where the cron script is located.
     *
     * @return array
     */
    public function info()
    {
        return $this->getService()->getCronInfo();
    }

    public function run(): bool
    {
        return $this->getService()->runCrons();
    }
}
