<?php

/**
 * Copyright 2022-2025 FOSSBilling
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

    final public const string METHOD = 'aes-256-cbc';
    final public const string CURRENT_FORMAT_PREFIX = 'v2:';

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

    public function encrypt(string $text, ?string $pass = null): string
    {
        return self::CURRENT_FORMAT_PREFIX . $this->encryptWithKey($text, $this->getCurrentKey($pass));
    }

    public function decrypt(?string $text, ?string $pass = null)
    {
        if (is_null($text)) {
            return false;
        }

        if (str_starts_with($text, self::CURRENT_FORMAT_PREFIX)) {
            return $this->decryptWithKey(substr($text, strlen(self::CURRENT_FORMAT_PREFIX)), $this->getCurrentKey($pass));
        }

        foreach ([$this->getCurrentKey($pass), $this->getLegacyKey($pass)] as $key) {
            $result = $this->decryptWithKey($text, $key);
            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function encryptWithKey(string $text, string $key): string
    {
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

    private function decryptWithKey(string $text, string $key): string|false
    {
        $decoded = base64_decode($text, true);
        if ($decoded === false) {
            return false;
        }

        $ivsize = openssl_cipher_iv_length(self::METHOD);
        $iv = mb_substr($decoded, 0, $ivsize, '8bit');
        $ciphertext = mb_substr($decoded, $ivsize, null, '8bit');

        $result = openssl_decrypt(
            $ciphertext,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($result === false) {
            return false;
        }

        $result = trim($result);

        if (!$this->isPlausiblePlaintext($result)) {
            return false;
        }

        return $result;
    }

    private function getCurrentKey(?string $pass = null): string
    {
        return hash_pbkdf2('sha256', (string) $this->resolvePassphrase($pass), 'fossbilling_salt', 100000, 32, true);
    }

    private function getLegacyKey(?string $pass = null): string
    {
        return pack('H*', hash('md5', (string) $this->resolvePassphrase($pass)));
    }

    private function resolvePassphrase(?string $pass = null): string
    {
        if ($pass === null) {
            $pass = Config::getProperty('info.salt');
        }

        return (string) $pass;
    }

    private function isPlausiblePlaintext(string $text): bool
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        return !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text);
    }
}
