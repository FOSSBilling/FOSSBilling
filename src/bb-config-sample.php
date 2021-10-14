<?php
/**
 * BoxBilling configuration file example
 *
 * If you are not using the web-installer, you can rename this file
 * to "bb-config.php" and fill in the values.
 * Import /install/sql/structure.sql to your database
 * Import /install/sql/content.sql to your database
 * Open browser http://www.yourdomain.com/index.php?_url=/bb-admin to create new admin account.
 * Remove /install directory
 */

return array(

    'salt'        => '',

    /**
     * Full URL where BoxBilling is installed with trailing slash
     */
    'url'     => 'http://localhost/',

    /**
     * The URL prefix to access the BB admin area. Ex: '/bb-admin' = https://example.com/bb-admin
     */
    'admin_area_prefix' =>  '/bb-admin',

    /**
     * Enable or Disable the display of notices
     */
    'debug'     => false,

    /**
     * Enable or Disable search engine friendly urls.
     * Configure .htaccess file before enabling this feature
     * Set to TRUE if using nginx
     */
    'sef_urls'  => true,

    /**
     * Application timezone
     */
    'timezone'    =>  'UTC',

    /**
     * Set BoxBilling locale
     */
    'locale'    =>  'en_US',

    /**
     * Set default date format for localized strings
     * Format information: http://php.net/manual/en/function.strftime.php
     */
    'locale_date_format'    =>  '%A, %d %B %G',

    /**
     * Set default time format for localized strings
     * Format information: http://php.net/manual/en/function.strftime.php
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
        'type'   =>'mysql',

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
        'port'   =>'3306',
    ),

    'twig'   =>  array(
        'debug'         =>  false,
        'auto_reload'   =>  false,
        'cache'         =>  dirname(__FILE__) . '/bb-data/cache',
    ),

    'api'   =>  array(
        // all requests made to API must have referrer request header with the same url as BoxBilling installation
        'require_referrer_header'   =>  false,

        // empty array will allow all IPs to access API
        'allowed_ips'       =>  array(),

        // Time span for limit in seconds
        'rate_span'         =>  60 * 60,

        // How many requests allowed per time span
        'rate_limit'        =>  1000,

        /**
         * Note about rate limiting login attempts:
         * When the limit is reach, a default delay of 2 seconds is added to the request. 
         * This makes brute forcing a password basically useless while not outright blocking legitimate traffic.
         * When calculating, ensure the rate limited traffic can still make enough requests to stay rate limited
         * Ex: One request every 2 seconds is more than 20 times in 1 minute, so the IP will remain throttled
         */

        // Throttling delay
        'throttle_delay'         =>  2,

        // Time span login for limit in seconds
        'rate_span_login'         =>  60,

        // How many login requests allowed per time span
        'rate_limit_login'        =>  20,
    ),
);
