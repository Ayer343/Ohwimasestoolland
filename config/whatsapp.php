<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "twilio", "vonage", "custom", "none"
    |
    */
    'provider' => env('WHATSAPP_PROVIDER', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    */
    'twilio_sid' => env('TWILIO_SID'),
    'twilio_token' => env('TWILIO_AUTH_TOKEN'),
    'twilio_whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),

    /*
    |--------------------------------------------------------------------------
    | Vonage Configuration
    |--------------------------------------------------------------------------
    */
    'vonage_key' => env('VONAGE_KEY'),
    'vonage_secret' => env('VONAGE_SECRET'),
    'vonage_whatsapp_from' => env('VONAGE_WHATSAPP_FROM'),

    /*
    |--------------------------------------------------------------------------
    | Custom API Configuration
    |--------------------------------------------------------------------------
    */
    'whatsapp_api_url' => env('WHATSAPP_API_URL'),
    'whatsapp_api_key' => env('WHATSAPP_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Test Configuration
    |--------------------------------------------------------------------------
    */
    'test_number' => env('WHATSAPP_TEST_NUMBER', '+233000000000'),
];