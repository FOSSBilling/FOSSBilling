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

use FOSSBilling\Container\InjectionAwareInterface;

class Validator implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private readonly DomainValidator $domainValidator;

    public function __construct()
    {
        $this->domainValidator = new DomainValidator();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->domainValidator->setDi($di);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function isSldValid(string $sld): bool
    {
        return $this->domainValidator->isSldValid($sld);
    }

    public function isTldValid(string $tld): bool
    {
        return $this->domainValidator->isTldValid($tld);
    }

    public function passwordsMatch(array $data, string $passwordKey = 'password', string $confirmKey = 'password_confirm'): void
    {
        PasswordValidator::passwordsMatch($data, $passwordKey, $confirmKey);
    }

    public function isPasswordStrong(mixed $pwd): bool
    {
        return PasswordValidator::isPasswordStrong($pwd);
    }

    public function checkRequiredParamsForArray(array $required, array $data, ?array $variables = null, int $code = 0): void
    {
        RequiredValidator::checkRequiredParamsForArray($required, $data, $variables, $code);
    }

    public function isEmailValid(string $email): bool
    {
        return EmailValidator::isEmailValid($email);
    }

    public function isBirthdayValid(mixed $birthday = ''): bool
    {
        return BirthdayValidator::isBirthdayValid($birthday);
    }
}
