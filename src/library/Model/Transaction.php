<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_Transaction extends \RedBeanPHP\SimpleModel
{
    const STATUS_RECEIVED        = 'received';
    const STATUS_APPROVED        = 'approved';
    const STATUS_PROCESSED       = 'processed';
    const STATUS_ERROR           = 'error';
}
