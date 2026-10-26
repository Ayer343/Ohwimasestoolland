<?php

namespace App\View\Composers;

use App\Contracts\Theme\ThemeServiceInterface;
use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class ThemeViewComposer
{
    public function __construct(
        protected ThemeServiceInterface $themeService,
    ) {}

    /**
     * Bind theme data to the view.
     * Call this via View::composer('*', ThemeViewComposer::class) or on
     * specific views that render the sidebar / theme picker.
     */
    public function compose(View $view): void
    {
        $user = auth()->user();

        $settings = $this->resolveSettings($user);
        $colors   = $this->resolveColors($user);

        $settingsDto = ThemeSettingsDTO::fromArray($settings);
        $colorsDto   = ThemeColorsDTO::fromArray($colors);

        $css = '';
        if ($settingsDto->isValid() && $colorsDto->isValid()) {
            try {
                $css = $this->themeService->generateThemeCss($settingsDto, $colorsDto);
            } catch (\Throwable $e) {
                Log::warning('ThemeViewComposer: CSS generation failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $view->with([
            'theme' => [
                'settings'      => $settings,
                'custom_colors' => $colors,
                'css'           => $css,
                'appearance'    => $settings['appearance']    ?? 'system',
                'sidebar_theme' => $settings['sidebar_theme'] ?? 'default',
            ],
            'defaultColors' => ThemeColorsDTO::default()->toArray(),
        ]);
    }

    /**
     * Session-cached settings, falling back to the service, then defaults.
     */
    protected function resolveSettings($user): array
    {
        if (session()->has('theme_settings')) {
            return session('theme_settings');
        }

        try {
            $resolved = $this->themeService->getSettings($user);
            $settings = $resolved['settings'] ?? ThemeSettingsDTO::default()->toArray();
        } catch (\Throwable $e) {
            Log::warning('ThemeViewComposer: settings resolution failed', [
                'error' => $e->getMessage(),
            ]);
            $settings = ThemeSettingsDTO::default()->toArray();
        }

        session(['theme_settings' => $settings]);

        return $settings;
    }

    /**
     * Resolve colors, falling back to defaults on failure.
     */
    protected function resolveColors($user): array
    {
        try {
            $resolved = $this->themeService->getColors($user);

            return $resolved['colors'] ?? ThemeColorsDTO::default()->toArray();
        } catch (\Throwable $e) {
            Log::warning('ThemeViewComposer: color resolution failed', [
                'error' => $e->getMessage(),
            ]);

            return ThemeColorsDTO::default()->toArray();
        }
    }
}