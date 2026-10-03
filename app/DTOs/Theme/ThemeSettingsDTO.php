<?php

namespace App\DTOs\Theme;

use Illuminate\Support\Arr;
use JsonSerializable;

class ThemeSettingsDTO implements JsonSerializable
{
    /**
     * ============================================
     * VALIDATION ALLOWLISTS
     * ============================================
     * Centralized so isValid(), validationErrors(), and rules()
     * all stay in sync. Add a new value here → everywhere updates.
     */
    public const APPEARANCES      = ['light', 'dark', 'system'];
    public const SIDEBAR_THEMES   = ['default', 'dark', 'light', 'blue', 'green', 'custom'];
    public const FONT_SIZES       = ['small', 'medium', 'large'];
    public const LAYOUTS          = ['compact', 'comfortable'];
    public const ANIMATIONS       = ['enabled', 'disabled'];
    public const SIDEBAR_POSITIONS = ['left', 'right'];
    public const HEADER_STYLES    = ['default', 'glass', 'solid'];
    public const DENSITIES        = ['comfortable', 'compact', 'spacious'];

    /**
     * Cached default instance.
     */
    private static ?self $defaultInstance = null;

    public function __construct(
        public readonly string $appearance,
        public readonly string $sidebarTheme,
        public readonly string $fontSize,
        public readonly string $layout,
        public readonly string $animations,
        public readonly string $sidebarPosition,
        public readonly string $headerStyle,
        public readonly string $density,
        public readonly ?array $customColors = null
    ) {}

    /**
     * ============================================
     * FACTORY METHODS
     * ============================================
     */

    /**
     * Canonical default settings (cached).
     */
    public static function default(): self
    {
        return self::$defaultInstance ??= new self(
            appearance: 'system',
            sidebarTheme: 'default',
            fontSize: 'medium',
            layout: 'comfortable',
            animations: 'enabled',
            sidebarPosition: 'left',
            headerStyle: 'default',
            density: 'comfortable',
            customColors: null
        );
    }

    /**
     * Hydrate from an array with per-field fallback to defaults.
     * Accepts null gracefully.
     */
    public static function fromArray(?array $data): self
    {
        $data = $data ?? [];
        $default = self::default();

        return new self(
            appearance:      Arr::get($data, 'appearance',       $default->appearance),
            sidebarTheme:    Arr::get($data, 'sidebar_theme',    $default->sidebarTheme),
            fontSize:        Arr::get($data, 'font_size',        $default->fontSize),
            layout:          Arr::get($data, 'layout',           $default->layout),
            animations:      Arr::get($data, 'animations',       $default->animations),
            sidebarPosition: Arr::get($data, 'sidebar_position', $default->sidebarPosition),
            headerStyle:     Arr::get($data, 'header_style',     $default->headerStyle),
            density:         Arr::get($data, 'density',          $default->density),
            customColors:    Arr::get($data, 'custom_colors')
        );
    }

    /**
     * Immutable partial update.
     *
     * @param array $overrides snake_case keys (e.g. ['appearance' => 'dark'])
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * Return a new DTO with custom colors attached.
     * Convenience for chaining: ->withColors($colorsDto)
     */
    public function withColors(?ThemeColorsDTO $colors): self
    {
        return $this->with([
            'custom_colors' => $colors?->toArray(),
        ]);
    }

    /**
     * ============================================
     * SERIALIZATION
     * ============================================
     */

    public function toArray(): array
    {
        return [
            'appearance'       => $this->appearance,
            'sidebar_theme'    => $this->sidebarTheme,
            'font_size'        => $this->fontSize,
            'layout'           => $this->layout,
            'animations'       => $this->animations,
            'sidebar_position' => $this->sidebarPosition,
            'header_style'     => $this->headerStyle,
            'density'          => $this->density,
            'custom_colors'    => $this->customColors,
        ];
    }

    /**
     * Allow direct JSON encoding via response()->json($dto).
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * ============================================
     * VALIDATION
     * ============================================
     */

    /**
     * Validate enum fields against allowlists, and validate nested
     * custom colors (if provided) via ThemeColorsDTO.
     */
    public function isValid(): bool
    {
        return empty($this->validationErrors());
    }

    /**
     * Human-readable validation errors, keyed by snake_case field name.
     * Empty array = valid.
     */
    public function validationErrors(): array
    {
        $errors = [];

        $checks = [
            'appearance'       => [$this->appearance,       self::APPEARANCES],
            'sidebar_theme'    => [$this->sidebarTheme,     self::SIDEBAR_THEMES],
            'font_size'        => [$this->fontSize,         self::FONT_SIZES],
            'layout'           => [$this->layout,           self::LAYOUTS],
            'animations'       => [$this->animations,       self::ANIMATIONS],
            'sidebar_position' => [$this->sidebarPosition,  self::SIDEBAR_POSITIONS],
            'header_style'     => [$this->headerStyle,      self::HEADER_STYLES],
            'density'          => [$this->density,          self::DENSITIES],
        ];

        foreach ($checks as $field => [$value, $allowed]) {
            if (!in_array($value, $allowed, true)) {
                $errors[$field] = sprintf(
                    'Must be one of: %s.',
                    implode(', ', $allowed)
                );
            }
        }

        // Validate nested custom colors, if present.
        if ($this->customColors !== null) {
            $colorErrors = ThemeColorsDTO::fromArray($this->customColors)->validationErrors();
            foreach ($colorErrors as $colorKey => $message) {
                $errors["custom_colors.{$colorKey}"] = $message;
            }
        }

        return $errors;
    }

    /**
     * Check whether any value differs from the defaults.
     * Does not consider custom_colors differences by default —
     * pass $includeColors = true to include them.
     */
    public function isCustom(bool $includeColors = false): bool
    {
        $default = self::default()->toArray();
        $current = $this->toArray();

        if (!$includeColors) {
            unset($default['custom_colors'], $current['custom_colors']);
        }

        return $default !== $current;
    }

    /**
     * ============================================
     * LARAVEL VALIDATION RULES
     * ============================================
     */

    /**
     * Rules for form requests validating settings input.
     * Unknown fields are ignored by default; extend if needed.
     */
    public static function rules(): array
    {
        return [
            'appearance'       => ['sometimes', 'string', 'in:' . implode(',', self::APPEARANCES)],
            'sidebar_theme'    => ['sometimes', 'string', 'in:' . implode(',', self::SIDEBAR_THEMES)],
            'font_size'        => ['sometimes', 'string', 'in:' . implode(',', self::FONT_SIZES)],
            'layout'           => ['sometimes', 'string', 'in:' . implode(',', self::LAYOUTS)],
            'animations'       => ['sometimes', 'string', 'in:' . implode(',', self::ANIMATIONS)],
            'sidebar_position' => ['sometimes', 'string', 'in:' . implode(',', self::SIDEBAR_POSITIONS)],
            'header_style'     => ['sometimes', 'string', 'in:' . implode(',', self::HEADER_STYLES)],
            'density'          => ['sometimes', 'string', 'in:' . implode(',', self::DENSITIES)],

            // Nested colors — prefix each rule with custom_colors.
            'custom_colors'    => ['sometimes', 'array'],
            ...self::prefixRules(ThemeColorsDTO::rules(), 'custom_colors.'),
        ];
    }

    /**
     * Helper: prefix every key in a rules array.
     */
    private static function prefixRules(array $rules, string $prefix): array
    {
        $prefixed = [];
        foreach ($rules as $key => $rule) {
            $prefixed[$prefix . $key] = $rule;
        }
        return $prefixed;
    }

    /**
     * ============================================
     * CONVENIENCE ACCESSORS
     * ============================================
     */

    public function isDark(): bool
    {
        return $this->appearance === 'dark';
    }

    public function isSystemAppearance(): bool
    {
        return $this->appearance === 'system';
    }

    public function hasCustomColors(): bool
    {
        return $this->customColors !== null && !empty($this->customColors);
    }

    /**
     * Return the nested colors as a DTO (or defaults if none set).
     */
    public function colorsAsDTO(): ThemeColorsDTO
    {
        return $this->hasCustomColors()
            ? ThemeColorsDTO::fromArray($this->customColors)
            : ThemeColorsDTO::default();
    }

    /**
     * Whether this DTO describes a fully custom theme (custom sidebar + colors).
     */
    public function isFullyCustom(): bool
    {
        return $this->sidebarTheme === 'custom' && $this->hasCustomColors();
    }
}