<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

final class EmailValidationRequiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Please check your mailbox and confirm your email address.');
    }
}
