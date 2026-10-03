<?php
// config/mail_developer.php

return [
    /*
    |--------------------------------------------------------------------------
    | Developer Mail Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is for developer-specific email settings.
    | Developers can update these settings through their dashboard.
    | These settings DO NOT affect the main system email configuration.
    |
    */

    'developer' => [
        /*
        |--------------------------------------------------------------------------
        | Default Mailer
        |--------------------------------------------------------------------------
        |
        | This option controls the default mailer that is used to send developer emails.
        |
        */

        'default' => env('DEVELOPER_MAIL_MAILER', 'smtp'),

        /*
        |--------------------------------------------------------------------------
        | Mailer Configurations
        |--------------------------------------------------------------------------
        |
        | Here you may configure all of the mailers used by your application plus
        | their respective settings. Several examples have been configured for
        | you and you are free to add your own as your application requires.
        |
        | Laravel supports a variety of mail "transport" drivers to be used while
        | sending an e-mail. You may specify which one you're using for your
        | mailers below. You may also add additional mailers if needed.
        |
        | Supported: "smtp", "sendmail", "mailgun", "ses", "postmark", "log", "array"
        |
        */

        'mailers' => [
            'smtp' => [
                'transport' => 'smtp',
                'host' => env('DEVELOPER_MAIL_HOST', 'smtp.gmail.com'),
                'port' => env('DEVELOPER_MAIL_PORT', 587),
                'encryption' => env('DEVELOPER_MAIL_ENCRYPTION', 'tls'),
                'username' => env('DEVELOPER_MAIL_USERNAME'),
                'password' => env('DEVELOPER_MAIL_PASSWORD'),
                'timeout' => env('DEVELOPER_MAIL_TIMEOUT', 30),
                'local_domain' => env('DEVELOPER_MAIL_LOCAL_DOMAIN'),
                'auth_mode' => env('DEVELOPER_MAIL_AUTH_MODE'),
                'verify_peer' => env('DEVELOPER_MAIL_VERIFY_PEER', true),
                'verify_peer_name' => env('DEVELOPER_MAIL_VERIFY_PEER_NAME', true),
                'allow_self_signed' => env('DEVELOPER_MAIL_ALLOW_SELF_SIGNED', false),
            ],

            'log' => [
                'transport' => 'log',
                'channel' => env('DEVELOPER_MAIL_LOG_CHANNEL'),
            ],

            'array' => [
                'transport' => 'array',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Global "From" Address
        |--------------------------------------------------------------------------
        |
        | You may wish for all e-mails sent by your application to be sent from
        | the same address. Here, you may specify a name and address that is
        | used globally for all e-mails that are sent by the developer system.
        |
        */

        'from' => [
            'address' => env('DEVELOPER_MAIL_FROM_ADDRESS', 'developer@hilltoprms.com'),
            'name' => env('DEVELOPER_MAIL_FROM_NAME', 'Developer System'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Global "Reply To" Address
        |--------------------------------------------------------------------------
        |
        | You may wish for all e-mails sent by your application to have a default
        | "reply to" address. This address will be used as the default "reply to"
        | address for all e-mails sent by the developer system.
        |
        */

        'reply_to' => [
            'address' => env('DEVELOPER_MAIL_REPLY_TO_ADDRESS', 'developer-support@hilltoprms.com'),
            'name' => env('DEVELOPER_MAIL_REPLY_TO_NAME', 'Developer Support'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Addresses
        |--------------------------------------------------------------------------
        |
        | Specific email addresses for developer system notifications.
        |
        */

        'addresses' => [
            'error' => env('DEVELOPER_ERROR_EMAIL', 'developer-errors@hilltoprms.com'),
            'test' => env('DEVELOPER_TEST_EMAIL', 'test+developer@hilltoprms.com'),
            'alert' => env('DEVELOPER_ALERT_EMAIL', 'developer-alerts@hilltoprms.com'),
            'support' => env('DEVELOPER_SUPPORT_EMAIL', 'developer-support@hilltoprms.com'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Features
        |--------------------------------------------------------------------------
        |
        | Enable/disable specific developer email features.
        |
        */

        'features' => [
            'enable_testing' => env('DEVELOPER_MAIL_ENABLE_TESTING', true),
            'enable_logging' => env('DEVELOPER_MAIL_ENABLE_LOGGING', true),
            'enable_queue' => env('DEVELOPER_MAIL_ENABLE_QUEUE', true),
            'enable_bulk' => env('DEVELOPER_MAIL_ENABLE_BULK', true),
            'enable_monitoring' => env('DEVELOPER_MAIL_ENABLE_MONITORING', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Queue Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for developer email queue system.
        |
        */

        'queue' => [
            'enabled' => env('DEVELOPER_MAIL_QUEUE_ENABLED', true),
            'connection' => env('DEVELOPER_MAIL_QUEUE_CONNECTION', 'database'),
            'name' => env('DEVELOPER_MAIL_QUEUE_NAME', 'developer_emails'),
            'delay' => env('DEVELOPER_MAIL_QUEUE_DELAY', 0),
            'max_tries' => env('DEVELOPER_MAIL_QUEUE_MAX_TRIES', 3),
            'retry_after' => env('DEVELOPER_MAIL_QUEUE_RETRY_AFTER', 90),
            'backoff' => env('DEVELOPER_MAIL_QUEUE_BACKOFF', 60),
            'timeout' => env('DEVELOPER_MAIL_QUEUE_TIMEOUT', 60),
            'memory_limit' => env('DEVELOPER_MAIL_QUEUE_MEMORY_LIMIT', 128),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Rate Limiting
        |--------------------------------------------------------------------------
        |
        | Rate limiting for developer email sending.
        |
        */

        'rate_limits' => [
            'enabled' => env('DEVELOPER_MAIL_RATE_LIMIT_ENABLED', true),
            'per_hour' => env('DEVELOPER_MAIL_RATE_LIMIT_PER_HOUR', 100),
            'per_day' => env('DEVELOPER_MAIL_RATE_LIMIT_PER_DAY', 1000),
            'bulk_limit' => env('DEVELOPER_MAIL_BULK_LIMIT', 50),
            'daily_limit' => env('DEVELOPER_MAIL_DAILY_LIMIT', 100),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Testing
        |--------------------------------------------------------------------------
        |
        | Configuration for developer email testing.
        |
        */

        'testing' => [
            'mode' => env('DEVELOPER_MAIL_TEST_MODE', false),
            'dry_run' => env('DEVELOPER_MAIL_DRY_RUN', false),
            'log_messages' => env('DEVELOPER_MAIL_LOG_MESSAGES', true),
            'debug_mode' => env('DEVELOPER_MAIL_DEBUG', true),
            'debug_output' => env('DEVELOPER_MAIL_DEBUG_OUTPUT', 'html'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Templates
        |--------------------------------------------------------------------------
        |
        | Configuration for developer email templates.
        |
        */

        'templates' => [
            'cache' => env('DEVELOPER_MAIL_TEMPLATE_CACHE', true),
            'cache_duration' => env('DEVELOPER_MAIL_TEMPLATE_CACHE_DURATION', 3600),
            'default' => env('DEVELOPER_MAIL_DEFAULT_TEMPLATE', 'developer::emails.default'),
            'error' => env('DEVELOPER_MAIL_ERROR_TEMPLATE', 'developer::emails.error'),
            'test' => env('DEVELOPER_MAIL_TEST_TEMPLATE', 'developer::emails.test'),
            'notification' => env('DEVELOPER_MAIL_NOTIFICATION_TEMPLATE', 'developer::emails.notification'),
            'report' => env('DEVELOPER_MAIL_REPORT_TEMPLATE', 'developer::emails.report'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Monitoring
        |--------------------------------------------------------------------------
        |
        | Configuration for developer email monitoring and analytics.
        |
        */

        'monitoring' => [
            'enabled' => env('DEVELOPER_MAIL_MONITORING_ENABLED', true),
            'track_opens' => env('DEVELOPER_MAIL_TRACK_OPENS', true),
            'track_clicks' => env('DEVELOPER_MAIL_TRACK_CLICKS', true),
            'track_deliveries' => env('DEVELOPER_MAIL_TRACK_DELIVERIES', true),
            'analytics_enabled' => env('DEVELOPER_MAIL_ANALYTICS_ENABLED', true),
            'analytics_retention_days' => env('DEVELOPER_MAIL_ANALYTICS_RETENTION_DAYS', 90),
            'performance_monitoring' => env('DEVELOPER_MAIL_PERFORMANCE_MONITORING', true),
            'bounce_handling' => env('DEVELOPER_MAIL_BOUNCE_HANDLING', true),
            'max_bounce_count' => env('DEVELOPER_MAIL_MAX_BOUNCE_COUNT', 3),
            'bounce_notification_email' => env('DEVELOPER_MAIL_BOUNCE_NOTIFICATION_EMAIL', 'developer-bounces@hilltoprms.com'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Security
        |--------------------------------------------------------------------------
        |
        | Security settings for developer emails.
        |
        */

        'security' => [
            'sign_dkim' => env('DEVELOPER_MAIL_SIGN_DKIM', false),
            'dkim_private_key' => env('DEVELOPER_MAIL_DKIM_PRIVATE_KEY', ''),
            'dkim_selector' => env('DEVELOPER_MAIL_DKIM_SELECTOR', 'default'),
            'dkim_domain' => env('DEVELOPER_MAIL_DKIM_DOMAIN'),
            'enable_spf' => env('DEVELOPER_MAIL_ENABLE_SPF', true),
            'enable_dmarc' => env('DEVELOPER_MAIL_ENABLE_DMARC', true),
            'validate_certificates' => env('DEVELOPER_MAIL_VALIDATE_CERTIFICATES', true),
            'require_tls' => env('DEVELOPER_MAIL_REQUIRE_TLS', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Compliance
        |--------------------------------------------------------------------------
        |
        | Compliance settings for developer emails.
        |
        */

        'compliance' => [
            'gdpr' => env('DEVELOPER_MAIL_COMPLIANCE_GDPR', true),
            'can_spam' => env('DEVELOPER_MAIL_COMPLIANCE_CAN_SPAM', true),
            'require_unsubscribe' => env('DEVELOPER_MAIL_REQUIRE_UNSUBSCRIBE', true),
            'unsubscribe_address' => env('DEVELOPER_MAIL_UNSUBSCRIBE_ADDRESS', 'developer-unsubscribe@hilltoprms.com'),
            'privacy_policy_url' => env('DEVELOPER_MAIL_PRIVACY_POLICY_URL'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Backup Configuration
        |--------------------------------------------------------------------------
        |
        | Backup SMTP configuration for failover.
        |
        */

        'backup' => [
            'enabled' => env('DEVELOPER_MAIL_BACKUP_ENABLED', true),
            'host' => env('DEVELOPER_MAIL_BACKUP_HOST'),
            'port' => env('DEVELOPER_MAIL_BACKUP_PORT', 587),
            'username' => env('DEVELOPER_MAIL_BACKUP_USERNAME'),
            'password' => env('DEVELOPER_MAIL_BACKUP_PASSWORD'),
            'encryption' => env('DEVELOPER_MAIL_BACKUP_ENCRYPTION', 'tls'),
            'failover_threshold' => env('DEVELOPER_MAIL_FAILOVER_THRESHOLD', 3),
            'failover_timeout' => env('DEVELOPER_MAIL_FAILOVER_TIMEOUT', 30),
            'auto_switch_back' => env('DEVELOPER_MAIL_AUTO_SWITCH_BACK', true),
            'switch_back_delay' => env('DEVELOPER_MAIL_SWITCH_BACK_DELAY', 300),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Template Engine
        |--------------------------------------------------------------------------
        |
        | Template engine configuration for developer emails.
        |
        */

        'template_engine' => [
            'engine' => env('DEVELOPER_MAIL_TEMPLATE_ENGINE', 'blade'),
            'cache_enabled' => env('DEVELOPER_MAIL_TEMPLATE_CACHE_ENABLED', true),
            'cache_path' => env('DEVELOPER_MAIL_TEMPLATE_CACHE_PATH', 'storage/framework/views/developer'),
            'auto_reload' => env('DEVELOPER_MAIL_TEMPLATE_AUTO_RELOAD', true),
            'strict_variables' => env('DEVELOPER_MAIL_TEMPLATE_STRICT_VARIABLES', false),
            'default_variables' => explode(',', env('DEVELOPER_MAIL_DEFAULT_VARIABLES', 'system_name,developer_email,current_year,app_url')),
            'allowed_tags' => explode(',', env('DEVELOPER_MAIL_ALLOWED_TAGS', 'b,i,u,strong,em,a,p,br,ul,ol,li,h1,h2,h3,h4,div,span,table,tr,td,th,thead,tbody')),
            'strip_unsafe_tags' => env('DEVELOPER_MAIL_STRIP_UNSAFE_TAGS', true),
            'escape_html' => env('DEVELOPER_MAIL_ESCAPE_HTML', true),
            'fallback_enabled' => env('DEVELOPER_MAIL_TEMPLATE_FALLBACK_ENABLED', true),
            'default_fallback' => env('DEVELOPER_MAIL_DEFAULT_TEMPLATE_FALLBACK', 'emails.default'),
            'error_fallback' => env('DEVELOPER_MAIL_ERROR_TEMPLATE_FALLBACK', 'emails.error'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Custom Headers
        |--------------------------------------------------------------------------
        |
        | Custom headers to include in developer emails.
        |
        */

        'headers' => [
            'X-Developer-System' => env('DEVELOPER_MAIL_HEADER_SYSTEM', 'Hilltop Developer System'),
            'X-Developer-Version' => env('DEVELOPER_MAIL_HEADER_VERSION', '1.0.0'),
            'X-Mailer' => env('DEVELOPER_MAIL_HEADER_MAILER', 'Developer Mail System'),
            'X-Priority' => env('DEVELOPER_MAIL_HEADER_PRIORITY', '3'),
            'X-Mailer-Type' => env('DEVELOPER_MAIL_HEADER_TYPE', 'developer'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Cache Configuration
        |--------------------------------------------------------------------------
        |
        | Cache settings for developer email configuration.
        |
        */

        'cache' => [
            'enabled' => env('DEVELOPER_MAIL_CACHE_ENABLED', true),
            'duration' => env('DEVELOPER_MAIL_CACHE_DURATION', 3600),
            'driver' => env('DEVELOPER_MAIL_CACHE_DRIVER', 'file'),
            'prefix' => env('DEVELOPER_MAIL_CACHE_PREFIX', 'developer_mail_'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Logging
        |--------------------------------------------------------------------------
        |
        | Logging configuration for developer emails.
        |
        */

        'logging' => [
            'enabled' => env('DEVELOPER_MAIL_LOGGING_ENABLED', true),
            'channel' => env('DEVELOPER_MAIL_LOG_CHANNEL', 'developer_mail'),
            'level' => env('DEVELOPER_MAIL_LOG_LEVEL', 'debug'),
            'log_failures' => env('DEVELOPER_MAIL_LOG_FAILURES', true),
            'log_successes' => env('DEVELOPER_MAIL_LOG_SUCCESSES', false),
            'log_queue' => env('DEVELOPER_MAIL_LOG_QUEUE', true),
            'retention_days' => env('DEVELOPER_MAIL_LOG_RETENTION_DAYS', 30),
        ],

        /*
        |--------------------------------------------------------------------------
        | Developer Email Error Handling
        |--------------------------------------------------------------------------
        |
        | Error handling configuration for developer emails.
        |
        */

        'error_handling' => [
            'notify_on_error' => env('DEVELOPER_MAIL_NOTIFY_ON_ERROR', true),
            'error_notification_email' => env('DEVELOPER_MAIL_ERROR_NOTIFICATION_EMAIL', 'developer-errors@hilltoprms.com'),
            'retry_on_failure' => env('DEVELOPER_MAIL_RETRY_ON_FAILURE', true),
            'max_retry_attempts' => env('DEVELOPER_MAIL_MAX_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('DEVELOPER_MAIL_RETRY_DELAY', 5),
            'throw_exceptions' => env('DEVELOPER_MAIL_THROW_EXCEPTIONS', false),
        ],
    ],
];