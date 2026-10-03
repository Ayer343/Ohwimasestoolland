<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ✅ Google Maps API Configuration (canonical, used by PropertyController
    |    and FieldAgentPropertyController + coordinate picker blades)
    |--------------------------------------------------------------------------
    |
    | The browsers (admin & field-agent property create/edit blades) read
    | `services.google_maps.browser_key` with a fallback to
    | `services.google_maps.key`. The server-side geocoder in
    | GoogleMapsService reads `services.google_maps.server_key` with a
    | fallback to `services.google_maps.key`.
    |
    | Best practice: use a DOMAIN-RESTRICTED key for the browser (safe to
    | expose in HTML) and a separate IP/API-restricted key for the server
    | (never exposed to the client).
    |
    */
    'google_maps' => [
        // ----------------------------------------------------------------
        // Primary keys
        // ----------------------------------------------------------------
        'key'         => env('GOOGLE_MAPS_API_KEY'),
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY', env('GOOGLE_MAPS_API_KEY')),
        'server_key'  => env('GOOGLE_MAPS_SERVER_KEY',  env('GOOGLE_MAPS_API_KEY')),

        // ----------------------------------------------------------------
        // Per-API key overrides (each falls back to the primary key)
        // ----------------------------------------------------------------
        'maps_api_key'            => env('GOOGLE_MAPS_API_KEY'),
        'places_api_key'          => env('GOOGLE_PLACES_API_KEY',          env('GOOGLE_MAPS_API_KEY')),
        'geocoding_api_key'       => env('GOOGLE_GEOCODING_API_KEY',       env('GOOGLE_MAPS_API_KEY')),
        'directions_api_key'      => env('GOOGLE_DIRECTIONS_API_KEY',      env('GOOGLE_MAPS_API_KEY')),
        'distance_matrix_api_key' => env('GOOGLE_DISTANCE_MATRIX_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'elevation_api_key'       => env('GOOGLE_ELEVATION_API_KEY',       env('GOOGLE_MAPS_API_KEY')),
        'roads_api_key'           => env('GOOGLE_ROADS_API_KEY',           env('GOOGLE_MAPS_API_KEY')),
        'timezone_api_key'        => env('GOOGLE_TIMEZONE_API_KEY',        env('GOOGLE_MAPS_API_KEY')),

        // ----------------------------------------------------------------
        // Default map behaviour
        // ----------------------------------------------------------------
        'maps' => [
            'default_center' => [
                'lat' => env('GOOGLE_MAPS_DEFAULT_LAT', 5.6037),
                'lng' => env('GOOGLE_MAPS_DEFAULT_LNG', -0.1870),
            ],
            'default_zoom'    => env('GOOGLE_MAPS_DEFAULT_ZOOM', 14),
            'max_zoom'        => env('GOOGLE_MAPS_MAX_ZOOM', 20),
            'min_zoom'        => env('GOOGLE_MAPS_MIN_ZOOM', 3),
            'default_country' => env('GOOGLE_MAPS_DEFAULT_COUNTRY', 'GH'),
            'default_region'  => env('GOOGLE_MAPS_DEFAULT_REGION', 'GH'),
        ],

        // ----------------------------------------------------------------
        // Geocoding service defaults
        // ----------------------------------------------------------------
        'geocoding' => [
            'cache_ttl'  => env('GOOGLE_GEOCODING_CACHE_TTL', 86400),
            'batch_size' => env('GOOGLE_GEOCODING_BATCH_SIZE', 100),
            'rate_limit' => env('GOOGLE_GEOCODING_RATE_LIMIT', 50),

            // Ghana Post GPS integration (optional)
            'ghana_post_url'    => env('GHANA_POST_GPS_URL', 'https://api.ghanapostgps.com/v1/address'),
            'ghana_post_api_key'=> env('GHANA_POST_GPS_API_KEY'),
            'ghana_post_enabled'=> env('GHANA_POST_GPS_ENABLED', false),
        ],

        // ----------------------------------------------------------------
        // Directions service defaults
        // ----------------------------------------------------------------
        'directions' => [
            'cache_ttl'     => env('GOOGLE_DIRECTIONS_CACHE_TTL', 3600),
            'max_waypoints' => env('GOOGLE_DIRECTIONS_MAX_WAYPOINTS', 23),
            'mode'          => env('GOOGLE_DIRECTIONS_MODE', 'driving'),
            'avoid'         => env('GOOGLE_DIRECTIONS_AVOID'),
        ],

        // ----------------------------------------------------------------
        // Places service defaults
        // ----------------------------------------------------------------
        'places' => [
            'cache_ttl'      => env('GOOGLE_PLACES_CACHE_TTL', 86400),
            'max_results'    => env('GOOGLE_PLACES_MAX_RESULTS', 20),
            'default_radius' => env('GOOGLE_PLACES_DEFAULT_RADIUS', 1000),
        ],

        // ----------------------------------------------------------------
        // Static map defaults (used for PDF exports, emails, etc.)
        // ----------------------------------------------------------------
        'static_map' => [
            'width'  => env('GOOGLE_STATIC_MAP_WIDTH', 600),
            'height' => env('GOOGLE_STATIC_MAP_HEIGHT', 400),
            'scale'  => env('GOOGLE_STATIC_MAP_SCALE', 2),
            'format' => env('GOOGLE_STATIC_MAP_FORMAT', 'png'),
        ],

        // ----------------------------------------------------------------
        // Distance matrix defaults
        // ----------------------------------------------------------------
        'distance_matrix' => [
            'cache_ttl'                  => env('GOOGLE_DISTANCE_MATRIX_CACHE_TTL', 3600),
            'max_elements'               => env('GOOGLE_DISTANCE_MATRIX_MAX_ELEMENTS', 100),
            'traffic_model'              => env('GOOGLE_DISTANCE_MATRIX_TRAFFIC_MODEL', 'best_guess'),
            'transit_mode'               => env('GOOGLE_DISTANCE_MATRIX_TRANSIT_MODE'),
            'transit_routing_preference' => env('GOOGLE_DISTANCE_MATRIX_TRANSIT_PREFERENCE'),
        ],

        // ----------------------------------------------------------------
        // Elevation defaults
        // ----------------------------------------------------------------
        'elevation' => [
            'cache_ttl'        => env('GOOGLE_ELEVATION_CACHE_TTL', 86400),
            'max_locations'    => env('GOOGLE_ELEVATION_MAX_LOCATIONS', 512),
            'default_sampling' => env('GOOGLE_ELEVATION_DEFAULT_SAMPLING', 100),
        ],

        // ----------------------------------------------------------------
        // Roads service defaults
        // ----------------------------------------------------------------
        'roads' => [
            'cache_ttl'      => env('GOOGLE_ROADS_CACHE_TTL', 3600),
            'max_points'     => env('GOOGLE_ROADS_MAX_POINTS', 100),
            'snap_tolerance' => env('GOOGLE_ROADS_SNAP_TOLERANCE', 0.5),
        ],

        // ----------------------------------------------------------------
        // Timezone service defaults
        // ----------------------------------------------------------------
        'timezone' => [
            'cache_ttl' => env('GOOGLE_TIMEZONE_CACHE_TTL', 86400),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth Configuration (+ legacy maps mirror)
    |--------------------------------------------------------------------------
    |
    | The `google` block is preserved so any older code that reads
    | `services.google.maps_api_key` etc. continues to work. It also
    | mirrors the browser/server keys so either path resolves.
    |
    */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),

        // ============================================================== //
        // ✅ Google Maps API Configuration (mirror of `google_maps`)      //
        // ============================================================== //
        'key'         => env('GOOGLE_MAPS_API_KEY'),
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY', env('GOOGLE_MAPS_API_KEY')),
        'server_key'  => env('GOOGLE_MAPS_SERVER_KEY',  env('GOOGLE_MAPS_API_KEY')),

        'maps_api_key'            => env('GOOGLE_MAPS_API_KEY'),
        'places_api_key'          => env('GOOGLE_PLACES_API_KEY',          env('GOOGLE_MAPS_API_KEY')),
        'geocoding_api_key'       => env('GOOGLE_GEOCODING_API_KEY',       env('GOOGLE_MAPS_API_KEY')),
        'directions_api_key'      => env('GOOGLE_DIRECTIONS_API_KEY',      env('GOOGLE_MAPS_API_KEY')),
        'distance_matrix_api_key' => env('GOOGLE_DISTANCE_MATRIX_API_KEY', env('GOOGLE_MAPS_API_KEY')),
        'elevation_api_key'       => env('GOOGLE_ELEVATION_API_KEY',       env('GOOGLE_MAPS_API_KEY')),
        'roads_api_key'           => env('GOOGLE_ROADS_API_KEY',           env('GOOGLE_MAPS_API_KEY')),
        'timezone_api_key'        => env('GOOGLE_TIMEZONE_API_KEY',        env('GOOGLE_MAPS_API_KEY')),

        'maps' => [
            'default_center' => [
                'lat' => env('GOOGLE_MAPS_DEFAULT_LAT', 5.6037),
                'lng' => env('GOOGLE_MAPS_DEFAULT_LNG', -0.1870),
            ],
            'default_zoom'    => env('GOOGLE_MAPS_DEFAULT_ZOOM', 14),
            'max_zoom'        => env('GOOGLE_MAPS_MAX_ZOOM', 20),
            'min_zoom'        => env('GOOGLE_MAPS_MIN_ZOOM', 3),
            'default_country' => env('GOOGLE_MAPS_DEFAULT_COUNTRY', 'GH'),
            'default_region'  => env('GOOGLE_MAPS_DEFAULT_REGION', 'GH'),
        ],

        'geocoding' => [
            'cache_ttl'  => env('GOOGLE_GEOCODING_CACHE_TTL', 86400),
            'batch_size' => env('GOOGLE_GEOCODING_BATCH_SIZE', 100),
            'rate_limit' => env('GOOGLE_GEOCODING_RATE_LIMIT', 50),
        ],

        'directions' => [
            'cache_ttl'     => env('GOOGLE_DIRECTIONS_CACHE_TTL', 3600),
            'max_waypoints' => env('GOOGLE_DIRECTIONS_MAX_WAYPOINTS', 23),
            'mode'          => env('GOOGLE_DIRECTIONS_MODE', 'driving'),
            'avoid'         => env('GOOGLE_DIRECTIONS_AVOID'),
        ],

        'places' => [
            'cache_ttl'      => env('GOOGLE_PLACES_CACHE_TTL', 86400),
            'max_results'    => env('GOOGLE_PLACES_MAX_RESULTS', 20),
            'default_radius' => env('GOOGLE_PLACES_DEFAULT_RADIUS', 1000),
        ],

        'static_map' => [
            'width'  => env('GOOGLE_STATIC_MAP_WIDTH', 600),
            'height' => env('GOOGLE_STATIC_MAP_HEIGHT', 400),
            'scale'  => env('GOOGLE_STATIC_MAP_SCALE', 2),
            'format' => env('GOOGLE_STATIC_MAP_FORMAT', 'png'),
        ],

        'distance_matrix' => [
            'cache_ttl'                  => env('GOOGLE_DISTANCE_MATRIX_CACHE_TTL', 3600),
            'max_elements'               => env('GOOGLE_DISTANCE_MATRIX_MAX_ELEMENTS', 100),
            'traffic_model'              => env('GOOGLE_DISTANCE_MATRIX_TRAFFIC_MODEL', 'best_guess'),
            'transit_mode'               => env('GOOGLE_DISTANCE_MATRIX_TRANSIT_MODE'),
            'transit_routing_preference' => env('GOOGLE_DISTANCE_MATRIX_TRANSIT_PREFERENCE'),
        ],

        'elevation' => [
            'cache_ttl'        => env('GOOGLE_ELEVATION_CACHE_TTL', 86400),
            'max_locations'    => env('GOOGLE_ELEVATION_MAX_LOCATIONS', 512),
            'default_sampling' => env('GOOGLE_ELEVATION_DEFAULT_SAMPLING', 100),
        ],

        'roads' => [
            'cache_ttl'      => env('GOOGLE_ROADS_CACHE_TTL', 3600),
            'max_points'     => env('GOOGLE_ROADS_MAX_POINTS', 100),
            'snap_tolerance' => env('GOOGLE_ROADS_SNAP_TOLERANCE', 0.5),
        ],

        'timezone' => [
            'cache_ttl' => env('GOOGLE_TIMEZONE_CACHE_TTL', 86400),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'microsoft' => [
        'client_id'     => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect'      => env('MICROSOFT_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twitter OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'twitter' => [
        'client_id'     => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect'      => env('TWITTER_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect'      => env('GITHUB_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Payment Configuration
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'key'     => env('STRIPE_KEY'),
        'secret'  => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal Payment Configuration
    |--------------------------------------------------------------------------
    */
    'paypal' => [
        'client_id'     => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode'          => env('PAYPAL_MODE', 'sandbox'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack Payment Configuration
    |--------------------------------------------------------------------------
    */
    'paystack' => [
        'secret_key'      => env('PAYSTACK_SECRET_KEY'),
        'public_key'      => env('PAYSTACK_PUBLIC_KEY'),
        'merchant_id'     => env('PAYSTACK_MERCHANT_ID'),
        'webhook_secret'  => env('PAYSTACK_WEBHOOK_SECRET'),
        'base_url'        => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'enabled'         => env('PAYSTACK_ENABLED', true),
        'webhook_url'     => env('PAYSTACK_WEBHOOK_URL'),

        'payment_methods' => [
            'card'           => env('PAYSTACK_ALLOW_CARD', true),
            'mtn_momo'       => env('PAYSTACK_ALLOW_MTN_MOMO', true),
            'telecel_cash'   => env('PAYSTACK_ALLOW_TELECEL_CASH', true),
            'airteltigo'     => env('PAYSTACK_ALLOW_AIRTELTIGO', true),
            'bank_transfer'  => env('PAYSTACK_ALLOW_BANK_TRANSFER', true),
            'qr'             => env('PAYSTACK_ALLOW_QR', true),
            'ussd'           => env('PAYSTACK_ALLOW_USSD', true),
            'bank_account'   => env('PAYSTACK_ALLOW_BANK_ACCOUNT', true),
        ],

        'fee' => [
            'percentage'     => env('PAYSTACK_FEE_PERCENTAGE', 1.95),
            'flat'           => env('PAYSTACK_FEE_FLAT', 0.50),
            'minimum_amount' => env('PAYSTACK_MINIMUM_AMOUNT', 1.00),
            'maximum_amount' => env('PAYSTACK_MAXIMUM_AMOUNT', 100000.00),
        ],

        'settlement' => [
            'days'           => env('PAYSTACK_SETTLEMENT_DAYS', 1),
            'minimum_payout' => env('PAYSTACK_MINIMUM_PAYOUT', 50.00),
        ],

        'test' => [
            'mode'             => env('PAYSTACK_TEST_MODE', true),
            'dry_run'          => env('PAYSTACK_DRY_RUN', false),
            'log_transactions' => env('PAYSTACK_LOG_TRANSACTIONS', true),
        ],

        'security' => [
            'verify_ssl'     => env('PAYSTACK_VERIFY_SSL', true),
            'timeout'        => env('PAYSTACK_TIMEOUT', 30),
            'retry_attempts' => env('PAYSTACK_RETRY_ATTEMPTS', 3),
            'retry_delay'    => env('PAYSTACK_RETRY_DELAY', 2),
            'encrypt_data'   => env('PAYSTACK_ENCRYPT_DATA', true),
        ],

        'webhook_events' => [
            'charge_success'        => env('PAYSTACK_WEBHOOK_CHARGE_SUCCESS', true),
            'transfer_success'      => env('PAYSTACK_WEBHOOK_TRANSFER_SUCCESS', true),
            'subscription_create'   => env('PAYSTACK_WEBHOOK_SUBSCRIPTION_CREATE', true),
            'subscription_disable'  => env('PAYSTACK_WEBHOOK_SUBSCRIPTION_DISABLE', true),
            'invoice_create'        => env('PAYSTACK_WEBHOOK_INVOICE_CREATE', true),
            'invoice_update'        => env('PAYSTACK_WEBHOOK_INVOICE_UPDATE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio SMS Configuration
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'sid'           => env('TWILIO_SID'),
        'token'         => env('TWILIO_TOKEN'),
        'from'          => env('TWILIO_FROM'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'sms' => [
            'enabled'        => env('TWILIO_SMS_ENABLED', true),
            'retry_attempts' => env('TWILIO_SMS_RETRY_ATTEMPTS', 3),
            'retry_delay'    => env('TWILIO_SMS_RETRY_DELAY', 60),
        ],
        'whatsapp' => [
            'enabled' => env('TWILIO_WHATSAPP_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vonage (Nexmo) SMS Configuration
    |--------------------------------------------------------------------------
    */
    'vonage' => [
        'key'    => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'from'   => env('VONAGE_FROM'),
        'sms' => [
            'enabled' => env('VONAGE_SMS_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Africa's Talking SMS Configuration
    |--------------------------------------------------------------------------
    */
    'africas_talking' => [
        'username' => env('AFRICAS_TALKING_USERNAME'),
        'api_key'  => env('AFRICAS_TALKING_API_KEY'),
        'from'     => env('AFRICAS_TALKING_FROM'),
        'sms' => [
            'enabled' => env('AFRICAS_TALKING_SMS_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SendGrid Email Configuration
    |--------------------------------------------------------------------------
    */
    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
        'from'    => [
            'email' => env('MAIL_FROM_ADDRESS'),
            'name'  => env('MAIL_FROM_NAME'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mailgun Email Configuration
    |--------------------------------------------------------------------------
    */
    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pusher WebSocket Configuration
    |--------------------------------------------------------------------------
    */
    'pusher' => [
        'key'    => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster'   => env('PUSHER_APP_CLUSTER'),
            'encrypted' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Image Configuration
    |--------------------------------------------------------------------------
    */
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'secure'     => env('CLOUDINARY_SECURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging Configuration
    |--------------------------------------------------------------------------
    */
    'firebase' => [
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OneSignal Push Notification Configuration
    |--------------------------------------------------------------------------
    */
    'onesignal' => [
        'app_id'        => env('ONESIGNAL_APP_ID'),
        'rest_api_key'  => env('ONESIGNAL_REST_API_KEY'),
        'user_auth_key' => env('ONESIGNAL_USER_AUTH_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenStreetMap Configuration (Free Alternative)
    |--------------------------------------------------------------------------
    */
    'openstreetmap' => [
        'enabled'       => env('OPENSTREETMAP_ENABLED', false),
        'nominatim_url' => env('OPENSTREETMAP_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent'    => env('OPENSTREETMAP_USER_AGENT', 'YourAppName/1.0'),
        'rate_limit'    => env('OPENSTREETMAP_RATE_LIMIT', 1),
    ],
];