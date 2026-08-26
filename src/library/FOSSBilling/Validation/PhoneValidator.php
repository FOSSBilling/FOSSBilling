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

final class PhoneValidator
{
    public static function validatePhoneCC(string|int $countryCode): int
    {
        if (!is_numeric($countryCode) || $countryCode <= 0 || $countryCode > 999) {
            throw new InformationException('The provided phone country code does not appear to be valid.');
        }

        return intval($countryCode);
    }

    public static function validatePhoneNumber(string $number): string
    {
        $digitsOnly = preg_replace('/\D+/', '', $number);
        if (strlen((string) $digitsOnly) < 1 || strlen((string) $digitsOnly) > 12) {
            throw new InformationException('The provided phone number does not appear to be valid.');
        }

        if (str_starts_with($number, '+')) {
            throw new InformationException('Please use the separate field for the phone country code.');
        }

        return $number;
    }
}
