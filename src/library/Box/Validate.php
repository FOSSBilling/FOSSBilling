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


class Box_Validate
{

    protected $di = null;

    /**
     * @param Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }


    public function isSldValid($sld)
    {
        // allow punnycode
        if(substr($sld, 0, 4) == 'xn--') {
            return true;
        }

        if(preg_match('/^[a-z0-9]+[a-z0-9\-]*[a-z0-9]+$/i', $sld) && strlen($sld) < 64 && substr($sld, 2, 2) != '--') {
            return true;
        } else {
            return false;
        }
    }

    public function isEmailValid($email, $throw = true)
    {
        $valid = (preg_match("/[-a-zA-Z0-9_.+]+@[a-zA-Z0-9-]{2,}\.[a-zA-Z]{2,}/", $email) > 0) ? true : false;
        if(!$valid && $throw) {
            throw new \Box_Exception('Email is not valid');
        }
        return $valid;
    }
    
    public function isPasswordStrong($pwd)
    {
        if( strlen($pwd) < 7 ) {
            throw new \Box_Exception("Password too short!");
        }

        if( !preg_match("#[0-9]+#", $pwd) ) {
            throw new \Box_Exception("Password must include at least one number!");
        }

        if( !preg_match("#[a-z]+#", $pwd) ) {
            throw new \Box_Exception("Password must include at least one letter!");
        }

        /*
        if( !preg_match("#[A-Z]+#", $pwd) ) {
            throw new \Box_Exception("Password must include at least one CAPS!");
        }

        if( !preg_match("#\W+#", $pwd) ) {
            throw new \Box_Exception("Password must include at least one symbol!");
        }
        */
        return true;
    }

    /**
     * @param array $required - Array with required keys and messages to show if the key is not found
     * @param array $data - Array to search for keys
     * @param array $variables - Array of variables for message placeholders (:placeholder)
     * @param integer $code - Exception code
     * @throws Box_Exception
     */
    public function checkRequiredParamsForArray(array $required, array $data, array $variables = NULL, $code = 0)
    {
        foreach ($required as $key => $msg) {

            if(!isset($data[$key])){
                throw new \Box_Exception($msg, $variables, $code);
            }

            if (is_string($data[$key]) && strlen(trim($data[$key])) === 0){
                throw new \Box_Exception($msg, $variables, $code);
            }

            if (!is_numeric($data[$key]) && empty($data[$key])){
                throw new \Box_Exception($msg, $variables, $code);
            }
        }
    }

    public function isBirthdayValid($birthday = '')
    {
        if (strlen(trim($birthday)) > 0 && strtotime($birthday) === false) {
            throw new \Box_Exception('Invalid birth date value');
        }
        return true;
    }
}
