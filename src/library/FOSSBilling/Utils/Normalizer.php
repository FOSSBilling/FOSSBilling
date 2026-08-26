<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Utils;

final class Normalizer
{
    public static function normalizeBoolean(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $normalized ?? $default;
        }

        return $default;
    }

    public static function normalizePort(mixed $value, ?int $default = null): ?int
    {
        if (!is_int($value) && !is_string($value)) {
            return $default;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $port = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);

        return $port === false ? $default : $port;
    }
}
