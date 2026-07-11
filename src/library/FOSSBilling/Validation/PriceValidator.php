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

use FOSSBilling\InformationException;

final class PriceValidator
{
    public static function validateAmount(mixed $value, string $fieldName = 'Price'): float
    {
        $amount = self::validateNumericAmount($value, $fieldName);

        if ($amount < 0) {
            throw new InformationException(':field cannot be negative.', [':field' => $fieldName]);
        }

        return $amount;
    }

    public static function validateSignedAmount(mixed $value, string $fieldName = 'Price'): float
    {
        return self::validateNumericAmount($value, $fieldName);
    }

    public static function validateQuantity(mixed $value): int
    {
        if (!is_numeric($value)) {
            throw new InformationException('Quantity must be a valid number.');
        }

        $quantity = (float) $value;
        if (!is_finite($quantity) || $quantity > PHP_INT_MAX || $quantity < PHP_INT_MIN) {
            throw new InformationException('Quantity must be a valid number.');
        }

        return max(1, (int) $quantity);
    }

    private static function validateNumericAmount(mixed $value, string $fieldName): float
    {
        if (!is_numeric($value)) {
            throw new InformationException(':field must be a valid number.', [':field' => $fieldName]);
        }

        $amount = (float) $value;
        if (!is_finite($amount)) {
            throw new InformationException(':field must be a valid number.', [':field' => $fieldName]);
        }

        return $amount;
    }
}
