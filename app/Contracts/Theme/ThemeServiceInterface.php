<?php

namespace App\Contracts\Theme;

use App\DTOs\Theme\ThemeSettingsDTO;
use App\DTOs\Theme\ThemeColorsDTO;
use App\Models\User;

interface ThemeServiceInterface
{
    public function getSettings(User $user): array;
    public function updateSettings(User $user, array $data): array;
    public function resetSettings(User $user): array;

    public function getColors(User $user): array;
    public function updateColors(User $user, array $data): array;
    public function resetColors(User $user): array;

    public function getOptions(): array;
    public function getSidebarPreviews(): array;

    public function generateThemeCss(ThemeSettingsDTO $settings, ThemeColorsDTO $colors): string;

    /**
     * Get the full resolved theme bundle for the user:
     * settings + colors + CSS + metadata.
     *
     * Formerly named applyTheme() — renamed because it reads, it does not apply.
     */
    public function getThemeBundle(User $user): array;
}