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

use FOSSBilling\Exception\BaseException as Exception;

final class BirthdayValidator
{
    public static function isBirthdayValid(mixed $birthday = ''): bool
    {
        if (strlen(trim((string) $birthday)) > 0 && strtotime((string) $birthday) === false) {
            $friendlyName = ucfirst(__trans('Birthdate'));

            throw new Exception(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
        }

        return true;
    }
}
