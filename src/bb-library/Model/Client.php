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


class Model_Client extends RedBean_SimpleModel
{
    const ACTIVE                    = 'active';
    const SUSPENDED                 = 'suspended';
    const CANCELED                  = 'canceled';

    /**
     * Override default setter
     * @param string $value
     * @return bool
     * @deprecated
     */
    public function setPass($value)
    {
        $this->mapValue('tmp_pass', $value);
        return $this->_set('pass', sha1($value));
    }

    /**
     * @return bool
     * @deprecated
     */
    public function getTempPass()
    {
        if($this->hasMappedValue('tmp_pass')) {
            return $this->_values['tmp_pass'];
        }
        return false;
    }

    public function getFullName()
    {
        return $this->first_name .' '.$this->last_name;
    }
}