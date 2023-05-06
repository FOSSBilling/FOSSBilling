<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Model_Invoice extends \RedBeanPHP\SimpleModel
{
    const STATUS_PAID   = 'paid';
    const STATUS_UNPAID = 'unpaid';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELED = 'canceled';
}
