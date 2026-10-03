<?php

namespace App\Repositories\Theme;

use App\Contracts\Theme\ThemeRepositoryInterface;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\DTOs\Theme\ThemeColorsDTO;
use App\Models\User;
use App\Models\UserTheme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ThemeRepository implements ThemeRepositoryInterface
{
    protected const CACHE_TTL = 3600; // 1 hour
    
    public function __construct(
        protected ThemeCacheRepository $cache
    ) {}

    public function getSettings(User $user): ThemeSettingsDTO
    {
        // Check cache first
        $cached = $this->cache->getSettings($user);
        if ($cached) {
            return $cached;
        }

        // Try user model
        $settings = $this->getSettingsFromUserModel($user);
        if ($settings) {
            $this->cache->setSettings($user, $settings);
            return $settings;
        }

        // Try UserTheme model
        $settings = $this->getSettingsFromUserTheme($user);
        if ($settings) {
            $this->cache->setSettings($user, $settings);
            return $settings;
        }

        // Try system settings
        $settings = $this->getSettingsFromSystem();
        if ($settings) {
            $this->cache->setSettings($user, $settings);
            return $settings;
        }

        // Return defaults
        $defaults = $this->getDefaultSettings();
        $this->cache->setSettings($user, $defaults);
        return $defaults;
    }

    public function saveSettings(User $user, ThemeSettingsDTO $settings): bool
    {
        try {
            $saved = false;
            
            // Try user model
            if ($this->hasThemeColumn($user)) {
                $user->theme_preference = json_encode($settings->toArray());
                $user->save();
                $saved = true;
            }
            
            // Try UserTheme model
            if (class_exists(UserTheme::class)) {
                UserTheme::updateOrCreate(
                    ['user_id' => $user->id],
                    ['settings' => json_encode($settings->toArray())]
                );
                $saved = true;
            }
            
            // Clear cache
            $this->clearCache($user);
            
            return $saved;
        } catch (\Exception $e) {
            Log::error('Failed to save theme settings', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getColors(User $user): ThemeColorsDTO
    {
        // Check cache first
        $cached = $this->cache->getColors($user);
        if ($cached) {
            return $cached;
        }

        // Try user model
        $colors = $this->getColorsFromUserModel($user);
        if ($colors) {
            $this->cache->setColors($user, $colors);
            return $colors;
        }

        // Try UserTheme model
        $colors = $this->getColorsFromUserTheme($user);
        if ($colors) {
            $this->cache->setColors($user, $colors);
            return $colors;
        }

        // Try system settings
        $colors = $this->getColorsFromSystem();
        if ($colors) {
            $this->cache->setColors($user, $colors);
            return $colors;
        }

        // Return defaults
        $defaults = ThemeColorsDTO::default();
        $this->cache->setColors($user, $defaults);
        return $defaults;
    }

    public function saveColors(User $user, ThemeColorsDTO $colors): bool
    {
        try {
            $saved = false;
            
            // Try user model
            if ($this->hasCustomColorsColumn($user)) {
                $user->custom_colors = json_encode($colors->toArray());
                $user->save();
                $saved = true;
            }
            
            // Try UserTheme model
            if (class_exists(UserTheme::class)) {
                UserTheme::updateOrCreate(
                    ['user_id' => $user->id],
                    ['custom_colors' => json_encode($colors->toArray())]
                );
                $saved = true;
            }
            
            // Clear cache
            $this->clearCache($user);
            
            return $saved;
        } catch (\Exception $e) {
            Log::error('Failed to save custom colors', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function resetSettings(User $user): bool
    {
        try {
            // Reset user model
            if ($this->hasThemeColumn($user)) {
                $user->theme_preference = null;
                $user->save();
            }
            
            // Reset UserTheme
            if (class_exists(UserTheme::class)) {
                UserTheme::where('user_id', $user->id)->delete();
            }
            
            // Clear cache
            $this->clearCache($user);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset theme settings', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function resetColors(User $user): bool
    {
        try {
            // Reset user model
            if ($this->hasCustomColorsColumn($user)) {
                $user->custom_colors = null;
                $user->save();
            }
            
            // Reset UserTheme
            if (class_exists(UserTheme::class)) {
                UserTheme::where('user_id', $user->id)->update(['custom_colors' => null]);
            }
            
            // Clear cache
            $this->clearCache($user);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to reset custom colors', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function clearCache(User $user): void
    {
        $this->cache->clearAll($user);
    }

    // Private helper methods
    protected function getDefaultSettings(): ThemeSettingsDTO
    {
        return new ThemeSettingsDTO(
            appearance: 'system',
            sidebarTheme: 'default',
            fontSize: 'medium',
            layout: 'comfortable',
            animations: 'enabled',
            sidebarPosition: 'left',
            headerStyle: 'default',
            density: 'comfortable'
        );
    }

    protected function getSettingsFromUserModel(User $user): ?ThemeSettingsDTO
    {
        if (!$this->hasThemeColumn($user) || !$user->theme_preference) {
            return null;
        }

        try {
            $data = json_decode($user->theme_preference, true);
            return is_array($data) ? ThemeSettingsDTO::fromArray($data) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getSettingsFromUserTheme(User $user): ?ThemeSettingsDTO
    {
        if (!class_exists(UserTheme::class)) {
            return null;
        }

        try {
            $userTheme = UserTheme::where('user_id', $user->id)->first();
            if ($userTheme && $userTheme->settings) {
                $data = json_decode($userTheme->settings, true);
                return is_array($data) ? ThemeSettingsDTO::fromArray($data) : null;
            }
        } catch (\Exception $e) {
            Log::warning('Could not get settings from UserTheme', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    protected function getSettingsFromSystem(): ?ThemeSettingsDTO
    {
        try {
            $systemSetting = \App\Models\SystemSetting::first();
            if ($systemSetting && $systemSetting->theme_settings) {
                $data = json_decode($systemSetting->theme_settings, true);
                return is_array($data) ? ThemeSettingsDTO::fromArray($data) : null;
            }
        } catch (\Exception $e) {
            Log::warning('Could not get system theme settings', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    protected function getColorsFromUserModel(User $user): ?ThemeColorsDTO
    {
        if (!$this->hasCustomColorsColumn($user) || !$user->custom_colors) {
            return null;
        }

        try {
            $data = json_decode($user->custom_colors, true);
            return is_array($data) ? ThemeColorsDTO::fromArray($data) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getColorsFromUserTheme(User $user): ?ThemeColorsDTO
    {
        if (!class_exists(UserTheme::class)) {
            return null;
        }

        try {
            $userTheme = UserTheme::where('user_id', $user->id)->first();
            if ($userTheme && $userTheme->custom_colors) {
                $data = json_decode($userTheme->custom_colors, true);
                return is_array($data) ? ThemeColorsDTO::fromArray($data) : null;
            }
        } catch (\Exception $e) {
            Log::warning('Could not get colors from UserTheme', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    protected function getColorsFromSystem(): ?ThemeColorsDTO
    {
        try {
            $systemSetting = \App\Models\SystemSetting::first();
            if ($systemSetting && $systemSetting->custom_colors) {
                $data = json_decode($systemSetting->custom_colors, true);
                return is_array($data) ? ThemeColorsDTO::fromArray($data) : null;
            }
        } catch (\Exception $e) {
            Log::warning('Could not get system colors', [
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    protected function hasThemeColumn(User $user): bool
    {
        try {
            $columns = Schema::getColumnListing($user->getTable());
            return in_array('theme_preference', $columns);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function hasCustomColorsColumn(User $user): bool
    {
        try {
            $columns = Schema::getColumnListing($user->getTable());
            return in_array('custom_colors', $columns);
        } catch (\Exception $e) {
            return false;
        }
    }
}