<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_ClientOrder extends \RedBeanPHP\SimpleModel
{
	public const STATUS_PENDING_SETUP = "pending_setup";
	public const STATUS_FAILED_SETUP = "failed_setup";
    public const STATUS_FAILED_RENEW = "failed_renew";
	public const STATUS_ACTIVE = "active";
	public const STATUS_CANCELED = "canceled";
	public const STATUS_SUSPENDED = "suspended";

    public const ACTION_CREATE     = 'create';
    public const ACTION_ACTIVATE   = 'activate';
    public const ACTION_RENEW      = 'renew';
    public const ACTION_SUSPEND    = 'suspend';
    public const ACTION_UNSUSPEND  = 'unsuspend';
    public const ACTION_CANCEL     = 'cancel';
    public const ACTION_UNCANCEL   = 'uncancel';
    public const ACTION_DELETE     = 'delete';
}
