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

namespace Box;

interface InjectionAwareInterface
{
    /**
     * @param \Box_Di $di
     * @return void
     */
    public function setDi($di);

    /**
     * @return \Box_Di
     */
    public function getDi ();
}