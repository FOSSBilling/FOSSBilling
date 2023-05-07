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
	const STATUS_PENDING_SETUP = "pending_setup";
	const STATUS_FAILED_SETUP = "failed_setup";
    const STATUS_FAILED_RENEW = "failed_renew";
	const STATUS_ACTIVE = "active";
	const STATUS_CANCELED = "canceled";
	const STATUS_SUSPENDED = "suspended";

    const ACTION_CREATE     = 'create';
    const ACTION_ACTIVATE   = 'activate';
    const ACTION_RENEW      = 'renew';
    const ACTION_SUSPEND    = 'suspend';
    const ACTION_UNSUSPEND  = 'unsuspend';
    const ACTION_CANCEL     = 'cancel';
    const ACTION_UNCANCEL   = 'uncancel';
    const ACTION_DELETE     = 'delete';
}
