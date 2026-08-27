<?php

declare(strict_types=1);
/**
 * FOSSBilling configuration file example.
 *
 * If you are not using the web installer, you can rename this file
 * to "config.php" and fill in the values.
 * Import /install/sql/structure.sql to your database
 * Import /install/sql/content.sql to your database
 * Open browser https://www.yourdomain.com/admin to create a new admin account.
 * Remove /install directory
 *
 * For more information, see the documentation: https://docs.fossbilling.org/customizing-fossbilling/config/
 */

return [
    /*
     * These configuration options allow you to configure the security options inside of FOSSBilling.
     * The default values are what we recommended running unless they are causing issues.
     */
    'security' => [
        'mode' => 'strict',
        'force_https' => true,
        /*
         * Configure trusted reverse proxies when HTTPS is terminated before the PHP backend.
         * Keep this disabled unless requests arrive through a proxy you control.
         */
        'trusted_proxies' => [
            'enabled' => false,
            'proxies' => [],
            'headers' => 'x_forwarded',
        ],
        'session_lifespan' => 7200,
        'session_regeneration_grace_period' => 300,
        'perform_session_fingerprinting' => true,
        'debug_fingerprint' => false,
    ],

    'debug_and_monitoring' => [
        /*
         * Enable or disable displaying advanced debugging messages.
         * You should keep this disabled unless you're making tests as it can reveal some information about your server.
         */
        'debug' => false,
        /*
         * Enable or disable stacktraces when an exception is thrown (also requires debug to be enabled).
         */
        'log_stacktrace' => true,
        /*
         * How long the stacktrace should be.
         */
        'stacktrace_length' => 25,

        /*
         * Enables automated error, stability, and performance reporting.
         * Private information is scrubbed from any info before being sent.
         * FOSSBilling uses Sentry.io for error reporting which has a full writeup on their security and privacy practices here: https://sentry.io/security/.
         * Enabling error reporting will help us proactively identify and fix bugs in FOSSBilling as well as provide better technical support.
         */
        'report_errors' => false,
    ],

    'info' => [
        'salt' => bin2hex(random_bytes(16)),
        'instance_id' => 'XXXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXXX',
    ],

    /*
     * Full URL where FOSSBilling is installed with trailing slash.
     */
    'url' => 'localhost/',

    /*
     * The URL prefix to access the admin area. Ex: '/admin' for https://example.com/admin.
     */
    'admin_area_prefix' => '/admin',

    /*
     * Configure the update branch for the automatic updater.
     * Currently acceptable options are "release" or "preview".
     */
    'update_branch' => 'release',

    'maintenance_mode' => [
        /*
         * Enable or disable the system maintenance mode.
         * Enabling this will block public access to your website, and API endpoints except the allowed ones won't work
         * However, this will not block access to the administrator area.
         */
        'enabled' => false,

        /*
         * Don't block these URLs when the maintenance is going on.
         * Supports wildcard (e.g. '/api/guest/staff/*').
         */
        'allowed_urls' => [],

        /*
         * Don't block these IP/Subnet addresses when the maintenance is going on.
         * Supported formats: 127.0.0.1、127.0.0.1/32.
         */
        'allowed_ips' => [],
    ],

    /*
     * FOSSBilling will automatically execute cron when you login to the admin panel if it hasn't been executed in awhile. You can disable this fallback here.
     */
    'disable_auto_cron' => true,

    /*
     * These configuration options allow you to configure the default localisation.
     */
    'i18n' => [
        'locale' => 'en_US',
        // Set to false to always use the configured locale unless the user manually selects another language.
        'auto_detect_locale' => true,
        'timezone' => 'UTC',

        // Short names for formats (none, short, medium, long).
        // @see https://www.php.net/manual/en/class.intldateformatter.php
        'date_format' => 'medium',
        'time_format' => 'short',

        // Specifying a pattern will override the above date/time options.
        // @see https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax
        'datetime_pattern' => '',
    ],

    /*
     * Set location to store sensitive data.
     */
    'path_data' => __DIR__ . '/data',

    'db' => [
        /*
         * Database driver. Don't change this if in doubt.
         * Supported: 'pdo_mysql' (MySQL/MariaDB), 'pdo_pgsql' (PostgreSQL), 'pdo_sqlite' (SQLite).
         * pdo_sqlite ignores 'host'/'port'/'user'/'password' below and instead reads a 'path'
         * (filesystem path to the database file) or 'memory' (bool, for an in-memory database).
         */
        'driver' => 'pdo_mysql',

        /*
         * Database hostname. Don't change this if in doubt.
         */
        'host' => getenv('DB_HOST') ?: '127.0.0.1',

        /*
         * The name of the database for FOSSBilling.
         */
        'name' => getenv('DB_NAME') ?: 'fossbilling',

        /*
         * Database username.
         */
        'user' => getenv('DB_USER') ?: 'foo',

        /*
         * Database password.
         */
        'password' => getenv('DB_PASS') ?: 'bar',

        /*
         * Database Port.
         */
        'port' => getenv('DB_PORT') ?: '3306',

        /*
         * Optional session timeouts (seconds) applied to database connections.
         * When unset, the MySQL server defaults are preserved.
         */
        // 'interactive_timeout' => 28800,
        // 'wait_timeout' => 28800,
    ],

    /*
     * Cache backend used for the general application cache, the rate limiter, and the
     * Doctrine ORM metadata/query/result cache. This is unrelated to the 'twig' cache below.
     */
    'cache' => [
        /*
         * Supported values: 'filesystem' (default), 'redis', 'memcached'.
         * 'redis' requires the PHP redis (or relay) extension, and 'memcached' requires the
         * PHP memcached extension. Also configurable from the admin area under System > Settings > Cache.
         */
        'driver' => 'filesystem',

        /*
         * Used when 'driver' is set to 'redis'.
         */
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,

            /*
             * TLS ('rediss://') for the connection to the Redis server. Also configurable from
             * the admin area under System > Settings > Cache.
             *
             * This matters most when 'host' is anything other than a loopback address
             * (127.0.0.1, ::1, localhost) AND 'password' is set: in that case, without TLS, the
             * password and every cached value would cross the network in plain text, so
             * CacheFactory refuses to connect at all until either TLS is enabled here or the
             * connection is moved to a loopback address. A loopback connection, or one with no
             * password configured, is never blocked - TLS there is optional hardening, not a
             * requirement. Note this deliberately does not try to recognize a "trusted private
             * network" host (a Docker service name, a VPC-internal IP) as exempt the way loopback
             * is: there's no reliable way to tell those apart from a genuinely remote host from
             * the hostname/IP alone, so an admin relying on network isolation instead of TLS
             * should leave 'password' unset rather than expect this check to special-case it.
             */
            'tls' => [
                'enabled' => false,

                // Verify the server's certificate, and that its certificate name matches 'host'.
                // Leave both enabled unless you have a specific reason not to.
                'verify_peer' => true,
                'verify_peer_name' => true,

                // Path to a CA bundle to trust, if the Redis server's certificate isn't signed by
                // a CA your system already trusts.
                'cafile' => null,

                // Only for a self-signed certificate in a development/testing environment - never
                // enable this in production.
                'allow_self_signed' => false,
            ],
        ],

        /*
         * Used when 'driver' is set to 'memcached'.
         */
        'memcached' => [
            'host' => '127.0.0.1',
            'port' => 11211,
        ],
    ],

    'twig' => [
        'debug' => false,
        'auto_reload' => true,
        'cache' => __DIR__ . '/data/cache',
        'strict_variables' => true,
    ],

    'api' => [
        // All requests made to the API must have referrer request header with the same URL as the FOSSBilling installation
        'require_referrer_header' => false,

        // Empty array will allow all IPs to access the API
        'allowed_ips' => [],

        // Enables CSRF token protection.
        // Disabling this is highly discouraged and opens your instance to a known vulnerability.
        'CSRFPrevention' => true,
    ],

    'rate_limiter' => [
        'enabled' => true,

        /*
         * Any IP address within this list will not be put through the rate-limiter system.
         * This is useful if you have an application with a static IP address that needs to make frequent API requests to FOSSBilling.
         */
        'whitelist_ips' => [],

        /*
         * Override individual rate limiter policies here.
         * Defaults are defined in FOSSBilling\Security\RateLimiter::getDefaultConfig().
         */
        'policies' => [
            // 'client_signup' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour'],
        ],
    ],
];
