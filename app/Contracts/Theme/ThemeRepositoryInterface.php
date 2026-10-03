<?php

namespace App\Contracts\Theme;

use App\DTOs\Theme\ThemeSettingsDTO;
use App\DTOs\Theme\ThemeColorsDTO;
use App\Models\User;

interface ThemeRepositoryInterface
{
    public function getSettings(User $user): ThemeSettingsDTO;
    public function saveSettings(User $user, ThemeSettingsDTO $settings): bool;
    public function getColors(User $user): ThemeColorsDTO;
    public function saveColors(User $user, ThemeColorsDTO $colors): bool;
    public function resetSettings(User $user): bool;
    public function resetColors(User $user): bool;
    public function clearCache(User $user): void;
}