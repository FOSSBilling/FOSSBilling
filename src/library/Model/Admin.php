<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_Admin extends \RedBeanPHP\SimpleModel
{
    const ROLE_ADMIN = 'admin';
    const ROLE_STAFF = 'staff';
    const ROLE_CRON = 'cron';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    public function getFullName()
    {
        return $this->name;
    }

    public function getStatus($status = '')
    {
        $statusArray = array(
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE
        );
        if (in_array($status, $statusArray)){
            return strtolower($status);
        }
        return self::STATUS_INACTIVE;
    }
}
