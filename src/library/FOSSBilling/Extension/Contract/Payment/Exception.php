<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment;

class Exception extends \FOSSBilling\Exception
{
    /**
     * Creates a new translated exception, using the \FOSSBilling\Exception class.
     *
     * @param string         $message   error message
     * @param array|int|null $variables translation variables, or the exception code
     *                                  (third-party adapters have been observed
     *                                  passing the code here, so tolerate it
     *                                  rather than failing and hiding the error)
     * @param int            $code      the exception code
     */
    public function __construct(string $message, array|int|null $variables = null, int $code = 0)
    {
        if (is_int($variables)) {
            $code = $variables;
            $variables = null;
        }

        parent::__construct($message, $variables, $code, true);
    }
}
