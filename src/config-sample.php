<?php

/**
 * FOSSBilling configuration file example.
 *
 * If you are not using the web installer, you can rename this file
 * to "config.php" and fill in the values.
 * Import /install/sql/structure.sql to your database
 * Import /install/sql/content.sql to your database
 * Open browser https://www.yourdomain.com/admin to create a new admin account.
 * Remove /install directory
 */

return [
    /* 
     * These configuration options allow you to configure the security options inside of FOSSBilling.
     * The default values are what we recommended running unless they are causing issues.
     */
    'security' => [
        'mode' => 'strict',
        'force_https' => true,
        'session_lifespan' => 7200,
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
    'url' => 'http://localhost/',

    /*
     * The URL prefix to access the BB admin area. Ex: '/admin' for https://example.com/admin.
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
    'disable_auto_cron' => false,

    /* 
     * These configuration options allow you to configure the default localisation.
     */
    'i18n' => [
        'locale' => 'en_US',
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
         * Database type. Don't change this if in doubt.
         */
        'type' => 'mysql',

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
    ],

    'twig' => [
        'debug' => false,
        'auto_reload' => true,
        'cache' => __DIR__ . '/data/cache',
    ],

    'api' => [
        // All requests made to the API must have referrer request header with the same URL as the FOSSBilling installation
        'require_referrer_header' => false,

        // Empty array will allow all IPs to access the API
        'allowed_ips' => [],

        // Time span for limit in seconds
        'rate_span' => 60 * 60,

        // How many requests allowed per time span
        'rate_limit' => 1000,

        /**
         * Note about rate-limiting login attempts:
         * When the limit is reached, a default delay of 2 seconds is added to the request.
         * This makes brute-forcing a password useless while not outright blocking legitimate traffic.
         * When calculating, ensure the rate-limited traffic can still make enough requests to stay rate limited
         * Ex: One request every 2 seconds is more than 20 times in 1 minute, so the IP will remain throttled.
         */
        'throttle_delay' => 2,

        // Time span login for limit in seconds
        'rate_span_login' => 60,

        // How many login requests allowed per time span
        'rate_limit_login' => 20,

        /*
        * This enables the usage of a token to protect the system from CSRF attacks.
        * Disabling this is highly discouraged and opens your instance to a known vulnerability.
        * This option is only here for backwards compatibility.
        */
        'CSRFPrevention' => true,

        /**
         * Any IP address within this list will not be put through the rate-limiter system.
         * This is useful if you have an application with a static IP address that needs to make frequent API requests to FOSSBilling.
         */
        'rate_limit_whitelist' => [],
    ],
];
