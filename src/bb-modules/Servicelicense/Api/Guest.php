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

namespace Box\Mod\Servicelicense\Api;
/**
 * Licensing server
 */
class Guest extends \Api_Abstract
{
    /**
     * Check license details callback. Request IP is detected automatically
     * You can pass any other parameters to be validated by license plugin.
     *
     * @param string $license - license key
     * @param string $host - hostname where license is installed
     * @param string $version - software version
     * @param string $path - software install path
     * 
     * @optional string $legacy - deprecated parameter. Returns result in non consistent API result
     * 
     * @return array - bool
     */
    public function check($data)
    {
        return $this->getService()->checkLicenseDetails($data);
    }
}