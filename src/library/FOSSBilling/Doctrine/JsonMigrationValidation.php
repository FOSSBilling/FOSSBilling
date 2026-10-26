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
    public static function assertCompatible(string $json, bool $scalar = false): void
    {
        $value = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)
            !== json_encode(json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)) {
            throw new \JsonException('JSON integers exceed the supported PHP integer range.');
        }
        if (!$scalar && $value !== null && !is_array($value) && !$value instanceof \stdClass) {
            throw new \JsonException('JSON document must contain an object, array or null.');
        }
        // Validation above handles syntax. Track object keys without interpreting their values.
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[{}\\[\\]:,]/s', $json, $tokens);
        $stack = [];
        foreach ($tokens[0] as $token) {
            if ($token === '{' || $token === '[') {
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
}
