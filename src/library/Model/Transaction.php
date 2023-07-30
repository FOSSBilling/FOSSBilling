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
    public const STATUS_RECEIVED        = 'received';
    public const STATUS_APPROVED        = 'approved';
    public const STATUS_PROCESSED       = 'processed';
    public const STATUS_ERROR           = 'error';
}
