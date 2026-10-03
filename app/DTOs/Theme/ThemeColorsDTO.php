<?php

namespace App\DTOs\Theme;

use JsonSerializable;

class ThemeColorsDTO implements JsonSerializable
{
    /**
     * Fields that accept only hex colors (#rgb or #rrggbb).
     */
    private const HEX_FIELDS = [
        'sidebar_bg_start',
        'sidebar_bg_end',
        'sidebar_text',
        'sidebar_border',
        'primary_color',
        'secondary_color',
        'accent_color',
        'success_color',
        'warning_color',
        'danger_color',
        'info_color',
        'header_bg',
        'header_text',
        'card_bg',
        'card_border',
    ];

    /**
     * Fields that accept rgba() values.
     */
    private const RGBA_FIELDS = [
        'sidebar_active_bg',
        'sidebar_hover_bg',
    ];

    /**
     * Fields that accept freeform CSS (shadows, etc.) — not strictly validated.
     */
    private const FREEFORM_FIELDS = [
        'sidebar_shadow',
    ];

    /**
     * Named CSS colors accepted as-is for hex fields.
     */
    private const NAMED_COLORS = [
        'transparent',
        'inherit',
        'currentColor',
    ];

    /**
     * Cached default instance (avoids rebuilding on every call).
     */
    private static ?self $defaultInstance = null;

    public function __construct(
        // Sidebar colors
        public readonly string $sidebarBgStart,
        public readonly string $sidebarBgEnd,
        public readonly string $sidebarText,
        public readonly string $sidebarActiveBg,
        public readonly string $sidebarHoverBg,
        public readonly string $sidebarBorder,
        public readonly string $sidebarShadow,
        // Global colors
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
        public readonly string $accentColor,
        public readonly string $successColor,
        public readonly string $warningColor,
        public readonly string $dangerColor,
        public readonly string $infoColor,
        // Header colors
        public readonly string $headerBg,
        public readonly string $headerText,
        // Card colors
        public readonly string $cardBg,
        public readonly string $cardBorder
    ) {}

    /**
     * ============================================
     * FACTORY METHODS
     * ============================================
     */

    /**
     * Get the canonical default colors (cached).
     */
    public static function default(): self
    {
        return self::$defaultInstance ??= new self(
            sidebarBgStart: '#7267f0',
            sidebarBgEnd: '#6258e0',
            sidebarText: '#ffffff',
            sidebarActiveBg: 'rgba(255, 255, 255, 0.2)',
            sidebarHoverBg: 'rgba(255, 255, 255, 0.12)',
            sidebarBorder: 'transparent',
            sidebarShadow: '0 0 20px rgba(114, 103, 240, 0.3)',
            primaryColor: '#7267f0',
            secondaryColor: '#6258e0',
            accentColor: '#64FFDA',
            successColor: '#10b981',
            warningColor: '#f59e0b',
            dangerColor: '#ef4444',
            infoColor: '#06b6d4',
            headerBg: '#ffffff',
            headerText: '#4b4b4b',
            cardBg: '#ffffff',
            cardBorder: '#e5e7eb'
        );
    }

    /**
     * Hydrate from an array with per-field fallback to defaults.
     * Unknown keys are silently ignored; null values fall back to defaults.
     */
    public static function fromArray(?array $data): self
    {
        $default = self::default();

        return new self(
            sidebarBgStart:   $data['sidebar_bg_start']   ?? $default->sidebarBgStart,
            sidebarBgEnd:     $data['sidebar_bg_end']     ?? $default->sidebarBgEnd,
            sidebarText:      $data['sidebar_text']       ?? $default->sidebarText,
            sidebarActiveBg:  $data['sidebar_active_bg']  ?? $default->sidebarActiveBg,
            sidebarHoverBg:   $data['sidebar_hover_bg']   ?? $default->sidebarHoverBg,
            sidebarBorder:    $data['sidebar_border']     ?? $default->sidebarBorder,
            sidebarShadow:    $data['sidebar_shadow']     ?? $default->sidebarShadow,
            primaryColor:     $data['primary_color']      ?? $default->primaryColor,
            secondaryColor:   $data['secondary_color']    ?? $default->secondaryColor,
            accentColor:      $data['accent_color']       ?? $default->accentColor,
            successColor:     $data['success_color']      ?? $default->successColor,
            warningColor:     $data['warning_color']      ?? $default->warningColor,
            dangerColor:      $data['danger_color']       ?? $default->dangerColor,
            infoColor:        $data['info_color']         ?? $default->infoColor,
            headerBg:         $data['header_bg']          ?? $default->headerBg,
            headerText:       $data['header_text']        ?? $default->headerText,
            cardBg:           $data['card_bg']            ?? $default->cardBg,
            cardBorder:       $data['card_border']        ?? $default->cardBorder
        );
    }

    /**
     * Create a new instance with selective overrides (immutable "setter").
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * ============================================
     * SERIALIZATION
     * ============================================
     */

    public function toArray(): array
    {
        return [
            'sidebar_bg_start'  => $this->sidebarBgStart,
            'sidebar_bg_end'    => $this->sidebarBgEnd,
            'sidebar_text'      => $this->sidebarText,
            'sidebar_active_bg' => $this->sidebarActiveBg,
            'sidebar_hover_bg'  => $this->sidebarHoverBg,
            'sidebar_border'    => $this->sidebarBorder,
            'sidebar_shadow'    => $this->sidebarShadow,
            'primary_color'     => $this->primaryColor,
            'secondary_color'   => $this->secondaryColor,
            'accent_color'      => $this->accentColor,
            'success_color'     => $this->successColor,
            'warning_color'     => $this->warningColor,
            'danger_color'      => $this->dangerColor,
            'info_color'        => $this->infoColor,
            'header_bg'         => $this->headerBg,
            'header_text'       => $this->headerText,
            'card_bg'           => $this->cardBg,
            'card_border'       => $this->cardBorder,
        ];
    }

    /**
     * Allow the DTO to be JSON-encoded directly via response()->json($dto).
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
     * Validate every field against its expected format.
     * - HEX_FIELDS     → #rgb / #rrggbb / named colors
     * - RGBA_FIELDS    → rgba(...)
     * - FREEFORM_FIELDS → skipped (shadows, etc.)
     */
    public function isValid(): bool
    {
        $hexPattern  = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
        $rgbaPattern = '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|0?\.\d+|1)\s*\)$/';

        foreach ($this->toArray() as $key => $value) {
            // Freeform fields (shadows, complex CSS) — no validation.
            if (in_array($key, self::FREEFORM_FIELDS, true)) {
                continue;
            }

            // RGBA fields — must match rgba() pattern.
            if (in_array($key, self::RGBA_FIELDS, true)) {
                if (!preg_match($rgbaPattern, $value)) {
                    return false;
                }
                continue;
            }

            // HEX fields — accept named colors or hex.
            if (in_array($value, self::NAMED_COLORS, true)) {
                continue;
            }

            if (!preg_match($hexPattern, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return human-readable validation errors (empty array = valid).
     * Useful for surfacing to API consumers.
     */
    public function validationErrors(): array
    {
        $hexPattern  = '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/';
        $rgbaPattern = '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|0?\.\d+|1)\s*\)$/';

        $errors = [];

        foreach ($this->toArray() as $key => $value) {
            if (in_array($key, self::FREEFORM_FIELDS, true)) {
                continue;
            }

            if (in_array($key, self::RGBA_FIELDS, true)) {
                if (!preg_match($rgbaPattern, $value)) {
                    $errors[$key] = "Must be a valid rgba() color (e.g., rgba(255, 255, 255, 0.2)).";
                }
                continue;
            }

            if (in_array($value, self::NAMED_COLORS, true)) {
                continue;
            }

            if (!preg_match($hexPattern, $value)) {
                $errors[$key] = "Must be a valid hex color (e.g., #7267f0 or #fff).";
            }
        }

        return $errors;
    }

    /**
     * Check whether any value differs from the defaults.
     */
    public function isCustom(): bool
    {
        return self::default()->toArray() !== $this->toArray();
    }

    /**
     * ============================================
     * LARAVEL VALIDATION RULES (reusable)
     * ============================================
     */

    /**
     * Returns Laravel validation rules for each color field.
     * Use in Form Requests: `return ThemeColorsDTO::rules();`
     */
    public static function rules(): array
    {
        $hexRule  = ['string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'];
        $rgbaRule = ['string', 'regex:/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|0?\.\d+|1)\s*\)$/'];

        return [
            'sidebar_bg_start'  => $hexRule,
            'sidebar_bg_end'    => $hexRule,
            'sidebar_text'      => $hexRule,
            'sidebar_active_bg' => $rgbaRule,
            'sidebar_hover_bg'  => $rgbaRule,
            'sidebar_border'    => array_merge($hexRule, ['in:transparent,inherit,currentColor']), // allow named
            'sidebar_shadow'    => ['string', 'max:255'], // freeform
            'primary_color'     => $hexRule,
            'secondary_color'   => $hexRule,
            'accent_color'      => $hexRule,
            'success_color'     => $hexRule,
            'warning_color'     => $hexRule,
            'danger_color'      => $hexRule,
            'info_color'        => $hexRule,
            'header_bg'         => $hexRule,
            'header_text'       => $hexRule,
            'card_bg'           => $hexRule,
            'card_border'       => $hexRule,
        ];
    }
}