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

use FOSSBilling\Exception\InformationException;
use FOSSBilling\Http\ErrorPage;
use FOSSBilling\System\Config;
use FOSSBilling\System\Environment;
use FOSSBilling\System\Version;
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
    final public const string last_change = '0.8.7';

    /**
     * `package@version` is required for Sentry to parse a release as semver - not composer.json's
     * "fossbilling/fossbilling" package name, since Sentry release identifiers can't contain "/".
     * Keep release-sentry.yml's `release:` input in sync with this.
     */
    private const string SENTRY_RELEASE_PACKAGE = 'fossbilling';

    // A full list of our own modules which we want to receive error reports for
    private const array ALLOWED_MODULES = [
        'activity',
        'api',
        'branding',
        'cart',
        'client',
        'cookieconsent',
        'cron',
        'currency',
        'custompages',
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
        'antispam',
        'staff',
        'stats',
        'support',
        'system',
        'theme',
    ];

    // Themes we want to receive error reports for
    private const array ALLOWED_THEMES = [
        'admin_default',
        'huraga',
    ];

    // Array containing instance IDs that are blacklisted from error reporting and a timestamp of when their blacklist expires.
    private const array BLACKLISTED_INSTANCES = [
        '49f78ad3-9e99-492d-aa86-09ba959b16ee' => '2025-08-21',
        '40ea07d8-84db-49a0-8dcc-7ef53f9a38be' => '2025-12-01',
    ];

    private static string $placeholderFirstHalf = '--replace--this--';
    private static string $placeholderSecondHalf = 'during--release--process--';

    /**
     * Registers Sentry for error reporting. Skips the steps to enable Sentry if error reporting is not enabled.
     */
    public static function registerSentry(string $serverSoftware = ''): void
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

            /*
             * Every PHP version bump deprecates a fresh batch of constants/casts/signatures, and we don't
             * control when self-hosted instances upgrade PHP - so on an instance running ahead of our tested
             * baseline, these fire on effectively every request, forever. `error_reporting(E_ALL)` (see
             * load.php) means the SDK's ErrorListenerIntegration would otherwise capture E_DEPRECATED same as
             * any other error. We deliberately keep E_USER_DEPRECATED enabled: that's our own trigger_error()
             * calls flagging things we should investigate (see Currency\Service::getExchangeRateAPIRates()),
             * not interpreter noise.
             *
             * Deliberately not also excluding E_STRICT: referencing that constant at all triggers its own
             * "Constant E_STRICT is deprecated" notice as of PHP 8.4, and E_STRICT hasn't been a real error
             * level PHP ever raises since 8.0 anyway (its cases were folded into E_DEPRECATED/E_WARNING), so
             * excluding it buys nothing.
             */
            'error_types' => E_ALL & ~E_DEPRECATED,

            'before_send' => function (Event $event, ?EventHint $hint) use ($serverSoftware): ?Event {
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

                $event->setTag('webserver.used', self::estimateWebServer($serverSoftware));

                return $event;
            },

            'ignore_exceptions' => [InformationException::class],

            'environment' => Environment::getCurrentEnvironment(),

            // Only affects releases from here on - see SENTRY_RELEASE_PACKAGE.
            'release' => self::SENTRY_RELEASE_PACKAGE . '@' . Version::VERSION,

            // This option is disabled by default, but we set it to false here to be explicit & ensure it can never change unexpectedly.
            'send_default_pii' => false,

            // Stack traces aren't that much data to send and are valuable for us, so let's always send them.
            'attach_stacktrace' => true,

            // Strips the install's own filesystem path from stack trace filenames - otherwise leaks per-install hosting details (usernames, domains) to Sentry.
            // PATH_ROOT goes first so it always wins; the include_path entries are the SDK's own default and are kept as a fallback for paths reached via a symlinked docroot.
            'prefixes' => [PATH_ROOT, ...array_filter(explode(PATH_SEPARATOR, get_include_path() ?: ''))],
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
    public static function estimateWebServer(string $serverSoftware = ''): string
    {
        if (function_exists('apache_get_version') || (stripos(strtolower($serverSoftware), 'apache') !== false)) {
            return 'Apache';
        } elseif (stripos(strtolower($serverSoftware), 'litespeed') !== false) {
            return 'Litespeed';
        } elseif (stripos(strtolower($serverSoftware), 'nginx') !== false) {
            return 'NGINX';
        } elseif (PHP_SAPI === 'cli-server') {
            return 'PHP Development Server';
        }

        return 'Unknown';
    }

    public static function skipReporting(?string $module = null, ?string $theme = null): bool
    {
        // @phpstan-ignore-next-line
        if (!defined('INSTANCE_ID') || !INSTANCE_ID || INSTANCE_ID === 'Unknown' || INSTANCE_ID === 'XXXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXXX') {
            return true;
        }

        if (array_key_exists(INSTANCE_ID, self::BLACKLISTED_INSTANCES) && strtotime((string) self::BLACKLISTED_INSTANCES[INSTANCE_ID]) >= time()) {
            return true;
        }

        if (is_string($module) && !in_array(strtolower($module), self::ALLOWED_MODULES, true)) {
            return true;
        }

        if (is_string($theme) && !in_array(strtolower($theme), self::ALLOWED_THEMES, true)) {
            return true;
        }

        if (Version::isPreviewVersion()) {
            return true;
        }

        return false;
    }
}
