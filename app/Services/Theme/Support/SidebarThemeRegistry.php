<?php

namespace App\Services\Theme\Support;

use App\DTOs\Theme\ThemeColorsDTO;

class SidebarThemeRegistry
{
    /**
     * Predefined sidebar palettes.
     * Single source of truth — no other class should hardcode these.
     */
    protected array $presets = [
        'default' => [
            'name'   => 'Default',
            'bg'     => 'linear-gradient(180deg, #7267f0 0%, #6258e0 100%)',
            'text'   => '#ffffff',
            'hover'  => 'rgba(255, 255, 255, 0.12)',
            'active' => 'rgba(255, 255, 255, 0.2)',
            'border' => 'transparent',
            'shadow' => '0 0 20px rgba(114, 103, 240, 0.3)',
        ],
        'dark' => [
            'name'   => 'Dark',
            'bg'     => 'linear-gradient(180deg, #232933 0%, #1e2229 100%)',
            'text'   => '#ecf0f1',
            'hover'  => 'rgba(236, 240, 241, 0.08)',
            'active' => 'rgba(52, 152, 219, 0.2)',
            'border' => '#3498db',
            'shadow' => '0 0 20px rgba(0, 0, 0, 0.2)',
        ],
        'light' => [
            'name'   => 'Light',
            'bg'     => 'linear-gradient(180deg, #ffffff 0%, #f5f7f9 100%)',
            'text'   => '#4a5568',
            'hover'  => 'rgba(114, 103, 240, 0.1)',
            'active' => 'rgba(114, 103, 240, 0.15)',
            'border' => '#7267f0',
            'shadow' => '0 0 15px rgba(0, 0, 0, 0.05)',
        ],
        'blue' => [
            'name'   => 'Blue',
            'bg'     => 'linear-gradient(180deg, #1a56db 0%, #1e429f 100%)',
            'text'   => '#ffffff',
            'hover'  => 'rgba(255, 255, 255, 0.12)',
            'active' => 'rgba(100, 255, 218, 0.2)',
            'border' => '#64FFDA',
            'shadow' => '0 0 20px rgba(26, 86, 219, 0.3)',
        ],
        'green' => [
            'name'   => 'Green',
            'bg'     => 'linear-gradient(180deg, #057a55 0%, #0a5c36 100%)',
            'text'   => '#ffffff',
            'hover'  => 'rgba(255, 255, 255, 0.12)',
            'active' => 'rgba(105, 240, 174, 0.2)',
            'border' => '#69F0AE',
            'shadow' => '0 0 20px rgba(5, 122, 85, 0.3)',
        ],
    ];

    /**
     * Get a preset palette by name, or null if unknown.
     */
    public function get(string $name): ?array
    {
        return $this->presets[$name] ?? null;
    }

    /**
     * All available preset names (for validation, UI pickers, etc.).
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->presets);
    }

    /**
     * Names + preview metadata for the UI.
     * Replaces ThemeController::getSidebarThemePreviews().
     */
    public function previews(): array
    {
        $previews = [];

        foreach ($this->presets as $name => $preset) {
            $previews[$name] = [
                'name'    => $preset['name'],
                'colors'  => [
                    'primary'    => $preset['border'] === 'transparent' ? $preset['active'] : $preset['border'],
                    'secondary'  => $preset['text'],
                    'background' => $preset['bg'],
                ],
                'preview' => $preset['bg'],
            ];
        }

        // The 'custom' entry is a placeholder — real values come from ThemeColorsDTO.
        $defaultColors = ThemeColorsDTO::default();
        $previews['custom'] = [
            'name'      => 'Custom',
            'colors'    => [
                'primary'    => $defaultColors->sidebarBgStart,
                'secondary'  => $defaultColors->sidebarBgEnd,
                'background' => "linear-gradient(180deg, {$defaultColors->sidebarBgStart} 0%, {$defaultColors->sidebarBgEnd} 100%)",
            ],
            'preview'   => "linear-gradient(180deg, {$defaultColors->sidebarBgStart} 0%, {$defaultColors->sidebarBgEnd} 100%)",
            'is_custom' => true,
        ];

        return $previews;
    }

    /**
     * Whether a given name is a valid preset (not including 'custom').
     */
    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }
}