<?php

return [
    'types' => [
        0 => 'super-admin',
        1 => 'admin',
        2 => 'landlord',
        3 => 'tenant',
        4 => 'field-agent',
        5 => 'developer',
        6 => 'security-personnel',
        7 => 'former-landlord',
    ],
    
    'type_names' => [
        0 => 'Super Admin',
        1 => 'Admin',
        2 => 'Landlord',
        3 => 'Tenant',
        4 => 'Field Agent',
        5 => 'Developer',
        6 => 'Security Personnel',
        7 => 'Former Landlord',
    ],
    
    'statuses' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'inactive' => 'Inactive',
        'verification_required' => 'Verification Required',
        'archived' => 'Archived',
    ],
    
    'status_colors' => [
        'pending' => 'warning',
        'active' => 'success',
        'suspended' => 'danger',
        'inactive' => 'secondary',
        'verification_required' => 'info',
        'archived' => 'dark',
    ],
    
    'supervisor_levels' => [
        0 => 'Security Personnel',
        1 => 'Team Lead',
        2 => 'Section Lead',
        3 => 'Post Commander',
    ],
    
    'phone' => [
        'country_code' => '+233',
        'local_prefix' => '0',
        'digits_length' => 9,
    ],
    
    'photo' => [
        'disk' => 'public',
        'directory' => 'users/photos',
        'thumbnail_width' => 150,
        'thumbnail_height' => 150,
        'max_size' => 5120, // 5MB in KB
    ],
];