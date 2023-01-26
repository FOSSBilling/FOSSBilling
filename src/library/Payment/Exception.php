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

class Payment_Exception extends Box_Exception
{
	/**
	 * Creates a new translated exception, using the Box_Exception class.
	 *
	 * @param   string   error message
	 * @param   array|null    translation variables
	 * @param   int 	 The exception code.
	 * @param 	bool 	 If the varibles in this should be considered protect, if so, disable stacktracing abilities.
	 */
    public function __construct(string $message, array $variables = NULL, int $code = 0)
    {
        parent:: __construct($message, $variables, $code, true);
    }
}
