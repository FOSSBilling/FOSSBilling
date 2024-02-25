<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ClientOrder extends RedBeanPHP\SimpleModel
{
    final public const STATUS_PENDING_SETUP = 'pending_setup';
    final public const STATUS_FAILED_SETUP = 'failed_setup';
    final public const STATUS_FAILED_RENEW = 'failed_renew';
    final public const STATUS_ACTIVE = 'active';
    final public const STATUS_CANCELED = 'canceled';
    final public const STATUS_SUSPENDED = 'suspended';

    final public const ACTION_CREATE = 'create';
    final public const ACTION_ACTIVATE = 'activate';
    final public const ACTION_RENEW = 'renew';
    final public const ACTION_SUSPEND = 'suspend';
    final public const ACTION_UNSUSPEND = 'unsuspend';
    final public const ACTION_CANCEL = 'cancel';
    final public const ACTION_UNCANCEL = 'uncancel';
    final public const ACTION_DELETE = 'delete';
}
