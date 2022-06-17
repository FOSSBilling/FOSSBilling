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

class Payment_Exception extends Exception
{
    /**
     * Creates a new translated exception.
     *
     * @param   string   error message
     * @param   array    translation variables
     */
    public function __construct($message, array $variables = NULL, $code = 0)
    {
        // Set the message
        $message = __($message, $variables);

        // Pass the message to the parent
        parent::__construct($message, $code);
    }
}
