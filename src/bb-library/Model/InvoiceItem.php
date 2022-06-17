<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Model_InvoiceItem extends RedBean_SimpleModel
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