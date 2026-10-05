<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Provider Registry
    |--------------------------------------------------------------------------
    |
    | Single source of truth for all payment gateways. Every controller,
    | service, and view should iterate this array rather than hardcoding
    | provider lists.
    |
    */

    'providers' => [
        'expresspay' => [
            'name'         => 'ExpressPay',
            'icon'         => 'fa-credit-card',
            'color'        => '#0066CC',
            'description'  => 'Mobile money & online payments',
            'setting_field'=> 'enable_expresspay',
            'env_enabled'  => 'EXPRESSPAY_ENABLED',
            'sort'         => 10,
        ],
        'hubtel' => [
            'name'         => 'Hubtel',
            'icon'         => 'fa-phone-alt',
            'color'        => '#2563EB',
            'description'  => 'Mobile money collections',
            'setting_field'=> 'enable_hubtel',
            'env_enabled'  => 'HUBTEL_ENABLED',
            'sort'         => 20,
        ],
        'paystack' => [
            'name'         => 'Paystack',
            'icon'         => 'fa-credit-card',
            'color'        => '#3B82F6',
            'description'  => 'Cards, bank transfers & mobile money',
            'setting_field'=> 'enable_paystack',
            'env_enabled'  => 'PAYSTACK_ENABLED',
            'sort'         => 30,
        ],
        'flutterwave' => [
            'name'         => 'Flutterwave',
            'icon'         => 'fa-cloud-upload-alt',
            'color'        => '#F97316',
            'description'  => 'Pan-African payment gateway',
            'setting_field'=> 'enable_flutterwave',
            'env_enabled'  => 'FLUTTERWAVE_ENABLED',
            'sort'         => 40,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Fallbacks
    |--------------------------------------------------------------------------
    */
    'fallback' => [
        'name'        => 'Payment Gateway',
        'icon'        => 'fa-credit-card',
        'color'       => '#6B7280',
        'description' => 'Payment gateway',
    ],
];