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
use \Sentry\HttpClient\HttpClientInterface;
use \Sentry\HttpClient\Request;
use \Sentry\HttpClient\Response;
use \Sentry\Options;
use Symfony\Component\HttpClient\HttpClient;

class SentryHelper
{
    /**
     * This represents the last FOSSBilling release which changed the behavior of error reporting.
     * IF you modify what's reported, update this to the version number to the release that includes your changes.
     * This is important as we rely on it to inform the user that they may want to review what's been changed.
     */
    final public const last_change = '0.6.0';

    /**
     * Registers Sentry for error reporting. Skips the steps to enable Sentry if error reporting is not enabled.
     *
     * @param array $config The FOSSBilling config.
     */
    public static function registerSentry(array $config): void
    {
        $sentryDSN = '--replace--this--during--release--process--';

        $httpClient = new class() implements HttpClientInterface
        {
            public function sendRequest(Request $request, Options $options): Response
            {
                $dsn = $options->getDsn();
                if ($dsn === null) {
                    throw new \RuntimeException('The DSN option must be set to use the HttpClient.');
                }

                $requestData = $request->getStringBody();
                if ($requestData === null) {
                    throw new \RuntimeException('The request data is empty.');
                }

                $client = HttpClient::create();
                $requestHeaders = \Sentry\Util\Http::getRequestHeaders($dsn, \Sentry\Client::SDK_IDENTIFIER, \Sentry\Client::SDK_VERSION);
                $response = $client->request(
                    'POST',
                    $dsn->getEnvelopeApiEndpointUrl(),
                    [
                        'headers' => $requestHeaders,
                        'body'    => $requestData,
                    ]
                );

                return new Response($response->getStatusCode(), $response->getHeaders(), '');
            }
        };

        // Registers Sentry for error reporting if enabled.
        $options = [
            // We explicitly set the HTTP client to use the Symfony HTTP client to provide wider support VS their default cURL client.
            'http_client' => $httpClient,

            'before_send' => function (Event $event, ?EventHint $hint): ?Event {
                if ($hint) {
                    $errorInfo = ErrorPage::getCodeInfo($hint->exception->getCode());

                    if (!$errorInfo['report']) {
                        return null;
                    }
                }

                return $event;
            },

            'ignore_exceptions' => [InformationException::class],

            'environment' => Environment::getCurrentEnvironment(),
            'release' => Version::VERSION,

            // This option is disabled by default, but we set it to false here to be explicit & ensure it can never change unexpectedly.
            'send_default_pii' => false,

            // Stack traces are always sent for Exceptions, but if debug mode is enabled we will send them for errors too.
            'attach_stacktrace' => (bool)DEBUG,
        ];

        /**
         * Here we validate that the DSN is correctly set and that error reporting is enabled before passing it off to the Sentry SDK.
         * It may look a bit odd, but the DSN placeholder value here is split into two strings and concatenated so we can easily perform a `sed` replacement of the placeholder without it effecting this check
         */
        if ($config['debug_and_monitoring']['report_errors'] && $sentryDSN !== '--replace--this--' . 'during--release--process--' && !empty($sentryDSN)) {
            // Per Sentry documentation, not setting this results in the SDK simply not sending any information.
            $options['dsn'] = $sentryDSN;
        }

        $options['server_name'] = INSTANCE_ID;
        \Sentry\init($options);
    }

    /**
     * Captures an exception and sends it to Sentry, adding additional information that we'd find useful.
     *
     * @param \Exception|\Error $e
     */
    public static function captureException(\Exception|\Error $e)
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
        return match ($number) {
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse error',
            E_NOTICE => 'Runtime notice',
            E_CORE_ERROR => 'Fatal PHP startup error',
            E_CORE_WARNING => 'PHP startup warning',
            E_COMPILE_ERROR => 'Zend compile error',
            E_COMPILE_WARNING => 'Zend compile warning',
            E_USER_ERROR => 'User-generated error',
            E_USER_WARNING => 'User-generated warning',
            E_USER_NOTICE => 'User-generated notice',
            E_STRICT => 'PHP Strict code checking',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED => 'PHP deprecation warning',
            E_USER_DEPRECATED => 'User-generated deprecation warning',
            default => 'Unknown error',
        };
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
        if (function_exists('apache_get_version') || (stripos(strtolower((string) $serverSoftware), 'apache') !== false)) {
            $scope->setTag('webserver.used', 'Apache');
        } else if (stripos(strtolower((string) $serverSoftware), 'litespeed') !== false) {
            $scope->setTag('webserver.used', 'Litespeed');
        } else if (stripos(strtolower((string) $serverSoftware), 'nginx') !== false) {
            $scope->setTag('webserver.used', 'NGINX');
        }
    }
}
