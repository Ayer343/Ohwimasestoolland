<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        /*
        |------------------------------------------------------------------
        | Audit Log
        |------------------------------------------------------------------
        |
        | Dedicated channel for security-sensitive business events:
        |
        |   - Property family link lifecycle
        |       proposed / approved / rejected / revoked
        |   - Family link access denials (middleware)
        |   - User permanent deletion (PII scrub)
        |   - Role changes on landlord accounts
        |   - Ownership transfers
        |
        | Kept on its own daily file with a long retention window so it
        | can be retained for compliance without bloating the main
        | laravel.log. Set LOG_AUDIT_DAYS in .env to override.
        |
        */
        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => env('LOG_AUDIT_LEVEL', 'info'),
            'days' => env('LOG_AUDIT_DAYS', 365),
            'replace_placeholders' => true,
        ],

        /*
        |------------------------------------------------------------------
        | Security Log
        |------------------------------------------------------------------
        |
        | Captures authentication and authorization events that warrant
        | monitoring: failed logins, 403 denials on protected routes,
        | suspicious rate-limit hits, and family-link access denials.
        |
        | Kept separate from `audit` because the volume and consumers
        | are different — this channel is designed to be piped to a SIEM
        | or security dashboard rather than read by humans.
        |
        */
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => env('LOG_SECURITY_LEVEL', 'notice'),
            'days' => env('LOG_SECURITY_DAYS', 180),
            'replace_placeholders' => true,
        ],

        /*
        |------------------------------------------------------------------
        | Notifications Log
        |------------------------------------------------------------------
        |
        | Records outbound notification deliveries (email / SMS /
        | WhatsApp) for the family-link workflow and the wider
        | multi-channel invitation system.
        |
        | Useful when debugging "the admin never received the email"
        | or "the linked user didn't get the welcome SMS" support
        | tickets. Each entry includes channel, provider, and message
        | ID when available.
        |
        */
        'notifications' => [
            'driver' => 'daily',
            'path' => storage_path('logs/notifications.log'),
            'level' => env('LOG_NOTIFICATIONS_LEVEL', 'info'),
            'days' => env('LOG_NOTIFICATIONS_DAYS', 90),
            'replace_placeholders' => true,
        ],

    ],

];