<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense\Api;

/**
 * Licensing server.
 */
class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Check license details callback. Request IP is detected automatically
     * You can pass any other parameters to be validated by license plugin.
     *
     * @return array - bool
     */
    public function check($data)
    {
        return $this->getService()->checkLicenseDetails($data);
    }
}
