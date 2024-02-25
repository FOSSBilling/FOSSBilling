<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Model_ProductPayment extends RedBeanPHP\SimpleModel
{
    final public const FREE = 'free';
    final public const ONCE = 'once';
    final public const RECURRENT = 'recurrent';
}
