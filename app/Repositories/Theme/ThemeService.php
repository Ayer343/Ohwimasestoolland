<?php

namespace App\Services\Theme;

use App\Contracts\Theme\ThemeServiceInterface;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\DTOs\Theme\ThemeColorsDTO;
use App\Events\Theme\ThemeUpdated;
use App\Events\Theme\ThemeReset;
use App\Events\Theme\ColorsUpdated;
use App\Models\User;
use App\Repositories\Theme\ThemeRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ThemeService implements ThemeServiceInterface
{
    protected array $themeOptions = [
        'appearance' => ['light', 'dark', 'system'],
        'sidebar_themes' => ['default', 'dark', 'light', 'blue', 'green', 'custom'],
        'font_sizes' => ['small', 'medium', 'large'],
        'layout' => ['compact', 'comfortable'],
        'animations' => ['enabled', 'disabled'],
        'sidebar_position' => ['left', 'right'],
        'header_style' => ['default', 'glass', 'solid'],
        'density' => ['comfortable', 'compact', 'spacious']
    ];

    protected array $sidebarThemes = [
        'default' => [
            'bg' => 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)',
            'text' => '#ffffff',
            'hover' => 'rgba(255, 255, 255, 0.12)',
            'active' => 'rgba(255, 255, 255, 0.2)',
            'border' => 'transparent',
            'shadow' => '0 0 20px rgba(114, 103, 240, 0.3)'
        ],
        'dark' => [
            'bg' => 'linear-gradient(180deg, #232933 0%, #1e2229 100%)',
            'text' => '#ecf0f1',
            'hover' => 'rgba(236, 240, 241, 0.08)',
            'active' => 'rgba(52, 152, 219, 0.2)',
            'border' => '#3498db',
            'shadow' => '0 0 20px rgba(0, 0, 0, 0.2)'
        ],
        'light' => [
            'bg' => 'linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%)',
            'text' => '#4a5568',
            'hover' => 'rgba(114, 103, 240, 0.1)',
            'active' => 'rgba(114, 103, 240, 0.15)',
            'border' => '#7267f0',
            'shadow' => '0 0 15px rgba(0, 0, 0, 0.05)'
        ],
        'blue' => [
            'bg' => 'linear-gradient(180deg, #1a56db 0%, #1e429f 100%)',
            'text' => '#ffffff',
            'hover' => 'rgba(255, 255, 255, 0.12)',
            'active' => 'rgba(100, 255, 218, 0.2)',
            'border' => '#64FFDA',
            'shadow' => '0 0 20px rgba(26, 86, 219, 0.3)'
        ],
        'green' => [
            'bg' => 'linear-gradient(180deg, #057a55 0%, #0a5c36 100%)',
            'text' => '#ffffff',
            'hover' => 'rgba(255, 255, 255, 0.12)',
            'active' => 'rgba(105, 240, 174, 0.2)',
            'border' => '#69F0AE',
            'shadow' => '0 0 20px rgba(5, 122, 85, 0.3)'
        ]
    ];

    public function __construct(
        protected ThemeRepositoryInterface $repository,
        protected ThemeCssGenerator $cssGenerator
    ) {}

    public function getSettings(User $user): array
    {
        $settings = $this->repository->getSettings($user);
        $colors = $this->repository->getColors($user);
        
        return [
            'settings' => $settings->toArray(),
            'colors' => $colors->toArray(),
            'options' => $this->getOptions(),
            'sidebar_previews' => $this->getSidebarPreviews(),
            'css' => $this->generateThemeCss($settings, $colors)
        ];
    }

    public function updateSettings(User $user, array $data): array
    {
        $this->validateSettings($data);
        
        $currentSettings = $this->repository->getSettings($user);
        $mergedSettings = array_merge($currentSettings->toArray(), $data);
        
        $settings = ThemeSettingsDTO::fromArray($mergedSettings);
        
        if (!$settings->isValid()) {
            throw ValidationException::withMessages([
                'settings' => 'Invalid theme settings provided'
            ]);
        }
        
        $this->repository->saveSettings($user, $settings);
        
        // Fire event
        event(new ThemeUpdated($user, $settings));
        
        // Generate fresh CSS
        $colors = $this->repository->getColors($user);
        $css = $this->generateThemeCss($settings, $colors);
        
        return [
            'settings' => $settings->toArray(),
            'css' => $css
        ];
    }

    public function resetSettings(User $user): array
    {
        $this->repository->resetSettings($user);
        
        // Fire event
        event(new ThemeReset($user));
        
        $settings = $this->repository->getSettings($user);
        $colors = $this->repository->getColors($user);
        
        return [
            'settings' => $settings->toArray(),
            'colors' => $colors->toArray()
        ];
    }

    public function getColors(User $user): array
    {
        $colors = $this->repository->getColors($user);
        
        return [
            'colors' => $colors->toArray(),
            'default_colors' => ThemeColorsDTO::default()->toArray()
        ];
    }

    public function updateColors(User $user, array $data): array
    {
        $this->validateColors($data);
        
        $currentColors = $this->repository->getColors($user);
        $mergedColors = array_merge($currentColors->toArray(), $data);
        
        $colors = ThemeColorsDTO::fromArray($mergedColors);
        
        if (!$colors->isValid()) {
            throw ValidationException::withMessages([
                'colors' => 'Invalid color values provided'
            ]);
        }
        
        $this->repository->saveColors($user, $colors);
        
        // If colors are customized, switch to custom sidebar theme
        if ($colors->isCustom()) {
            $settings = $this->repository->getSettings($user);
            if ($settings->sidebarTheme !== 'custom') {
                $newSettings = new ThemeSettingsDTO(
                    appearance: $settings->appearance,
                    sidebarTheme: 'custom',
                    fontSize: $settings->fontSize,
                    layout: $settings->layout,
                    animations: $settings->animations,
                    sidebarPosition: $settings->sidebarPosition,
                    headerStyle: $settings->headerStyle,
                    density: $settings->density
                );
                $this->repository->saveSettings($user, $newSettings);
            }
        }
        
        // Fire event
        event(new ColorsUpdated($user, $colors));
        
        $settings = $this->repository->getSettings($user);
        $css = $this->generateThemeCss($settings, $colors);
        
        return [
            'colors' => $colors->toArray(),
            'settings' => $settings->toArray(),
            'css' => $css
        ];
    }

    public function resetColors(User $user): array
    {
        $this->repository->resetColors($user);
        
        // Reset sidebar theme to default
        $settings = $this->repository->getSettings($user);
        if ($settings->sidebarTheme === 'custom') {
            $newSettings = new ThemeSettingsDTO(
                appearance: $settings->appearance,
                sidebarTheme: 'default',
                fontSize: $settings->fontSize,
                layout: $settings->layout,
                animations: $settings->animations,
                sidebarPosition: $settings->sidebarPosition,
                headerStyle: $settings->headerStyle,
                density: $settings->density
            );
            $this->repository->saveSettings($user, $newSettings);
        }
        
        // Fire event
        event(new ThemeReset($user));
        
        $colors = $this->repository->getColors($user);
        $settings = $this->repository->getSettings($user);
        
        return [
            'colors' => $colors->toArray(),
            'settings' => $settings->toArray()
        ];
    }

    public function getOptions(): array
    {
        return $this->themeOptions;
    }

    public function getSidebarPreviews(): array
    {
        $previews = [];
        
        foreach ($this->sidebarThemes as $key => $theme) {
            $previews[$key] = [
                'name' => ucfirst($key),
                'colors' => [
                    'primary' => $theme['text'] ?? '#ffffff',
                    'secondary' => $theme['active'] ?? 'rgba(255, 255, 255, 0.2)',
                    'background' => $theme['bg'] ?? 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)'
                ],
                'preview' => $theme['bg'] ?? 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)'
            ];
        }
        
        // Add custom theme preview
        $previews['custom'] = [
            'name' => 'Custom',
            'colors' => [
                'primary' => '#7267f0',
                'secondary' => '#6258e0',
                'background' => 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)'
            ],
            'preview' => 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)',
            'is_custom' => true
        ];
        
        return $previews;
    }

    public function generateThemeCss(ThemeSettingsDTO $settings, ThemeColorsDTO $colors): string
    {
        return $this->cssGenerator->generate($settings, $colors);
    }

    public function applyTheme(User $user): array
    {
        $settings = $this->repository->getSettings($user);
        $colors = $this->repository->getColors($user);
        $css = $this->generateThemeCss($settings, $colors);
        
        return [
            'theme' => $settings->appearance,
            'sidebar_theme' => $settings->sidebarTheme,
            'settings' => $settings->toArray(),
            'colors' => $colors->toArray(),
            'css' => $css
        ];
    }

    protected function validateSettings(array $data): void
    {
        $validator = Validator::make($data, [
            'appearance' => 'sometimes|in:light,dark,system',
            'sidebar_theme' => 'sometimes|in:default,dark,light,blue,green,custom',
            'font_size' => 'sometimes|in:small,medium,large',
            'layout' => 'sometimes|in:compact,comfortable',
            'animations' => 'sometimes|in:enabled,disabled',
            'sidebar_position' => 'sometimes|in:left,right',
            'header_style' => 'sometimes|in:default,glass,solid',
            'density' => 'sometimes|in:comfortable,compact,spacious'
        ]);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    protected function validateColors(array $data): void
    {
        $validator = Validator::make($data, [
            'sidebar_bg_start' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'sidebar_bg_end' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'sidebar_text' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'primary_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'secondary_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'accent_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'success_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'warning_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'danger_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'info_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'header_bg' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'header_text' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'card_bg' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'card_border' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
        ]);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}