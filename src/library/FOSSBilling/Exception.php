<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Exception extends \Exception
{
    public function __construct(string $message, ?array $variables = null, int $code = 0)
    {
        if (function_exists('__trans')) {
            $message = __trans($message, $variables);
        } elseif (is_array($variables)) {
            $message = strtr($message, $variables);
        }

        if (DEBUG && Config::getProperty('debug_and_monitoring.log_stacktrace', true) && !Environment::isTesting()) {
            error_log("Exception: $message");
            error_log('Stack trace:');
            error_log($this->getTraceAsString());
        }

        parent::__construct($message, $code);
    }
}
