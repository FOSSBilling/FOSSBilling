<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

/**
 * Per-field encryption for stored adapter configuration arrays.
 *
 * Only the values an adapter marks as secret are encrypted; everything else
 * stays readable JSON so stored configuration remains inspectable and
 * debuggable. Encrypted values carry {@see Crypt}'s own format prefix, so no
 * separate envelope is needed and legacy plaintext rows are detected for
 * free: anything without the prefix is passed through untouched.
 */
final class SecretConfig
{
    private function __construct()
    {
    }

    /**
     * Encrypt every listed secret field present in the config array.
     *
     * Values that are not strings, are empty, or already carry ciphertext are
     * left alone, which keeps the operation idempotent and preserves the
     * "is this secret set" semantics callers rely on.
     *
     * @param array<string, mixed> $config
     * @param string[]             $secretFields
     *
     * @return array<string, mixed>
     */
    public static function encryptSecretFields(array $config, array $secretFields, Crypt $crypt, ?string $passphrase = null): array
    {
        foreach ($secretFields as $field) {
            $value = $config[$field] ?? null;
            if (!is_string($value) || $value === '' || str_starts_with($value, Crypt::CURRENT_FORMAT_PREFIX)) {
                continue;
            }

            $config[$field] = $crypt->encrypt($value, $passphrase);
        }

        return $config;
    }

    /**
     * Decrypt any config value carrying ciphertext, pass the rest through.
     *
     * Reads never need the adapter's secret-field list: the format prefix
     * alone identifies ciphertext, so this stays correct even when the
     * adapter that wrote the row is gone or its secret fields changed. A
     * prefixed value that fails to decrypt (a legacy row, or a plaintext
     * value that happens to start with the prefix) falls back to its raw
     * form rather than breaking the caller.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function decryptMarkedValues(array $config, Crypt $crypt, ?string $passphrase = null): array
    {
        foreach ($config as $field => $value) {
            if (!is_string($value) || !str_starts_with($value, Crypt::CURRENT_FORMAT_PREFIX)) {
                continue;
            }

            $decrypted = $crypt->decrypt($value, $passphrase);
            if (is_string($decrypted)) {
                $config[$field] = $decrypted;
            }
        }

        return $config;
    }
}
