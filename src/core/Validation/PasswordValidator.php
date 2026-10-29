<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Validation;

use FOSSBilling\Core\Exception\InformationException;

final class PasswordValidator
{
    public static function isPasswordStrong(mixed $pwd): bool
    {
        if (!is_string($pwd) || $pwd === '') {
            throw new InformationException('Password is required.');
        }

        if (strlen($pwd) < 8) {
            throw new InformationException('Minimum password length is 8 characters.');
        }

        if (strlen($pwd) > 256) {
            throw new InformationException('Maximum password length is 256 characters.');
        }

        if (!preg_match('#[0-9]+#', $pwd)) {
            throw new InformationException('Password must include at least one number.');
        }

        if (!preg_match('#[a-z]+#', $pwd)) {
            throw new InformationException('Password must include at least one lowercase letter.');
        }

        if (!preg_match('#[A-Z]+#', $pwd)) {
            throw new InformationException('Password must include at least one uppercase letter.');
        }

        return true;
    }

    public static function passwordsMatch(array $data, string $passwordKey = 'password', string $confirmKey = 'password_confirm'): void
    {
        if (($data[$passwordKey] ?? '') !== ($data[$confirmKey] ?? '')) {
            throw new InformationException('Passwords do not match.');
        }
    }
}
