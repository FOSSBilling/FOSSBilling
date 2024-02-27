<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_InvoiceItem extends RedBeanPHP\SimpleModel
{
    final public const TYPE_DEPOSIT = 'deposit'; // this type of item cannot be charged with credits
    final public const TYPE_CUSTOM = 'custom';
    final public const TYPE_ORDER = 'order';
    final public const TYPE_HOOK_CALL = 'hook_call';

    final public const TASK_VOID = 'void';
    final public const TASK_ACTIVATE = 'activate';
    final public const TASK_RENEW = 'renew';

    final public const STATUS_PENDING_PAYMENT = 'pending_payment';
    final public const STATUS_PENDING_SETUP = 'pending_setup';
    final public const STATUS_EXECUTED = 'executed';
}
