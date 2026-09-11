<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Exception;

use FOSSBilling\Core\System\Config;
use FOSSBilling\Core\System\Environment;

/**
 * The base FOSSBilling exception class. Implements translation and stacktrace logging.
 */
class BaseException extends \Exception
{
    /**
     * Creates a new translated exception.
     *
     * @param string     $message   error message
     * @param array|null $variables translation variables
     * @param int        $code      the exception code
     * @param bool       $protected if the variables in this should be considered protect, if so, hide them from the stack trace
     */
    public function __construct(string $message, ?array $variables = null, int $code = 0, bool $protected = false)
    {
        $logStack = Config::getProperty('debug_and_monitoring.log_stacktrace', true);
        $stackLength = Config::getProperty('debug_and_monitoring.stacktrace_length', 25);

        // Translate the exception
        if (function_exists('__trans')) {
            $message = __trans($message, $variables);
        } elseif (is_array($variables)) {
            $message = strtr($message, $variables);
        }

        if (DEBUG && $logStack && !Environment::isTesting()) {
            // Exceptions can be created before the DI container and its logger
            // exist, so keep this diagnostic on PHP's fallback logger.
            error_log(sprintf("Exception: %s\nStack trace:\n%s", $message, $this->stackTrace($stackLength, $protected)));
        }

        // Pass the message to the parent
        parent::__construct($message, $code);
    }

    /**
     * Big thank you to jhurliman and jambroseclarke on Stack Overflow for this backtrace formatter.
     * We have slightly modified it for our purposes
     * https://stackoverflow.com/a/32365961.
     */
    private function stackTrace(int $length = 25, bool $protected = false): string
    {
        $stack = debug_backtrace($length);
        $output = '';

        $stackLength = count($stack);
        for ($i = 1; $i < $stackLength; ++$i) {
            $entry = $stack[$i];

            $func = $entry['function'] . '(';
            if (isset($entry['args'])) {
                $argsLength = count($entry['args']);
                for ($j = 0; $j < $argsLength; ++$j) {
                    $arg = $entry['args'][$j];
                    if ($protected) {
                        $func .= '***';
                    } elseif (is_string($arg)) {
                        $func .= $arg;
                    }
                    if ($j < $argsLength - 1) {
                        $func .= ', ';
                    }
                }
            }
            $func .= ')';

            $entryFile = 'NO_FILE';
            if (array_key_exists('file', $entry)) {
                $entryFile = str_replace(PATH_ROOT, '', $entry['file']);
            }
            $entryLine = 'NO_LINE';
            if (array_key_exists('line', $entry)) {
                $entryLine = $entry['line'];
            }
            $output .= $entryFile . ':' . $entryLine . ' - ' . $func . PHP_EOL;
        }

        return $output;
    }
}
