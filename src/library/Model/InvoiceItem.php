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
    const TYPE_DEPOSIT  = 'deposit'; // this type of item can not be charged with credits
    const TYPE_CUSTOM   = 'custom';
    const TYPE_ORDER    = 'order';
    const TYPE_HOOK_CALL= 'hook_call';

    const TASK_VOID     = 'void';
    const TASK_ACTIVATE = 'activate';
    const TASK_RENEW    = 'renew';

    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PENDING_SETUP = 'pending_setup';
    const STATUS_EXECUTED = 'executed';
}
