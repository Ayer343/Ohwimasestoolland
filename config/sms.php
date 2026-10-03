<?php

// config/sms.php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    | The provider used when none is explicitly specified.
    | Leave NULL when no default is set — the service will pick the first
    | enabled + configured provider as fallback.
    */
    'default' => env('DEFAULT_SMS_PROVIDER', null),

    /*
    |--------------------------------------------------------------------------
    | Global SMS Behavior
    |--------------------------------------------------------------------------
    */
    'test_mode' => env('SMS_TEST_MODE', false),
    'dry_run'   => env('SMS_DRY_RUN', false),
    'enabled'   => env('SMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | SMS Provider Credentials
    |--------------------------------------------------------------------------
    | IMPORTANT: Do NOT set fallback values for sender_id / api_key here.
    | Missing values must return NULL so the admin UI can distinguish
    | "not configured" from "configured with a specific value".
    */
    'providers' => [

        'arkesel' => [
            'enabled'   => env('ARKESEL_SMS_ENABLED', false),
            'api_key'   => env('ARKESEL_SMS_API_KEY'),
            'sender_id' => env('ARKESEL_SMS_SENDER_ID'),   // ✅ no default
            'base_url'  => env('ARKESEL_SMS_BASE_URL', 'https://sms.arkesel.com'),
        ],

        'twilio' => [
            'enabled'     => env('TWILIO_SMS_ENABLED', false),
            'account_sid' => env('TWILIO_ACCOUNT_SID', env('TWILIO_SID')),
            'auth_token'  => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_FROM_NUMBER', env('TWILIO_SMS_FROM')),
        ],

        'africastalking' => [
            'enabled'   => env('AFRICASTALKING_SMS_ENABLED', false),
            'api_key'   => env('AFRICASTALKING_API_KEY'),
            'username'  => env('AFRICASTALKING_USERNAME', 'sandbox'),
            'sender_id' => env('AFRICASTALKING_SENDER_ID'), // ✅ no default
        ],

        'hubtel' => [
            'enabled'       => env('HUBTEL_SMS_ENABLED', false),
            'client_id'     => env('HUBTEL_CLIENT_ID'),
            'client_secret' => env('HUBTEL_CLIENT_SECRET'),
            'sender_id'     => env('HUBTEL_SENDER_ID'),     // ✅ no default
        ],

        'nalosolutions' => [
            'enabled'   => env('NALOSOLUTIONS_SMS_ENABLED', false),
            'api_key'   => env('NALOSOLUTIONS_API_KEY'),
            'sender_id' => env('NALOSOLUTIONS_SMS_SENDER_ID'), // ✅ no default
            'base_url'  => env('NALOSOLUTIONS_SMS_BASE_URL', 'https://sms.nalosolutions.com'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Constraints
    |--------------------------------------------------------------------------
    */
    'max_length'           => 160,
    'timeout_seconds'      => 30,
    'cache_ttl'            => 300,
    'log_channel'          => env('SMS_LOG_CHANNEL', 'stack'),
    'default_country'      => 'GH',
    'default_country_code' => '233',

];