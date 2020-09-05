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


class Model_SupportTicket extends RedBean_SimpleModel
{
    const OPENED = 'open';
    const ONHOLD = 'on_hold';
    const CLOSED = 'closed';

    const REL_TYPE_ORDER   = 'order';

    const REL_STATUS_PENDING        = 'pending';
    const REL_STATUS_COMPLETE       = 'complete';
    
    const REL_TASK_CANCEL   = 'cancel';
    const REL_TASK_UPGRADE  = 'upgrade';
}