<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Model_Admin extends \RedBean_SimpleModel
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
}