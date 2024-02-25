<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Cookies consent notification bar.
 *
 * Show cookie consent message to comply with European Cookie Law
 */

namespace Box\Mod\Cookieconsent\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get message which should be shown in notification bar.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function message()
    {
        return $this->getService()->getMessage();
    }
}
