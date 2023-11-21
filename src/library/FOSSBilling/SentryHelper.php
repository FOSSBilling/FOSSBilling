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

                    // Skip any errors that aren't supposed to be reported
                    if (!$errorInfo['report']) {
                        return null;
                    }

                    // Tag the event with the exception's category.
                    $event->setTag('exception.category', $errorInfo['category']);

                    // Tag the event with the correct module / library
                    $exceptionPath = $hint->exception->getFile();
                    if (str_starts_with($exceptionPath, PATH_MODS)) {
                        $event->setTag('module.name', self::getModule($exceptionPath));
                    } else if (str_starts_with($exceptionPath, PATH_LIBRARY)) {
                        $event->setTag('library.class', self::getLibrary($exceptionPath));
                    }
                }

                $event->setTag('webserver.used', self::estimateWebServer());
                return $event;
            },

            'ignore_exceptions' => [InformationException::class],

            'environment' => Environment::getCurrentEnvironment(),
            'release' => Version::VERSION,

            // This option is disabled by default, but we set it to false here to be explicit & ensure it can never change unexpectedly.
            'send_default_pii' => false,

            // Stack traces aren't that much data to send and are valuable for us, so let's always send them.
            'attach_stacktrace' => true,
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

    private static function getModule(string $exceptionPath)
    {
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
        return $module;
    }

    private static function getLibrary(string $exceptionPath)
    {
        return pathinfo($exceptionPath, PATHINFO_FILENAME);
    }

    /**
     * Tries to guess what type of webserver is being used and tags the Sentry event with it.
     */
    private static function estimateWebServer(): string
    {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (function_exists('apache_get_version') || (stripos(strtolower($serverSoftware), 'apache') !== false)) {
            return 'Apache';
        } else if (stripos(strtolower($serverSoftware), 'litespeed') !== false) {
            return 'Litespeed';
        } else if (stripos(strtolower($serverSoftware), 'nginx') !== false) {
            return 'NGINX';
        } else {
            return 'Unknown';
        }
    }
}
