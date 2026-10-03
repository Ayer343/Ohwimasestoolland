<?php

namespace App\Repositories\Theme;

use App\DTOs\Theme\ThemeSettingsDTO;
use App\DTOs\Theme\ThemeColorsDTO;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ThemeCacheRepository
{
    protected const CACHE_TTL = 3600;
    protected const PREFIX = 'theme_';

    public function getSettings(User $user): ?ThemeSettingsDTO
    {
        $key = $this->getSettingsKey($user);
        $data = Cache::get($key);
        
        if ($data) {
            return ThemeSettingsDTO::fromArray($data);
        }
        
        return null;
    }

    public function setSettings(User $user, ThemeSettingsDTO $settings): void
    {
        $key = $this->getSettingsKey($user);
        Cache::put($key, $settings->toArray(), self::CACHE_TTL);
    }

    public function getColors(User $user): ?ThemeColorsDTO
    {
        $key = $this->getColorsKey($user);
        $data = Cache::get($key);
        
        if ($data) {
            return ThemeColorsDTO::fromArray($data);
        }
        
        return null;
    }

    public function setColors(User $user, ThemeColorsDTO $colors): void
    {
        $key = $this->getColorsKey($user);
        Cache::put($key, $colors->toArray(), self::CACHE_TTL);
    }

    public function clearAll(User $user): void
    {
        Cache::forget($this->getSettingsKey($user));
        Cache::forget($this->getColorsKey($user));
        Cache::forget($this->getCssKey($user));
    }

    public function getCss(User $user): ?string
    {
        $key = $this->getCssKey($user);
        return Cache::get($key);
    }

    public function setCss(User $user, string $css): void
    {
        $key = $this->getCssKey($user);
        Cache::put($key, $css, self::CACHE_TTL);
    }

    protected function getSettingsKey(User $user): string
    {
        return self::PREFIX . 'settings_' . $user->id;
    }

    protected function getColorsKey(User $user): string
    {
        return self::PREFIX . 'colors_' . $user->id;
    }

    protected function getCssKey(User $user): string
    {
        return self::PREFIX . 'css_' . $user->id;
    }
}