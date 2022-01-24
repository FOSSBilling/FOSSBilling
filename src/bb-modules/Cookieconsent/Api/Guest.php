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
 * Cookies consent notification bar.
 *
 * Show cookie consent message to comply with European Cookie Law
 *
 */

namespace Box\Mod\Cookieconsent\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get message which should be shown in notification bar
     *
     * @return boolean
     * @throws \Box_Exception
     */
    public function message()
    {
        return $this->getService()->getMessage();
    }
}