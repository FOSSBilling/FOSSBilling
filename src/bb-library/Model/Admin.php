<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
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