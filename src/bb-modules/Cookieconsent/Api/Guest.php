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
     * @throws \Box_Exception
     */
    public function message()
    {
        return $this->getService()->getMessage();
    }
}
