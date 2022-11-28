<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Box_ExceptionAuth extends Box_Exception
{
    public function __construct($uri = "", $code = 0)
    {
        parent::__construct('You need to login in order to view this :url page', array(':url'=>$uri), $code);
    }
}