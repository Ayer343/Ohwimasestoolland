<?php

use App\Models\SystemSetting;

if (!function_exists('registration_enabled')) {
    function registration_enabled() {
        $settings = SystemSetting::getSettings();
        return $settings->allow_registration ?? true;
    }
}

if (!function_exists('registration_message')) {
    function registration_message() {
        $settings = SystemSetting::getSettings();
        return $settings->registration_disabled_message ?? 'Registrations are currently disabled';
    }
}