<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Example module Admin API
 * 
 * API can be access only by admins
 */

namespace Box\Mod\Example\Api;

class Admin extends \Api_Abstract
{
    /**
     * Return list of example objects
     * 
     * @return string[]
     */
    public function get_something($data)
    {
        $result = array(
            'apple',
            'google',
            'facebook',
        );

        if(isset($data['microsoft'])) {
            $result[] = 'microsoft';
        }
        
        return $result;
    }
}