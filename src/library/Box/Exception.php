<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Exception extends FOSSBilling\Exception
{
    /**
     * Creates a new translated exception.
     *
     * @deprecated 0.6.0, you should use FOSSBilling\Exception instead which is a drop in replacement.
     *
     * @param string     $message   error message
     * @param array|null $variables translation variables
     * @param int        $code      the exception code
     * @param bool       $protected if the variables in this should be considered protect, if so, hide them from the stack trace
     */
    public function __construct(string $message, array $variables = null, int $code = 0, bool $protected = false)
    {
        // Pass the message to the parent
        trigger_error('Box_Exception is deprectated and soon to be removed.', E_USER_DEPRECATED);
        parent::__construct($message, $variables, $code, $protected);
    }
}
