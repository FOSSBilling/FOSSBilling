<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Model_ProductPayment extends RedBean_SimpleModel
{
    const FREE      = 'free';
    const ONCE      = 'once';
    const RECURRENT = 'recurrent';
}