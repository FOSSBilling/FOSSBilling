<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Pimple\Container;

class Crypt implements InjectionAwareInterface
{
    protected ?Container $di = null;

    final public const string METHOD = 'aes-256-gcm';
    final public const string LEGACY_METHOD = 'aes-256-cbc';
    final public const string CURRENT_FORMAT_PREFIX = 'v3:';

    private array $derivedKeys = [];

    public function __construct()
    {
        if (!extension_loaded('openssl')) {
            throw new Exception('The PHP OpenSSL extension must be enabled on your server');
        }
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function encrypt(string $text, ?string $pass = null): string
    {
        $key = $this->authenticatedKey($pass);
        $header = self::CURRENT_FORMAT_PREFIX;
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($text, self::METHOD, $key, OPENSSL_RAW_DATA, $nonce, $tag, $header, 16);
        if ($ciphertext === false) {
            throw new \RuntimeException('Unable to encrypt data.');
        }

        return $header . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(?string $text, ?string $pass = null): string|false
    {
        if ($text === null) {
            return false;
        }

        if (str_starts_with($text, self::CURRENT_FORMAT_PREFIX)) {
            $raw = base64_decode(substr($text, strlen(self::CURRENT_FORMAT_PREFIX)), true);
            if ($raw === false || strlen($raw) < 28) {
                return false;
            }

            return openssl_decrypt(substr($raw, 28), self::METHOD, $this->authenticatedKey($pass), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), self::CURRENT_FORMAT_PREFIX);
        }
        if (str_starts_with($text, 'v2:')) {
            return $this->decryptWithKey(substr($text, 3), $this->getCurrentKey($pass));
        }

        foreach ([$this->getCurrentKey($pass), $this->getLegacyKey($pass)] as $key) {
            $result = $this->decryptWithKey($text, $key);
            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function decryptWithKey(string $text, string $key): string|false
    {
        $decoded = base64_decode($text, true);
        if ($decoded === false) {
            return false;
        }

        $ivSize = openssl_cipher_iv_length(self::LEGACY_METHOD);
        if (strlen($decoded) <= $ivSize) {
            return false;
        }

        $iv = substr($decoded, 0, $ivSize);
        $ciphertext = substr($decoded, $ivSize);

        $result = openssl_decrypt(
            $ciphertext,
            self::LEGACY_METHOD,
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
        $passphrase = $this->resolvePassphrase($pass);
        $id = hash('sha256', $passphrase);
        if (!isset($this->derivedKeys[$id])) {
            // Bound the per-instance cache; never cache plaintext passphrases or decrypted values.
            if (count($this->derivedKeys) >= 8) {
                $this->derivedKeys = [];
            }
            $this->derivedKeys[$id] = hash_pbkdf2('sha256', $passphrase, 'fossbilling_salt', 100000, 32, true);
        }

        return $this->derivedKeys[$id];
    }

    private function authenticatedKey(?string $pass): string
    {
        return hash_hkdf('sha256', $this->getCurrentKey($pass), 32, 'fossbilling:crypt:v3');
    }

    private function getLegacyKey(?string $pass = null): string
    {
        return pack('H*', hash('md5', $this->resolvePassphrase($pass)));
    }

    private function resolvePassphrase(?string $pass = null): string
    {
        $pass ??= Config::getProperty('info.salt');

        return (string) $pass;
    }

    private function isPlausiblePlaintext(string $text): bool
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) !== 1;
    }
}
