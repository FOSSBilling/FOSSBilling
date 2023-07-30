<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_Client extends \RedBeanPHP\SimpleModel
{
    public const ACTIVE                    = 'active';
    public const SUSPENDED                 = 'suspended';
    public const CANCELED                  = 'canceled';

    public function getFullName()
    {
        return $this->first_name .' '.$this->last_name;
    }
}
