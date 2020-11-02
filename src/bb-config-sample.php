<?php
/**
 * BoxBilling configuration file example
 *
 * If you are not using the web-installer, you can rename this file
 * to "bb-config.php" and fill in the values.
 * Import /install/structure.sql to your database
 * Import /install/content.sql to your database
 * Open browser http://www.yourdomain.com/index.php?_url=/bb-admin to create new admin account.
 * Remove /install directory
 */

return array(

    'salt'        => '',

    /**
     * Full URL where BoxBilling is installed with trailing slash
     */
    'url'     => 'http://localhost/',

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
    'sef_urls'  => false,

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
    ),
);
