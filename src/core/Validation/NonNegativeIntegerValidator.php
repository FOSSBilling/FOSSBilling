<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Validation;

use FOSSBilling\Exception\InformationException;

final class NonNegativeIntegerValidator
{
    public static function validate(mixed $value, string $message): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^(0|[1-9]\d*)$/D', $value) === 1) {
            $normalized = (int) $value;
            if ((string) $normalized === $value) {
                return $normalized;
            }
        }

        throw new InformationException($message);
    }
}
