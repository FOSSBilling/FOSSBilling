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

use Egulias\EmailValidator\EmailValidator as EguliasValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\RFCValidation;
use FOSSBilling\Exception\InformationException;
use FOSSBilling\System\Environment;

final class EmailValidator
{
    public static function isEmailValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateAndSanitizeEmail(string $email, bool $throw = true, bool $checkDNS = true): mixed
    {
        $email = trim($email);

        $validator = new EguliasValidator();
        if (Environment::isProduction() && $checkDNS) {
            $validations = new MultipleValidationWithAnd([
                new RFCValidation(),
                new DNSCheckValidation(),
            ]);
        } else {
            $validations = new RFCValidation();
        }

        if (!$validator->isValid($email, $validations)) {
            if ($throw) {
                $friendlyName = ucfirst(__trans('Email address'));

                throw new InformationException(':friendlyName: is invalid', [':friendlyName:' => $friendlyName]);
            }

            return false;
        }

        return $email;
    }
}
