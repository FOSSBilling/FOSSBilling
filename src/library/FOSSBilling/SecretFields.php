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

/** Encrypt declared credentials without hiding ordinary JSON settings from Doctrine. */
final readonly class SecretFields
{
    private const string MARKER = '_fossbilling_encrypted';

    public function __construct(private Crypt $crypt)
    {
    }

    public function names(array $config): array
    {
        return array_keys(array_filter($config, static fn ($value): bool => is_array($value) && array_key_exists(self::MARKER, $value)));
    }

    public function store(array $config, array $fields): array
    {
        foreach (array_unique([...$fields, ...$this->names($config)]) as $field) {
            $value = $config[$field] ?? null;
            if (is_array($value) && isset($value[self::MARKER])) {
                if (!is_string($value[self::MARKER]) || !str_starts_with($value[self::MARKER], Crypt::CURRENT_FORMAT_PREFIX)) {
                    throw new InformationException('Stored credential uses an invalid encryption envelope.');
                }
                if (!$this->crypt->needsReEncryption($value[self::MARKER])) {
                    $this->reveal([$field => $value]);

                    continue;
                }
                $value = $this->reveal([$field => $value])[$field];
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (!is_string($value)) {
                throw new InformationException('Credential fields must contain strings.');
            }
            $config[$field] = [self::MARKER => $this->crypt->encrypt($value)];
        }

        return $config;
    }

    public function reveal(array $config): array
    {
        foreach ($this->names($config) as $field) {
            $envelope = $config[$field];
            if (count($envelope) !== 1 || !is_string($envelope[self::MARKER]) || !str_starts_with($envelope[self::MARKER], Crypt::CURRENT_FORMAT_PREFIX)) {
                throw new InformationException('Stored credential could not be decrypted.');
            }
            $value = $this->crypt->decrypt($envelope[self::MARKER]);
            if ($value === false) {
                throw new InformationException('Stored credential could not be decrypted.');
            }
            $config[$field] = $value;
        }

        return $config;
    }
}
