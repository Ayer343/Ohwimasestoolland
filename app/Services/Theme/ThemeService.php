<?php

namespace App\Services\Theme;

use App\Contracts\Theme\ThemeRepositoryInterface;
use App\Contracts\Theme\ThemeServiceInterface;
use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\Events\Theme\ColorsUpdated;
use App\Events\Theme\ThemeReset;
use App\Events\Theme\ThemeUpdated;
use App\Models\User;
use App\Services\Theme\Support\SidebarThemeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ThemeService implements ThemeServiceInterface
{
    public function __construct(
        protected ThemeRepositoryInterface $repository,
        protected ThemeCssGenerator $cssGenerator,
        protected SidebarThemeRegistry $sidebarRegistry,
    ) {}

    /**
     * ============================================
     * READ OPERATIONS
     * ============================================
     */

    /**
     * Return the user's current settings (without generating CSS —
     * CSS has its own endpoint).
     */
    public function getSettings(User $user): array
    {
        $settings = $this->repository->getSettings($user);
        $colors   = $this->repository->getColors($user);

        return [
            'settings' => $settings->toArray(),
            'colors'   => $colors->toArray(),
        ];
    }

    public function getColors(User $user): array
    {
        $colors = $this->repository->getColors($user);

        return [
            'colors'         => $colors->toArray(),
            'default_colors' => ThemeColorsDTO::default()->toArray(),
        ];
    }

    public function getOptions(): array
    {
        return [
            'appearance'       => ThemeSettingsDTO::APPEARANCES,
            'sidebar_themes'   => $this->sidebarRegistry->names() + ['custom' => 'custom'],
            'font_sizes'       => ThemeSettingsDTO::FONT_SIZES,
            'layout'           => ThemeSettingsDTO::LAYOUTS,
            'animations'       => ThemeSettingsDTO::ANIMATIONS,
            'sidebar_position' => ThemeSettingsDTO::SIDEBAR_POSITIONS,
            'header_style'     => ThemeSettingsDTO::HEADER_STYLES,
            'density'          => ThemeSettingsDTO::DENSITIES,
        ];
    }

    public function getSidebarPreviews(): array
    {
        return $this->sidebarRegistry->previews();
    }

    /**
     * Generate CSS for the given settings + colors.
     */
    public function generateThemeCss(ThemeSettingsDTO $settings, ThemeColorsDTO $colors): string
    {
        return $this->cssGenerator->generate($settings, $colors, minify: true);
    }

    /**
     * Get the full resolved theme bundle (settings + colors + CSS + metadata).
     * Renamed from applyTheme() — this reads, it doesn't apply.
     */
    public function getThemeBundle(User $user): array
    {
        $settings = $this->repository->getSettings($user);
        $colors   = $this->repository->getColors($user);

        return [
            'theme'         => $settings->appearance,
            'sidebar_theme' => $settings->sidebarTheme,
            'settings'      => $settings->toArray(),
            'colors'        => $colors->toArray(),
            'css'           => $this->generateThemeCss($settings, $colors),
        ];
    }

    /**
     * ============================================
     * WRITE OPERATIONS
     * ============================================
     */

    public function updateSettings(User $user, array $data): array
    {
        $current = $this->repository->getSettings($user);
        $merged  = array_merge($current->toArray(), $data);
        $next    = ThemeSettingsDTO::fromArray($merged);

        $this->assertValidSettings($next);

        DB::transaction(function () use ($user, $next) {
            $this->repository->saveSettings($user, $next);
        });

        ThemeUpdated::dispatch(
            user:     $user,
            settings: $next,
            previous: $current,
        );

        return [
            'settings' => $next->toArray(),
            'css'      => $this->generateThemeCss($next, $this->repository->getColors($user)),
        ];
    }

    public function updateColors(User $user, array $data): array
    {
        $currentSettings = $this->repository->getSettings($user);
        $currentColors   = $this->repository->getColors($user);

        $merged = array_merge($currentColors->toArray(), $data);
        $next   = ThemeColorsDTO::fromArray($merged);

        $this->assertValidColors($next);

        // If the user customized colors, switch the sidebar theme to 'custom'.
        $settingsChanged = $next->isCustom() && $currentSettings->sidebarTheme !== 'custom';
        $nextSettings    = $settingsChanged
            ? $currentSettings->with(['sidebar_theme' => 'custom'])
            : $currentSettings;

        DB::transaction(function () use ($user, $next, $nextSettings, $settingsChanged) {
            $this->repository->saveColors($user, $next);
            if ($settingsChanged) {
                $this->repository->saveSettings($user, $nextSettings);
            }
        });

        // Fire colors event.
        ColorsUpdated::dispatch(
            user:     $user,
            colors:   $next,
            previous: $currentColors,
        );

        // If we also touched settings, announce that too.
        if ($settingsChanged) {
            ThemeUpdated::dispatch(
                user:     $user,
                settings: $nextSettings,
                previous: $currentSettings,
            );
        }

        return [
            'colors'   => $next->toArray(),
            'settings' => $nextSettings->toArray(),
            'css'      => $this->generateThemeCss($nextSettings, $next),
        ];
    }

    public function resetSettings(User $user): array
    {
        $previous = $this->repository->getSettings($user);

        DB::transaction(function () use ($user) {
            $this->repository->resetSettings($user);
        });

        $next = $this->repository->getSettings($user);

        ThemeReset::dispatch(
            user:             $user,
            scope:            ThemeReset::SCOPE_SETTINGS,
            previousSettings: $previous,
        );

        return [
            'settings' => $next->toArray(),
            'colors'   => $this->repository->getColors($user)->toArray(),
        ];
    }

    public function resetColors(User $user): array
    {
        $previousColors   = $this->repository->getColors($user);
        $currentSettings  = $this->repository->getSettings($user);
        $settingsChanged  = $currentSettings->sidebarTheme === 'custom';
        $nextSettings     = $settingsChanged
            ? $currentSettings->with(['sidebar_theme' => 'default'])
            : $currentSettings;

        DB::transaction(function () use ($user, $nextSettings, $settingsChanged) {
            $this->repository->resetColors($user);
            if ($settingsChanged) {
                $this->repository->saveSettings($user, $nextSettings);
            }
        });

        ThemeReset::dispatch(
            user:           $user,
            scope:          ThemeReset::SCOPE_COLORS,
            previousColors: $previousColors,
        );

        if ($settingsChanged) {
            ThemeUpdated::dispatch(
                user:     $user,
                settings: $nextSettings,
                previous: $currentSettings,
            );
        }

        return [
            'colors'   => $this->repository->getColors($user)->toArray(),
            'settings' => $nextSettings->toArray(),
        ];
    }

    /**
     * ============================================
     * VALIDATION HELPERS
     * ============================================
     */

    /**
     * Throw a ValidationException if the settings DTO fails validation.
     * Uses the DTO's own validationErrors() for per-field messages.
     */
    protected function assertValidSettings(ThemeSettingsDTO $settings): void
    {
        $errors = $settings->validationErrors();
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Throw a ValidationException if the colors DTO fails validation.
     * Uses the DTO's own validationErrors() for per-field messages.
     */
    protected function assertValidColors(ThemeColorsDTO $colors): void
    {
        $errors = $colors->validationErrors();
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}