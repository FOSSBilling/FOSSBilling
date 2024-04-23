<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Config;

class Box_Crypt implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    final public const METHOD = 'aes-256-cbc';

    public function __construct()
    {
        if (!extension_loaded('openssl')) {
            throw new FOSSBilling\Exception('The PHP OpenSSL extension must be enabled on your server');
        }
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function encrypt($text, $pass = null)
    {
        $key = $this->_getSalt($pass);

        $ivsize = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($ivsize);

        $ciphertext = openssl_encrypt(
            $text,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv . $ciphertext);
    }

    public function decrypt($text, $pass = null)
    {
        if (is_null($text)) {
            return false;
        }
        $key = $this->_getSalt($pass);

        $text = base64_decode($text);

        $ivsize = openssl_cipher_iv_length(self::METHOD);
        $iv = mb_substr($text, 0, $ivsize, '8bit');
        $ciphertext = mb_substr($text, $ivsize, null, '8bit');

        $result = openssl_decrypt(
            $ciphertext,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return trim($result);
    }

    private function _getSalt($pass = null)
    {
        if ($pass == null) {
            $pass = Config::getProperty('info.salt');
        }

        return pack('H*', hash('md5', $pass));
    }
}
