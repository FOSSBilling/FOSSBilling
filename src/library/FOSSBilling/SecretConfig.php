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

/** Per-field encryption for stored adapter configuration. Only values an adapter marks as secret are encrypted; the rest stays readable JSON. Encrypted values carry {@see \Box_Crypt}'s own format prefix, so legacy plaintext needs no separate envelope to be detected. */
final class SecretConfig
{
    /**
     * Encrypt the listed secret fields. Non-strings and empty values are left alone. A value is treated as already encrypted only when it actually decrypts, so a plaintext value that happens to carry the format prefix is still encrypted, keeping this idempotent.
     *
     * @param array<string, mixed> $config
     * @param string[]             $secretFields
     *
     * @return array<string, mixed>
     */
    public static function encryptSecretFields(array $config, array $secretFields, \Box_Crypt $crypt, ?string $passphrase = null): array
    {
        foreach ($secretFields as $field) {
            $value = $config[$field] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            if (str_starts_with($value, \Box_Crypt::CURRENT_FORMAT_PREFIX) && is_string($crypt->decrypt($value, $passphrase))) {
                continue;
            }

            $config[$field] = $crypt->encrypt($value, $passphrase);
        }

        return $config;
    }

    /**
     * Decrypt any config value carrying ciphertext, pass the rest through. Reads need no field list, so this still works when the adapter is gone; values that fail to decrypt fall back to raw instead of breaking the caller.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function decryptMarkedValues(array $config, \Box_Crypt $crypt, ?string $passphrase = null): array
    {
        foreach ($config as $field => $value) {
            if (!is_string($value) || !str_starts_with($value, \Box_Crypt::CURRENT_FORMAT_PREFIX)) {
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
