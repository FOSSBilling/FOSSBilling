<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_Invoice extends RedBeanPHP\SimpleModel
{
    final public const STATUS_PAID = 'paid';
    final public const STATUS_UNPAID = 'unpaid';
    final public const STATUS_REFUNDED = 'refunded';
    final public const STATUS_CANCELED = 'canceled';
}
