<?php
/**
 * BoxBilling configuration file example
 *
 * If you are not using the web installer, you can rename this file
 * to "bb-config.php" and fill in the values.
 * Import /install/sql/structure.sql to your database
 * Import /install/sql/content.sql to your database
 * Open browser http://www.yourdomain.com/index.php?_url=/bb-admin to create a new admin account.
 * Remove /install directory
 */

return array(

    'salt'        => '',

    /**
     * Full URL where BoxBilling is installed with trailing slash
     */
    'url'     => 'http://localhost/',

    /**
     * The URL prefix to access the BB admin area. Ex: '/bb-admin' for https://example.com/bb-admin
     */
    'admin_area_prefix' =>  '/bb-admin',

    /**
     * Enable or disable displaying advanced debugging messages.
     * You should keep this disabled unless you're making tests as it can reveal some information about your server.
     */
    'debug'     => false,

    /**
     * Enable or disable search engine friendly URLs.
     * Configure .htaccess file before enabling this feature
     * Set to TRUE if using nginx
     */
    'sef_urls'  => true,

    /**
     * System timezone
     */
    'timezone'    =>  'UTC',

    /**
     * BoxBilling locale
     */
    'locale'    =>  'en_US',

    /**
     * Set default date format for localized strings
     * @see http://php.net/manual/en/function.strftime.php
     */
    'locale_date_format'    =>  '%A, %d %B %G',

    /**
     * Set default time format for localized strings
     * @see http://php.net/manual/en/function.strftime.php
     */
    'locale_time_format'    =>  ' %T',

    /**
     * Set location to store sensitive data
     */
    'path_data'  => dirname(__FILE__) . '/bb-data',

    'path_logs'  => dirname(__FILE__) . '/bb-data/log/application.log',

    'log_to_db'  => true,

    'db'    =>  array(
        /**
         * Database type. Don't change this if in doubt.
         */
        'type'   => 'mysql',

        /**
         * Database hostname. Don't change this if in doubt.
         */
        'host'   => getenv('DB_HOST') ?: '127.0.0.1',

        /**
         * The name of the database for BoxBilling
         */
        'name'   => getenv('DB_NAME') ?: 'boxbilling',

        /**
         * Database username
         */
        'user'   => getenv('DB_USER') ?: 'foo',

        /**
         * Database password
         */
        'password'   => getenv('DB_PASS') ?: 'foo',

        /**
         * Database Port
         */
        'port'   => getenv('DB_PORT') ?: '3306',
    ),

    'twig'   =>  array(
        'debug'         =>  false,
        'auto_reload'   =>  false,
        'cache'         =>  dirname(__FILE__) . '/bb-data/cache',
    ),

    'api'   =>  array(
        // All requests made to the API must have referrer request header with the same URL as the BoxBilling installation
        'require_referrer_header'   =>  false,

        // Empty array will allow all IPs to access the API
        'allowed_ips'       =>  array(),

        // Time span for limit in seconds
        'rate_span'         =>  60 * 60,

        // How many requests allowed per time span
        'rate_limit'        =>  1000,

        /**
         * Note about rate-limiting login attempts:
         * When the limit is reached, a default delay of 2 seconds is added to the request. 
         * This makes brute-forcing a password useless while not outright blocking legitimate traffic.
         * When calculating, ensure the rate-limited traffic can still make enough requests to stay rate limited
         * Ex: One request every 2 seconds is more than 20 times in 1 minute, so the IP will remain throttled
         */

        // Throttling delay
        'throttle_delay'         =>  2,

        // Time span login for limit in seconds
        'rate_span_login'         =>  60,

        // How many login requests allowed per time span
        'rate_limit_login'        =>  20,
    ),

    'guzzle'   =>  array(
        // The user agent to be used when making requests to external services
        'user_agent'    =>  'Mozilla/5.0 (RedHatEnterpriseLinux; Linux x86_64; BoxBilling; +http://boxbilling.org) Gecko/20100101 Firefox/93.0',

        // Default request timeout
        'timeout'       => 5.0,

        /**
         * The HTTP Upgrade-Insecure-Requests header sends a signal to the server
         * expressing the client’s preference for an encrypted response.
         * 
         * 0: don't ask for an encrypted response
         * 1:       ask for an encrypted response
         * 
         * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Upgrade-Insecure-Requests
         */
        'upgrade_insecure_requests' => 0
    ),
);
