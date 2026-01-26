<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_Client extends RedBeanPHP\SimpleModel
{
    final public const string ACTIVE = 'active';
    final public const string SUSPENDED = 'suspended';
    final public const string CANCELED = 'canceled';
    final public const string GENDER_MALE = 'male';
    final public const string GENDER_FEMALE = 'female';
    final public const string GENDER_NON_BINARY = 'nonbinary';
    final public const string GENDER_OTHER = 'other';
    public const ALLOWED_GENDERS = [
        self::GENDER_MALE,
        self::GENDER_FEMALE,
        self::GENDER_NON_BINARY,
        self::GENDER_OTHER,
    ];
    final public const string DOC_PASSPORT = 'passport';
    public const ALLOWED_DOCUMENT_TYPES = [
        self::DOC_PASSPORT,
    ];

    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
