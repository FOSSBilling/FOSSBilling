<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_SupportTicket extends \RedBeanPHP\SimpleModel
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
