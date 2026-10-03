<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'documents' => [
            'driver' => 'local',
            'root' => storage_path('app/documents'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'maintenance' => [
            'driver' => 'local',
            'root' => storage_path('app/maintenance'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'signatures' => [
            'driver' => 'local',
            'root' => storage_path('app/signatures'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'reports' => [
            'driver' => 'local',
            'root' => storage_path('app/reports'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'exports' => [
            'driver' => 'local',
            'root' => storage_path('app/exports'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'invoices' => [
            'driver' => 'local',
            'root' => storage_path('app/invoices'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        // Optional: Link maintenance images if needed
        // public_path('maintenance') => storage_path('app/maintenance'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Custom configuration for file uploads in the property management system
    |
    */

    'max_upload_size' => env('MAX_UPLOAD_SIZE', 5120), // 5MB in KB

    'allowed_mimes' => [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
        'both' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    ],

    'paths' => [
        'tenant_documents' => 'tenant_documents',
        'pending_tenants' => 'pending_tenants',
        'maintenance_photos' => 'maintenance_requests',
        'lease_attachments' => 'lease_attachments',
        'property_images' => 'property_images',
        'unit_images' => 'unit_images',
        'signatures' => 'signatures',
        'exports' => 'exports',
        'reports' => 'reports',
        'invoices' => 'invoices',
    ],

];