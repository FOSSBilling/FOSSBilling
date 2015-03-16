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
