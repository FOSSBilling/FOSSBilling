<?php

declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use \Sentry\Event;
use \Sentry\EventHint;
use \Sentry\State\Scope;
use \Sentry\Severity;

class SentryHelper
{
    public static function registerSentry(array $config): void
    {
        $sentryDSN = '--replace--this--during--release--process--';

        // Registers Sentry for error reporting if enabled.
        $options = [
            'before_send' => function (Event $event, ?EventHint $hint): ?Event {
                if ($hint) {
                    $errorInfo = ErrorPage::getCodeInfo($hint->exception->getCode());

                    if (!$errorInfo['report']) {
                        return null;
                    }

                    if ($hint->exception instanceof \FOSSBilling\InformationException) {
                        return null;
                    }
                }

                return $event;
            },

            'environment' => Environment::getCurrentEnvironment(),
            'release' => Version::VERSION,

            // This option is disabled by default, but we set it to false here to be explicit & ensure it can never change unexpectedly.
            'send_default_pii' => false,

            // Stack traces are always sent for Exceptions, but if debug mode is enabled we will send them for errors too.
            'attach_stacktrace' => (bool)BB_DEBUG,
        ];

        /**
         * Here we validate that the DSN is correctly set and that error reporting is enabled before passing it off to the Sentry SDK.
         * It may look a bit odd, but the DSN placeholder value here is split into two strings and concatenated so we can easily perform a `sed` replacement of the placeholder without it effecting this check
         */
        if ($config['debug_and_monitoring']['report_errors'] && $sentryDSN !== '--replace--this--' . 'during--release--process--' && !empty($sentryDSN)) {
            // Per Sentry documentation, not setting this results in the SDK simply not sending any information.
            $options['dsn'] = $sentryDSN;
        }

        $options['server_name'] = Instance::getInstanceID();
        \Sentry\init($options);
    }

    /**
     * Captures an exception and sends it to Sentry, adding additional information that we'd find useful.
     * 
     * @param \Exception $e
     */
    public static function captureException(\Exception $e)
    {
        \Sentry\withScope(function (Scope $scope) use ($e): void {
            $errorInfo = ErrorPage::getCodeInfo($e->getCode());
            $exceptionPath = $e->getFile();

            // Tag the event with the exception's category.
            $scope->setTag('exception.category', $errorInfo['category']);

            // If we can, tag the event with the module or library that threw the exception.
            if (str_starts_with($exceptionPath, PATH_MODS)) {
                $strippedPath = str_replace(PATH_MODS, '', $exceptionPath);
                $level = 0;
                $module = 'Unknown';

                while ($level <= 10) {
                    if (dirname($strippedPath, ($level + 1)) === DIRECTORY_SEPARATOR) {
                        $module = trim(dirname($strippedPath, $level), DIRECTORY_SEPARATOR);
                        break;
                    }
                    $level++;
                }
                $scope->setTag('module.name', $module);
                error_log($module);
            } else if (str_starts_with($exceptionPath, PATH_LIBRARY)) {
                $scope->setTag('library.class', pathinfo($exceptionPath, PATHINFO_FILENAME));
            }

            // Finally tag the event with what is probably the webserver in use, then send the event to Sentry.
            self::estimateWebServer($scope);
            \Sentry\captureException($e);
        });
    }

    /**
     * Accepts a PHP error's number and returns what type of error it is.
     */
    public static function getErrorType(int $number): string
    {
        switch ($number) {
            case E_ERROR:
                return 'Error';
                break;
            case E_WARNING:
                return 'Warning';
                break;
            case E_PARSE:
                return 'Parse error';
                break;
            case E_NOTICE:
                return 'Runtime notice';
                break;
            case E_CORE_ERROR:
                return 'Fatal PHP startup error';
                break;
            case E_CORE_WARNING:
                return 'PHP startup warning';
                break;
            case E_COMPILE_ERROR:
                return 'Zend compile error';
                break;
            case E_COMPILE_WARNING:
                return 'Zend compile warning';
                break;
            case E_USER_ERROR:
                return 'User-generated error';
                break;
            case E_USER_WARNING:
                return 'User-generated warning';
                break;
            case E_USER_NOTICE:
                return 'User-generated notice';
                break;
            case E_STRICT:
                return 'PHP Strict code checking';
                break;
            case E_RECOVERABLE_ERROR:
                return 'Recoverable error';
                break;
            case E_DEPRECATED:
                return 'PHP deprecation warning';
                break;
            case E_USER_DEPRECATED:
                return 'User-generated deprecation warning';
                break;
            default:
                return 'Unknown error';
                break;
        }
    }

    /**
     * Returns the appropriate Sentry severity level for a given error type.
     */
    public static function getSeverityLevel(string $type): Severity
    {
        if (stripos($type, 'fatal') !== false) {
            return Severity::fatal();
        }
        // We check for deprecation before warning because the message for them also includes 'warning'
        if (stripos($type, 'deprecation') !== false) {
            return Severity::info();
        }
        if (stripos($type, 'warning') !== false) {
            return Severity::warning();
        }

        // Default to error
        return Severity::error();
    }

    /**
     * Tries to guess what type of webserver is being used and tags the Sentry event with it.
     */
    private static function estimateWebServer(Scope $scope): void
    {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (function_exists('apache_get_version') || (stripos(strtolower($serverSoftware), 'apache') !== false)) {
            $scope->setTag('webserver.used', 'Apache');
        } else if (stripos(strtolower($serverSoftware), 'litespeed') !== false) {
            $scope->setTag('webserver.used', 'Litespeed');
        } else if (stripos(strtolower($serverSoftware), 'nginx') !== false) {
            $scope->setTag('webserver.used', 'NGINX');
        }
    }
}
