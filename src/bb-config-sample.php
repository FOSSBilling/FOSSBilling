<?php
/**
 * FOSSBilling configuration file example.
 *
 * If you are not using the web installer, you can rename this file
 * to "bb-config.php" and fill in the values.
 * Import /install/sql/structure.sql to your database
 * Import /install/sql/content.sql to your database
 * Open browser http://www.yourdomain.com/index.php?_url=/bb-admin to create a new admin account.
 * Remove /install directory
 */

return [
    'salt' => '',

    /**
     * Full URL where FOSSBilling is installed with trailing slash.
     */
    'url' => 'http://localhost/',

    /**
     * The URL prefix to access the BB admin area. Ex: '/bb-admin' for https://example.com/bb-admin.
     */
    'admin_area_prefix' => '/bb-admin',

    /**
     * Enable or disable displaying advanced debugging messages.
     * You should keep this disabled unless you're making tests as it can reveal some information about your server.
     */
    'debug' => false,

    'maintenance_mode' => [
        /**
         * Enable or disable the system maintenance mode.
         * Enabling this will block public access to your website, and API endpoints except the allowed ones won't work
         * However, this will not block access to the administrator area.
         *
         * @since 4.22.0
         */
        'enabled' => false,

        /**
         * Don't block these URLs when the maintenance is going on.
         * Supports wildcard (e.g. '/api/guest/staff/*').
         *
         * @since 4.22.0
         */
        'allowed_urls' => [],

        /**
         * Don't block these IP/Subnet addresses when the maintenance is going on.
         * Supported formats: 127.0.0.1、127.0.0.1/32.
         *
         * @since 4.22.0
         */
        'allowed_ips' => [],
    ],

    /**
     * Enable or disable search engine friendly URLs.
     * Configure .htaccess file before enabling this feature
     * Set to TRUE if using nginx.
     */
    'sef_urls' => true,

    /**
     * System timezone.
     */
    'timezone' => 'UTC',

    /**
     * FOSSBilling locale.
     */
    'locale' => 'en_US',

    /**
     * Set default date format for localized strings.
     *
     * @see http://php.net/manual/en/function.strftime.php
     */
    'locale_date_format' => '%A, %d %B %G',

    /**
     * Set default time format for localized strings.
     *
     * @see http://php.net/manual/en/function.strftime.php
     */
    'locale_time_format' => ' %T',

    /**
     * Set location to store sensitive data.
     */
    'path_data' => dirname(__FILE__).'/bb-data',

    'path_logs' => dirname(__FILE__).'/bb-data/log/application.log',

    'log_to_db' => true,

    'db' => [
        /**
         * Database type. Don't change this if in doubt.
         */
        'type' => 'mysql',

        /**
         * Database hostname. Don't change this if in doubt.
         */
        'host' => getenv('DB_HOST') ?: '127.0.0.1',

        /**
         * The name of the database for FOSSBilling.
         */
        'name' => getenv('DB_NAME') ?: 'fossbilling',

        /**
         * Database username.
         */
        'user' => getenv('DB_USER') ?: 'foo',

        /**
         * Database password.
         */
        'password' => getenv('DB_PASS') ?: 'foo',

        /**
         * Database Port.
         */
        'port' => getenv('DB_PORT') ?: '3306',
    ],

    'twig' => [
        'debug' => false,
        'auto_reload' => false,
        'cache' => dirname(__FILE__).'/bb-data/cache',
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
         *
         * @since 4.22.0
         */

        // Throttling delay
        'throttle_delay' => 2,

        // Time span login for limit in seconds
        'rate_span_login' => 60,

        // How many login requests allowed per time span
        'rate_limit_login' => 20,
    ],

    'guzzle' => [
        /**
         * The user agent to be used when making requests to external services.
         *
         * @since 4.22.0
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
         */
        'user_agent' => 'Mozilla/5.0 (RedHatEnterpriseLinux; Linux x86_64; FOSSBilling; +https://fossbilling.org) Gecko/20100101 Firefox/93.0',

        /**
         * Default request timeout
         * Setting 0 will disable this limitation.
         *
         * @since 4.22.0
         * @see https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/timeout
         */
        'timeout' => 0,

        /**
         * The HTTP Upgrade-Insecure-Requests header sends a signal to the server
         * expressing the client’s preference for an encrypted response.
         *
         * 0: don't ask for an encrypted response
         * 1:       ask for an encrypted response
         *
         * @since 4.22.0
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Upgrade-Insecure-Requests
         */
        'upgrade_insecure_requests' => 0,
    ],
];
