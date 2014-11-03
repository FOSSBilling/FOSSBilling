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


class Box_Crypt implements \Box\InjectionAwareInterface
{
    protected $di = NULL;

    function __construct()
    {
        if (!extension_loaded('mcrypt')) {
            throw new Exception('php mcrypt extension must be enabled on your server');
        }
    }

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function encrypt($text, $pass = null)
    {
        $pass = $this->_getSalt($pass);
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $pass, $text, MCRYPT_MODE_ECB, $iv));
    }

    public function decrypt($text, $pass = null)
    {
        $pass = $this->_getSalt($pass);
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $pass, base64_decode($text), MCRYPT_MODE_ECB, $iv));
    }

    private function _getSalt($pass = null)
    {
        if (null == $pass) {
            return $this->di['config']['salt'];
        }
        return $pass;
    }
}