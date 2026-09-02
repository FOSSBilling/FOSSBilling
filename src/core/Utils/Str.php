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

final class Str
{
    public static function slug(string $str): string
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9-]/', '-', $str);
        $str = preg_replace('/-+/', '-', (string) $str);

        return trim((string) $str, '-');
    }

    public static function toCamelCase(string $str, bool $capitalizeFirstChar = false): ?string
    {
        if ($capitalizeFirstChar) {
            $str[0] = strtoupper($str[0]);
        }
        $func = fn ($c): string => strtoupper($c[1]);

        return preg_replace_callback('/-([a-z])/', $func, $str);
    }

    public static function fromCamelCase(string $str): ?string
    {
        $str[0] = strtolower($str[0]);
        $func = fn ($c): string => '-' . strtolower($c[1]);

        return preg_replace_callback('/([A-Z])/', $func, $str);
    }
}
