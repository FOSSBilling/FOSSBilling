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

class SentryHelper
{
    public static function registerSentry(array $config): void
    {
        $sentryDSN = '--replace--this--during--release--process--';
        //$sentryDSN = 'https://1735e8299adb8d9099e47eefcf0b8f42@o4506063756328960.ingest.sentry.io/4506063757901824';

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
        ];

        /**
         * Here we validate that the DSN is correctly set and that error reporting is enabled before passing it off to the Sentry SDK.
         * It may look a bit odd, but the DSN placeholder value here is split into two strings and concatenated so we can easily perform a `sed` replacement of the placeholder without it effecting this check
         */
        if ($config['debug_and_monitoring']['report_errors'] && $sentryDSN !== '--replace--this--' . 'during--release--process--' && !empty($sentryDSN)) {
            // Per Sentry documentation, not setting this results in the SDK simply not sending any information.
            $options['dsn'] = $sentryDSN;
        };

        // If the system URL is correctly set, we can get the UUID for this instance. Otherwise, let Sentry try to come up with one
        if (!empty(BB_URL)) {
            $options['server_name'] = Instance::getInstanceID();
        }

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
            $exceptionpath = $e->getFile();

            // Tag the event with the exception's category.
            $scope->setTag('exception.category', $errorInfo['category']);

            // If we can, tag the event with the module or library that threw the exception.
            if (str_starts_with($exceptionpath, PATH_MODS)) {
                $strippedPath = str_replace(PATH_MODS, '', $exceptionpath);
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
            } else if (str_starts_with($exceptionpath, PATH_LIBRARY)) {
                $scope->setTag('library.class', pathinfo($exceptionpath, PATHINFO_FILENAME));
            }

            // Finally tag the event with what is probably the webserver in use, then send the event to Sentry.
            self::estimateWebServer($scope);
            \Sentry\captureException($e);
        });
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
