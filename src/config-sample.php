<?php
/**
 * FOSSBilling configuration file example.
 *
 * If you are not using the web installer, you can rename this file
 * to "config.php" and fill in the values.
 * Import /install/sql/structure.sql to your database
 * Import /install/sql/content.sql to your database
 * Open browser https://www.yourdomain.com/index.php?_url=/admin to create a new admin account.
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
        'cookie_lifespan' => 7200,
    ],

    'salt' => '',

    /*
     * Full URL where FOSSBilling is installed with trailing slash.
     */
    'url' => 'http://localhost/',

    /*
     * The URL prefix to access the BB admin area. Ex: '/admin' for https://example.com/admin.
     */
    'admin_area_prefix' => '/admin',

    /*
     * Enable or disable displaying advanced debugging messages.
     * You should keep this disabled unless you're making tests as it can reveal some information about your server.
     */
    'debug' => false,

    /*
     * Configure the update branch for the automatic updater.
     * Currently acceptable options are "release" or "preview".
     */
    'update_branch' => 'release',

    /*
     * Enable or disable stacktraces when an exception is thrown (also requires debug to be enabled).
     */
    'log_stacktrace' => true,
    /*
     * How long the stacktrace should be.
     */
    'stacktrace_length' => 25,

    'maintenance_mode' => [
        /*
         * Enable or disable the system maintenance mode.
         * Enabling this will block public access to your website, and API endpoints except the allowed ones won't work
         * However, this will not block access to the administrator area.
         *
         * @since 4.22.0
         */
        'enabled' => false,

        /*
         * Don't block these URLs when the maintenance is going on.
         * Supports wildcard (e.g. '/api/guest/staff/*').
         *
         * @since 4.22.0
         */
        'allowed_urls' => [],

        /*
         * Don't block these IP/Subnet addresses when the maintenance is going on.
         * Supported formats: 127.0.0.1、127.0.0.1/32.
         *
         * @since 4.22.0
         */
        'allowed_ips' => [],
    ],

    /*
     * FOSSBilling will automatically execute cron when you login to the admin panel if it hasn't been executed in awhile. You can disable this fallback here.
     */
    'disable_auto_cron' => false,

    /*
     * Enable or disable search engine friendly URLs.
     * Configure .htaccess file before enabling this feature
     * Set to TRUE if using nginx.
     */
    'sef_urls' => true,

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

    'path_logs' => __DIR__ . '/data/log/application.log',

    'log_to_db' => true,

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
        'password' => getenv('DB_PASS') ?: 'foo',

        /*
         * Database Port.
         */
        'port' => getenv('DB_PORT') ?: '3306',
    ],

    'twig' => [
        'debug' => false,
        'auto_reload' => false,
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

        /*
         * Note about rate-limiting login attempts:
         * When the limit is reached, a default delay of 2 seconds is added to the request.
         * This makes brute-forcing a password useless while not outright blocking legitimate traffic.
         * When calculating, ensure the rate-limited traffic can still make enough requests to stay rate limited
         * Ex: One request every 2 seconds is more than 20 times in 1 minute, so the IP will remain throttled.
         *
         * @since 4.22.0
         */

        // Throttling delay
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
    ],
];
