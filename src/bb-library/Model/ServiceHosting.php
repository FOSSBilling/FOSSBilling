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


class Model_ServiceHosting extends RedBean_SimpleModel
{
    private $_tmp_pass;

    public function setPass($value)
    {
        $this->setTmpPass($value);
        $this->_set('pass', sha1($value));
    }

    private function setTmpPass($value)
    {
        $this->_tmp_pass = $value;
        return $this;
    }

    public function getTmpPass()
    {
        if($this->_tmp_pass) {
            return $this->_tmp_pass;
        }
        return '********';
    }

}