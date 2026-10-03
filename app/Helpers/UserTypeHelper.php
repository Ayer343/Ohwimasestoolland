<?php

namespace App\Helpers;

class UserTypeHelper
{
    public static function getTypes(): array
    {
        return config('auth.user_types.types', []);
    }
    
    public static function getLabel(int $type): string
    {
        return config('auth.user_types.types')[$type] ?? 'Unknown';
    }
    
    public static function isAdmin(int $type): bool
    {
        return in_array($type, config('auth.user_types.admin_panel_types', []));
    }
    
    public static function requiresTwoFactor(int $type): bool
    {
        return in_array($type, config('auth.user_types.require_2fa_types', []));
    }
    
    public static function canImpersonate(int $type): bool
    {
        return in_array($type, config('auth.user_types.impersonate_types', []));
    }
    
    public static function isDeveloper(int $type): bool
    {
        return in_array($type, config('auth.user_types.developer_types', []));
    }
    
    public static function getAdminTypes(): array
    {
        return config('auth.user_types.admin_panel_types', []);
    }
}