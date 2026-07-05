<?php
return [
    'security' => [
        'mode' => 'strict',
        'force_https' => true,
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
        'debug' => true,
        'log_stacktrace' => true,
        'stacktrace_length' => 25,
        'report_errors' => false,
    ],

    'info' => [
        'salt' => '74cb65703d65ba24755c8b4408f24b28',
        'instance_id' => '647ef046-4016-48d0-a0c4-604e1092e714',
    ],

    'url' => 'fossbilling.dev.ddev.site/',

    'admin_area_prefix' => '/admin',

    'update_branch' => 'preview',

    'maintenance_mode' => [
        'enabled' => false,
        'allowed_urls' => [],
        'allowed_ips' => [],
    ],

    'disable_auto_cron' => false,

    'i18n' => [
        'locale' => 'en_US',
        'auto_detect_locale' => true,
        'timezone' => 'UTC',
        'date_format' => 'medium',
        'time_format' => 'short',
        'datetime_pattern' => '',
    ],

    'path_data' => '/var/www/html/src/data',

    'db' => [
        'driver' => 'pdo_mysql',
        'host' => 'db',
        'port' => 3306,
        'name' => 'db',
        'user' => 'db',
        'password' => 'db',
    ],

    'twig' => [
        'debug' => false,
        'auto_reload' => true,
        'cache' => '/var/www/html/src/data/cache',
        'strict_variables' => true,
    ],

    'api' => [
        'require_referrer_header' => false,
        'allowed_ips' => [],
        'CSRFPrevention' => true,
    ],

    'rate_limiter' => [
        'enabled' => false,
        'whitelist_ips' => [],
        'policies' => [],
    ],

    'log_stacktrace' => true,

    'stacktrace_length' => 25,
];