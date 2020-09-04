<?php 
return array (
  'debug' => 'true',
  'license' => 'PRO-4W93-NZ9G-HGUP-3C7H-I6FM',
  'salt' => '258c25516c6525b3cf178d69899699e5',
  'url' => 'https://webbhostingservices.com/boxbilling/src/',
  'admin_area_prefix' => '/bb-admin',
  'sef_urls' => 'false',
  'timezone' => 'UTC',
  'locale' => 'en_US',
  'locale_date_format' => '%A, %d %B %G',
  'locale_time_format' => ' %T',
  'path_data' => '/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-data/',
  'path_logs' => '/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-data/log/application.log',
  'log_to_db' => 'true',
  'db' => 
  array (
    'type' => 'mysql',
    'host' => 'localhost',
    'name' => 'boxbilling',
    'user' => 'boxbilling',
    'password' => 'Ai5*os5P7Gloofir',
  ),
  'twig' => 
  array (
    'debug' => true,
    'auto_reload' => true,
    'cache' => '/var/www/vhosts/webbhostingservices.com/httpdocs/boxbilling/src/bb-data/cache/',
  ),
  'api' => 
  array (
    'require_referrer_header' => 'false',
    'allowed_ips' => 
    array (
    ),
    'rate_span' => 3600,
    'rate_limit' => 1000,
  ),
);
