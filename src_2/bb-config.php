<?php 
return array (
  'debug' => true,
  'maintenance_mode' => 
  array (
    'enabled' => false,
    'allowed_urls' => 
    array (
    ),
    'allowed_ips' => 
    array (
    ),
  ),
  'salt' => '6a9763dc9200068a3b66ece26436e055',
  'url' => 'http://up-boxbilling.loc/',
  'admin_area_prefix' => '/bb-admin',
  'sef_urls' => true,
  'timezone' => 'UTC',
  'locale' => 'en_US',
  'locale_date_format' => '%A, %d %B %G',
  'locale_time_format' => ' %T',
  'path_data' => '/shared/httpd/up-boxbilling/FOSSBilling/src/bb-data',
  'path_logs' => '/shared/httpd/up-boxbilling/FOSSBilling/src/bb-data/log/application.log',
  'log_to_db' => false,
  'db' => 
  array (
    'type' => 'mysql',
    'host' => 'mysql',
    'name' => 'boxbilling',
    'user' => 'root',
    'password' => '',
  ),
  'twig' => 
  array (
    'debug' => true,
    'auto_reload' => true,
    'cache' => '/shared/httpd/up-boxbilling/FOSSBilling/src/bb-data/cache',
  ),
  'api' => 
  array (
    'require_referrer_header' => false,
    'allowed_ips' => 
    array (
    ),
    'rate_span' => 3600,
    'rate_limit' => 1000,
    'throttle_delay' => 2,
    'rate_span_login' => 60,
    'rate_limit_login' => 20,
  ),
  'guzzle' => 
  array (
    'user_agent' => 'Mozilla/5.0 (RedHatEnterpriseLinux; Linux x86_64; BoxBilling; +http://boxbilling.org) Gecko/20100101 Firefox/93.0',
    'timeout' => 0,
    'upgrade_insecure_requests' => 0,
  ),
);