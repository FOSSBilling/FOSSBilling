<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_InvoiceItem extends RedBeanPHP\SimpleModel
{
    final public const string TYPE_DEPOSIT = 'deposit'; // this type of item cannot be charged with credits
    final public const string TYPE_CUSTOM = 'custom';
    final public const string TYPE_ORDER = 'order';

    final public const string TASK_VOID = 'void';
    final public const string TASK_ACTIVATE = 'activate';
    final public const string TASK_RENEW = 'renew';

    final public const string STATUS_PENDING_PAYMENT = 'pending_payment';
    final public const string STATUS_PENDING_SETUP = 'pending_setup';
    final public const string STATUS_EXECUTED = 'executed';
}
