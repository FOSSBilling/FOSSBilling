<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_InvoiceItem extends \RedBeanPHP\SimpleModel
{
    public const TYPE_DEPOSIT  = 'deposit'; // this type of item can not be charged with credits
    public const TYPE_CUSTOM   = 'custom';
    public const TYPE_ORDER    = 'order';
    public const TYPE_HOOK_CALL= 'hook_call';

    public const TASK_VOID     = 'void';
    public const TASK_ACTIVATE = 'activate';
    public const TASK_RENEW    = 'renew';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PENDING_SETUP = 'pending_setup';
    public const STATUS_EXECUTED = 'executed';
}
