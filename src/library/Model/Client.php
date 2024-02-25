<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_Client extends RedBeanPHP\SimpleModel
{
    final public const ACTIVE = 'active';
    final public const SUSPENDED = 'suspended';
    final public const CANCELED = 'canceled';

    public function getFullName()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
