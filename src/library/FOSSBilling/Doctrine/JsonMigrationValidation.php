<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

/** Preflight checks for stored data before switching to standard Doctrine JSON fields. */
final class JsonMigrationValidation
{
    /** Reject values that cannot safely populate the structured entity fields. */
    public static function assertCompatible(string $json): void
    {
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)
            !== json_encode(json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)) {
            throw new \JsonException('JSON integers exceed the supported PHP integer range.');
        }
        if ($value !== null && !is_array($value) && !$value instanceof \stdClass) {
            throw new \JsonException('JSON document must contain an object, array or null.');
        }
        self::assertObjectShapes($value);
        // Validation above handles syntax. Track object keys without interpreting their values.
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[{}\\[\\]:,]|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/s', $json, $tokens);
        $stack = [];
        foreach ($tokens[0] as $token) {
            if ($token[0] === '-' || ctype_digit($token[0])) {
                $roundTrip = json_encode(json_decode($token, false, 512, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
                if (self::numberValue($token) !== self::numberValue($roundTrip)) {
                    throw new \JsonException('JSON number cannot round-trip without losing precision.');
                }
            } elseif ($token === '{' || $token === '[') {
                $stack[] = ['object' => $token === '{', 'key' => $token === '{', 'keys' => []];
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif ($stack !== []) {
                $last = array_key_last($stack);
                if ($token === ',') {
                    $stack[$last]['key'] = $stack[$last]['object'];
                } elseif ($token === ':') {
                    $stack[$last]['key'] = false;
                } elseif ($token[0] === '"' && $stack[$last]['key']) {
                    $key = 'key:' . json_decode($token, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($stack[$last]['keys'][$key])) {
                        throw new \JsonException('Duplicate JSON object keys require review.');
                    }
                    $stack[$last]['keys'][$key] = true;
                    $stack[$last]['key'] = false;
                }
            }
        }
    }

    private static function assertObjectShapes(mixed $value): void
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            $keys = array_keys($properties);
            sort($keys);
            if ($keys !== [] && $keys === range(0, count($keys) - 1)) {
                throw new \JsonException('Nonempty numeric-key JSON objects would become lists; review their intended shape.');
            }
            foreach ($properties as $child) {
                self::assertObjectShapes($child);
            }
        } elseif (is_array($value)) {
            foreach ($value as $child) {
                self::assertObjectShapes($child);
            }
        }
    }

    /** Compare decimal meanings without rounding through floating point. */
    private static function numberValue(string $number): string
    {
        $parts = preg_split('/[eE]/', $number);
        $mantissa = $parts[0];
        $negative = str_starts_with($mantissa, '-');
        $mantissa = ltrim($mantissa, '-');
        $dot = strpos($mantissa, '.');
        $scale = $dot === false ? 0 : strlen($mantissa) - $dot - 1;
        $digits = ltrim(str_replace('.', '', $mantissa), '0');
        if ($digits === '') {
            return '0';
        }
        $trimmed = rtrim($digits, '0');
        $exponent = (int) ($parts[1] ?? 0) - $scale + strlen($digits) - strlen($trimmed);

        return ($negative ? '-' : '') . $trimmed . 'e' . $exponent;
    }
}
