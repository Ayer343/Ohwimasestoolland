<?php

return [
    'mobile_money' => [
        'verification' => [
            'timeout' => 300, // 5 minutes
            'attempts' => 3,
            'code_length' => 6,
        ],
        'providers' => [
            'mtn' => [
                'ussd_code' => '*170#',
                'customer_service' => '0244300000',
            ],
            'vodafone' => [
                'ussd_code' => '*110#',
                'customer_service' => '0202000000',
            ],
            'airteltigo' => [
                'ussd_code' => '*110#',
                'customer_service' => '0272000000',
            ],
        ],
    ],
    
    'bank_transfer' => [
        'confirmation_timeout' => 1440, // 24 hours
        'required_reference' => true,
    ],
    
    'payment_timeout' => 72, // hours until payment expires
];