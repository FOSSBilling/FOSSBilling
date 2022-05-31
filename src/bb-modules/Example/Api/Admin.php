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
 * Example module Admin API.
 *
 * API can be access only by admins
 */

namespace Box\Mod\Example\Api;

class Admin extends \Api_Abstract
{
    /**
     * Return list of example objects.
     *
     * @return string[]
     */
    public function get_something($data)
    {
        $result = [
            'apple',
            'google',
            'facebook',
        ];

        if (isset($data['microsoft'])) {
            $result[] = 'microsoft';
        }

        return $result;
    }
}
