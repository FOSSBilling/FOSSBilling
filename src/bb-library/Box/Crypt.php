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

    public function __construct()
    {
        if (!extension_loaded('mcrypt')) {
            throw new Box_Exception('php mcrypt extension must be enabled on your server');
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
        $key = $this->_getSalt($pass);
        $mode = MCRYPT_MODE_CBC;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, $mode);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_DEV_URANDOM);
        $enc =  mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, $mode, $iv);
        return base64_encode($iv . $enc);
    }

    public function decrypt($text, $pass = null)
    {
        if (is_null($text)){
            return false;
        }
        $key = $this->_getSalt($pass);
        $mode = MCRYPT_MODE_CBC;
        $ciphertext_dec = base64_decode($text);

        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, $mode);
        # retrieves the IV, iv_size should be created using mcrypt_get_iv_size()
        $iv = substr($ciphertext_dec, 0, $iv_size);
        # retrieves the cipher text (everything except the $iv_size in the front)
        $ciphertext_dec = substr($ciphertext_dec, $iv_size);

        # may remove 00h valued characters from end of plain text
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $ciphertext_dec, $mode, $iv));
    }

    private function _getSalt($pass = null)
    {
        if (null == $pass) {
            $pass = $this->di['config']['salt'];
        }
        return pack('H*', hash('md5', $pass));
    }
}