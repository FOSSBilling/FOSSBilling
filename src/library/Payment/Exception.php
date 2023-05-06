<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Exception extends Box_Exception
{
	/**
	 * Creates a new translated exception, using the Box_Exception class.
	 *
	 * @param   string   error message
	 * @param   array|null    translation variables
	 * @param   int 	 The exception code.
	 * @param 	bool 	 If the variables in this should be considered protect, if so, disable stacktracing abilities.
	 */
    public function __construct(string $message, array $variables = NULL, int $code = 0)
    {
        parent:: __construct($message, $variables, $code, true);
    }
}
