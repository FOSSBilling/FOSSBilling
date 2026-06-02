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

final class AuthenticationRequiredException extends \RuntimeException
{
    public function __construct(private readonly string $area)
    {
        parent::__construct($area === 'admin' ? 'Admin is not logged in' : 'Client is not logged in');
    }

    public function getArea(): string
    {
        return $this->area;
    }
}
