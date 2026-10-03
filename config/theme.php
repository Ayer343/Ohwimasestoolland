<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Theme Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'appearance'       => 'system',
        'sidebar_theme'    => 'default',
        'font_size'        => 'medium',
        'layout'           => 'comfortable',
        'animations'       => 'enabled',
        'sidebar_position' => 'left',
        'header_style'     => 'default',
        'density'          => 'comfortable',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) generated theme CSS should be cached per user.
    | A value of 0 disables caching.
    |
    */
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | CSS Output
    |--------------------------------------------------------------------------
    */
    'minify_css' => env('THEME_MINIFY_CSS', true),

    /*
    |--------------------------------------------------------------------------
    | Sidebar Theme Names
    |--------------------------------------------------------------------------
    |
    | Names of the built-in sidebar themes plus the reserved 'custom' value.
    | The actual palette definitions live in SidebarThemeRegistry; only the
    | list of names is duplicated here for validation.
    |
    */
    'sidebar_theme_names' => ['default', 'dark', 'light', 'blue', 'green', 'custom'],
];