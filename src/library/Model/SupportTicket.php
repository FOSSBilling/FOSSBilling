<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_SupportTicket extends RedBeanPHP\SimpleModel
{
    final public const string OPENED = 'open';
    final public const string ONHOLD = 'on_hold';
    final public const string CLOSED = 'closed';

    final public const string REL_TYPE_ORDER = 'order';

    final public const string REL_STATUS_PENDING = 'pending';
    final public const string REL_STATUS_COMPLETE = 'complete';

    final public const string REL_TASK_CANCEL = 'cancel';
    final public const string REL_TASK_UPGRADE = 'upgrade';
}
