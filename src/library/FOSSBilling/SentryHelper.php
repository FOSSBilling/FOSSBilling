<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Sentry\Event;
use Sentry\EventHint;
use Sentry\HttpClient\HttpClientInterface;
use Sentry\HttpClient\Request;
use Sentry\HttpClient\Response;
use Sentry\Options;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpClient\HttpClient;

class SentryHelper
{
    /**
     * This represents the last FOSSBilling release which changed the behavior of error reporting.
     * If you modify what's reported, update this to the version number to the release that includes your changes.
     * This is important as we rely on it to inform the user that they may want to review what's been changed.
     */
    final public const last_change = '0.6.0';

    // A full list of our own modules which we want to receive error reports for
    private static array $allowedModules = [
        'activity',
        'api',
        'branding',
        'cart',
        'client',
        'cookieconsent',
        'cron',
        'currency',
        'custompages',
        'dashboard',
        'email',
        'embed',
        'extension',
        'formbuilder',
        'hook',
        'index',
        'invoice',
        'massmailer',
        'news',
        'notification',
        'order',
        'orderbutton',
        'page',
        'paidsupport',
        'product',
        'profile',
        'redirect',
        'security',
        'seo',
        'serviceapikey',
        'servicecustom',
        'servicedomain',
        'servicedownloadable',
        'servicehosting',
        'servicelicense',
        'servicemembership',
        'spamchecker',
        'staff',
        'stats',
        'support',
        'system',
        'theme',
        'wysiwyg',
    ];

    // Themes we want to receive error reports for
    private static array $allowedThemes = [
        'admin_default',
        'huraga',
    ];

    // Array containing instance IDs that are blacklisted from error reporting and a timestamp of when their blacklist expires.
    private static array $blacklistedInstances = [
        '49f78ad3-9e99-492d-aa86-09ba959b16ee' => '2025-08-21',
        '40ea07d8-84db-49a0-8dcc-7ef53f9a38be' => '2025-12-01',
    ];

    private static string $placeholderFirstHalf = '--replace--this--';
    private static string $placeholderSecondHalf = 'during--release--process--';

    /**
     * Registers Sentry for error reporting. Skips the steps to enable Sentry if error reporting is not enabled.
     */
    public static function registerSentry(): void
    {
        $sentryDSN = '--replace--this--during--release--process--';

        $httpClient = new class implements HttpClientInterface {
            public function sendRequest(Request $request, Options $options): Response
            {
                $dsn = $options->getDsn();
                if (!$dsn instanceof \Sentry\Dsn) {
                    throw new \RuntimeException('The DSN option must be set to use the HttpClient.');
                }

                $requestData = $request->getStringBody();
                if ($requestData === null) {
                    throw new \RuntimeException('The request data is empty.');
                }

                $client = HttpClient::create(['bindto' => BIND_TO]);
                $requestHeaders = \Sentry\Util\Http::getRequestHeaders($dsn, \Sentry\Client::SDK_IDENTIFIER, \Sentry\Client::SDK_VERSION);
                $response = $client->request(
                    'POST',
                    $dsn->getEnvelopeApiEndpointUrl(),
                    [
                        'headers' => $requestHeaders,
                        'body' => $requestData,
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
                $module = null;
                $theme = null;

                if ($hint) {
                    $errorInfo = ErrorPage::getCodeInfo($hint->exception->getCode());
                    $exceptionPath = $hint->exception->getFile();

                    // Skip any errors that aren't supposed to be reported
                    if (!$errorInfo['report']) {
                        return null;
                    }

                    // Tag the event with the exception's category.
                    $event->setTag('exception.category', $errorInfo['category']);

                    // Tag the module name
                    if (str_starts_with($exceptionPath, (string) PATH_MODS)) {
                        $module = self::extractName($exceptionPath, PATH_MODS);
                        $event->setTag('module.name', $module);
                    }

                    // Tag the theme name
                    if (str_starts_with($exceptionPath, (string) PATH_THEMES)) {
                        $theme = self::extractName($exceptionPath, PATH_THEMES);
                        $event->setTag('theme.name', $theme);
                    }

                    // Tag the library class.
                    if (str_starts_with($exceptionPath, PATH_LIBRARY)) {
                        $event->setTag('library.class', self::getLibrary($exceptionPath));
                    }
                }

                if (self::skipReporting($module, $theme)) {
                    return null;
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

        /*
         * Here we validate that the DSN is correctly set and that error reporting is enabled before passing it off to the Sentry SDK.
         * It may look a bit odd, but the DSN placeholder value here is split into two strings and concatenated so we can easily perform a `sed` replacement of the placeholder without it effecting this check
         *
         * @phpstan-ignore-next-line (The value is replaced during release and the check is written with this in mind.)
         */
        if (Config::getProperty('debug_and_monitoring.report_errors', false) && $sentryDSN !== self::$placeholderFirstHalf . self::$placeholderSecondHalf && !empty($sentryDSN)) {
            // Per Sentry documentation, not setting this results in the SDK simply not sending any information.
            $options['dsn'] = $sentryDSN;
        }

        $options['server_name'] = INSTANCE_ID;
        \Sentry\init($options);
    }

    private static function extractName(string $exceptionPath, string $path): string
    {
        $strippedPath = str_replace($path, '', $exceptionPath);
        $level = 0;
        $name = 'Unknown';

        while ($level <= 10) {
            if (dirname($strippedPath, $level + 1) === DIRECTORY_SEPARATOR) {
                $name = trim(dirname($strippedPath, $level), DIRECTORY_SEPARATOR);

                break;
            }
            ++$level;
        }

        return $name;
    }

    private static function getLibrary(string $exceptionPath): string
    {
        return Path::getFilenameWithoutExtension($exceptionPath);
    }

    /**
     * Tries to guess what type of webserver is in use.
     */
    public static function estimateWebServer(): string
    {
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (function_exists('apache_get_version') || (stripos(strtolower((string) $serverSoftware), 'apache') !== false)) {
            return 'Apache';
        } elseif (stripos(strtolower((string) $serverSoftware), 'litespeed') !== false) {
            return 'Litespeed';
        } elseif (stripos(strtolower((string) $serverSoftware), 'nginx') !== false) {
            return 'NGINX';
        } elseif (PHP_SAPI === 'cli-server') {
            return 'PHP Development Server';
        } else {
            return 'Unknown';
        }
    }

    public static function skipReporting(?string $module = null, ?string $theme = null): bool
    {
        if (!defined('INSTANCE_ID') || !INSTANCE_ID || INSTANCE_ID === 'Unknown' || INSTANCE_ID === 'XXXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXXX') {
            return true;
        }

        if (in_array(INSTANCE_ID, self::$blacklistedInstances) && strtotime((string) self::$blacklistedInstances[INSTANCE_ID]) >= time()) {
            return true;
        }

        if (is_string($module) && !in_array(strtolower($module), self::$allowedModules)) {
            return true;
        }

        if (is_string($theme) && !in_array(strtolower($theme), self::$allowedThemes)) {
            return true;
        }

        if (Version::isPreviewVersion()) {
            return true;
        }

        return false;
    }
}
