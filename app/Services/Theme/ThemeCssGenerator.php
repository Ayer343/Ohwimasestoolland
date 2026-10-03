<?php

namespace App\Services\Theme;

use App\DTOs\Theme\ThemeColorsDTO;
use App\DTOs\Theme\ThemeSettingsDTO;
use App\Services\Theme\Support\SidebarThemeRegistry;
use Illuminate\Support\Facades\Log;

class ThemeCssGenerator
{
    public function __construct(
        protected SidebarThemeRegistry $sidebarRegistry
    ) {}

    /**
     * Generate the full theme CSS from settings + colors DTOs.
     */
    public function generate(ThemeSettingsDTO $settings, ThemeColorsDTO $colors, bool $minify = false): string
    {
        $css = [];

        // 1. Merge appearance + global colors into a single :root block
        //    (plus media queries for system mode). One coherent source of vars.
        $this->emitRootVariables($settings, $colors, $css);

        // 2. Sidebar theme (preset or custom)
        $this->emitSidebarTheme($settings, $colors, $css);

        // 3. Density variables
        $this->emitDensityVariables($settings, $css);

        // 4. Header style (glass/solid)
        $this->emitHeaderStyle($settings, $colors, $css);

        // 5. Animations toggle (scoped to data attribute)
        $this->emitAnimationRules($settings, $css);

        // 6. Accessibility: always respect prefers-reduced-motion
        $this->emitReducedMotionRules($css);

        $output = implode("\n", $css);

        return $minify ? $this->minify($output) : $output;
    }

    /**
     * ============================================
     * ROOT VARIABLES (APPEARANCE + GLOBAL COLORS)
     * ============================================
     */

    /**
     * Emit a single :root block (or media queries for system mode)
     * containing both appearance variables and global color variables.
     */
    protected function emitRootVariables(
        ThemeSettingsDTO $settings,
        ThemeColorsDTO $colors,
        array &$css
    ): void {
        $appearance = $settings->appearance;

        if ($appearance === 'dark') {
            $this->emitRootBlock($this->darkVariables($colors), $css);
        } elseif ($appearance === 'light') {
            $this->emitRootBlock($this->lightVariables($colors), $css);
        } else {
            // System mode — both preference queries.
            $this->emitMediaBlock('dark',  $this->darkVariables($colors),  $css);
            $this->emitMediaBlock('light', $this->lightVariables($colors), $css);
        }

        // Global semantic colors apply to all appearances.
        $this->emitGlobalColorVars($colors, $css);
    }

    /**
     * Appearance-specific background/text variables for dark mode.
     * Combined with the DTO-provided card/header colors.
     */
    protected function darkVariables(ThemeColorsDTO $colors): array
    {
        return [
            '--bg-primary'     => '#1e1e2d',
            '--bg-secondary'   => '#2a2a3c',
            '--text-primary'   => '#e4e4e4',
            '--text-secondary' => '#a0a0a0',
            '--card-bg'        => $colors->cardBg,
            '--card-border'    => $colors->cardBorder,
            '--header-bg'      => $colors->headerBg,
            '--header-text'    => $colors->headerText,
            '--border-color'   => '#39394a',
        ];
    }

    /**
     * Appearance-specific background/text variables for light mode.
     */
    protected function lightVariables(ThemeColorsDTO $colors): array
    {
        return [
            '--bg-primary'     => '#f8f8f8',
            '--bg-secondary'   => '#ffffff',
            '--text-primary'   => '#4b4b4b',
            '--text-secondary' => '#6b7280',
            '--card-bg'        => $colors->cardBg,
            '--card-border'    => $colors->cardBorder,
            '--header-bg'      => $colors->headerBg,
            '--header-text'    => $colors->headerText,
            '--border-color'   => '#e5e7eb',
        ];
    }

    /**
     * Global semantic colors — same across light/dark.
     */
    protected function emitGlobalColorVars(ThemeColorsDTO $colors, array &$css): void
    {
        $vars = [
            '--primary'   => $colors->primaryColor,
            '--secondary' => $colors->secondaryColor,
            '--accent'    => $colors->accentColor,
            '--success'   => $colors->successColor,
            '--warning'   => $colors->warningColor,
            '--danger'    => $colors->dangerColor,
            '--info'      => $colors->infoColor,
        ];

        $this->emitRootBlock($vars, $css);
    }

    /**
     * Emit a ":root { --key: value; ... }" block.
     */
    protected function emitRootBlock(array $vars, array &$css): void
    {
        $css[] = ':root {';
        foreach ($vars as $key => $value) {
            $css[] = "  {$key}: {$value};";
        }
        $css[] = '}';
    }

    /**
     * Emit "@media (prefers-color-scheme: {scheme}) { :root { ... } }".
     */
    protected function emitMediaBlock(string $scheme, array $vars, array &$css): void
    {
        $css[] = "@media (prefers-color-scheme: {$scheme}) {";
        $css[] = '  :root {';
        foreach ($vars as $key => $value) {
            $css[] = "    {$key}: {$value};";
        }
        $css[] = '  }';
        $css[] = '}';
    }

    /**
     * ============================================
     * SIDEBAR
     * ============================================
     */

    protected function emitSidebarTheme(
        ThemeSettingsDTO $settings,
        ThemeColorsDTO $colors,
        array &$css
    ): void {
        $sidebarTheme = $settings->sidebarTheme;

        if ($sidebarTheme === 'custom') {
            $this->emitCustomSidebar($colors, $css);
            return;
        }

        $preset = $this->sidebarRegistry->get($sidebarTheme);

        if ($preset === null) {
            Log::warning('Unknown sidebar theme encountered during CSS generation', [
                'theme' => $sidebarTheme,
            ]);
            // Fall back to default so the UI never renders unstyled.
            $preset = $this->sidebarRegistry->get('default');
            $sidebarTheme = 'default';
        }

        $this->emitPresetSidebar($sidebarTheme, $preset, $css);
    }

    protected function emitPresetSidebar(string $name, array $preset, array &$css): void
    {
        $css[] = "[data-sidebar-theme=\"{$name}\"] .sidebar {";
        $css[] = "  --sidebar-bg: {$preset['bg']};";
        $css[] = "  --sidebar-text: {$preset['text']};";
        $css[] = "  --sidebar-active-bg: {$preset['active']};";
        $css[] = "  --sidebar-hover-bg: {$preset['hover']};";
        $css[] = "  --sidebar-border: {$preset['border']};";
        $css[] = "  --sidebar-shadow: {$preset['shadow']};";
        $css[] = '}';
    }

    protected function emitCustomSidebar(ThemeColorsDTO $colors, array &$css): void
    {
        // Custom sidebar: emit values as CSS variables so the base
        // stylesheet can consume them uniformly across all sidebar themes.
        $css[] = '[data-sidebar-theme="custom"] .sidebar {';
        $css[] = "  --sidebar-bg: linear-gradient(180deg, {$colors->sidebarBgStart} 0%, {$colors->sidebarBgEnd} 100%);";
        $css[] = "  --sidebar-text: {$colors->sidebarText};";
        $css[] = "  --sidebar-active-bg: {$colors->sidebarActiveBg};";
        $css[] = "  --sidebar-hover-bg: {$colors->sidebarHoverBg};";
        $css[] = "  --sidebar-border: {$colors->sidebarBorder};";
        $css[] = "  --sidebar-shadow: {$colors->sidebarShadow};";
        $css[] = '}';

        // No redundant .nav-item.active / :hover rules — the base stylesheet
        // reads --sidebar-active-bg and --sidebar-hover-bg. This eliminates
        // the previous duplication.
    }

    /**
     * ============================================
     * DENSITY
     * ============================================
     */

    protected function emitDensityVariables(ThemeSettingsDTO $settings, array &$css): void
    {
        $density = $settings->density;

        $maps = [
            'compact' => [
                '--sidebar-padding'  => '0.5rem',
                '--nav-item-spacing' => '0.25rem',
                '--font-size-base'   => '0.875rem',
                '--card-padding'     => '1rem',
            ],
            'spacious' => [
                '--sidebar-padding'  => '1.5rem',
                '--nav-item-spacing' => '0.75rem',
                '--font-size-base'   => '1.125rem',
                '--card-padding'     => '2rem',
            ],
            'comfortable' => [
                '--sidebar-padding'  => '1rem',
                '--nav-item-spacing' => '0.5rem',
                '--font-size-base'   => '1rem',
                '--card-padding'     => '1.5rem',
            ],
        ];

        $vars = $maps[$density] ?? $maps['comfortable'];

        $css[] = "[data-density=\"{$density}\"] {";
        foreach ($vars as $key => $value) {
            $css[] = "  {$key}: {$value};";
        }
        $css[] = '}';
    }

    /**
     * ============================================
     * HEADER
     * ============================================
     */

    protected function emitHeaderStyle(
        ThemeSettingsDTO $settings,
        ThemeColorsDTO $colors,
        array &$css
    ): void {
        $headerStyle = $settings->headerStyle;

        if ($headerStyle === 'glass') {
            $this->emitGlassHeader($css);
        } elseif ($headerStyle === 'solid') {
            $this->emitSolidHeader($colors, $css);
        }
        // 'default' → no rules
    }

    protected function emitGlassHeader(array &$css): void
    {
        $css[] = '.header.glass {';
        $css[] = '  backdrop-filter: blur(10px);';
        $css[] = '  -webkit-backdrop-filter: blur(10px);';
        $css[] = '  background: rgba(255, 255, 255, 0.8);';
        $css[] = '  border-bottom: 1px solid rgba(255, 255, 255, 0.3);';
        $css[] = '  will-change: backdrop-filter;';
        $css[] = '}';

        // Dark-mode variant — works both when data-theme is set explicitly
        // AND when the OS is in dark mode (system appearance).
        $css[] = '[data-theme="dark"] .header.glass,';
        $css[] = '@media (prefers-color-scheme: dark) {';
        $css[] = '  .header.glass {';
        $css[] = '    background: rgba(30, 30, 45, 0.8);';
        $css[] = '    border-bottom: 1px solid rgba(255, 255, 255, 0.1);';
        $css[] = '  }';
        $css[] = '}';
    }

    protected function emitSolidHeader(ThemeColorsDTO $colors, array &$css): void
    {
        $css[] = '.header.solid {';
        $css[] = "  background: {$colors->headerBg};";
        $css[] = "  border-bottom: 2px solid {$colors->primaryColor};";
        $css[] = '}';
    }

    /**
     * ============================================
     * ANIMATIONS
     * ============================================
     */

    protected function emitAnimationRules(ThemeSettingsDTO $settings, array &$css): void
    {
        if ($settings->animations !== 'disabled') {
            return;
        }

        // Scoped to a data attribute, so third-party widgets can opt out
        // by removing the attribute or overriding these rules.
        $css[] = '[data-animations="disabled"] *,';
        $css[] = '[data-animations="disabled"] *::before,';
        $css[] = '[data-animations="disabled"] *::after {';
        $css[] = '  animation-duration: 0s !important;';
        $css[] = '  transition-duration: 0s !important;';
        $css[] = '}';
    }

    /**
     * Always respect the OS-level reduced-motion preference,
     * regardless of the user's app setting. WCAG 2.1 compliance.
     */
    protected function emitReducedMotionRules(array &$css): void
    {
        $css[] = '@media (prefers-reduced-motion: reduce) {';
        $css[] = '  *, *::before, *::after {';
        $css[] = '    animation-duration: 0.01ms !important;';
        $css[] = '    animation-iteration-count: 1 !important;';
        $css[] = '    transition-duration: 0.01ms !important;';
        $css[] = '    scroll-behavior: auto !important;';
        $css[] = '  }';
        $css[] = '}';
    }

    /**
     * ============================================
     * MINIFICATION
     * ============================================
     */

    /**
     * Lightweight CSS minifier — safe for the CSS we generate,
     * which contains no strings-with-semicolons or data URIs.
     */
    protected function minify(string $css): string
    {
        // Remove comments
        $css = preg_replace('#/\*.*?\*/#s', '', $css);

        // Collapse whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove space around structural characters
        $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);

        // Remove trailing semicolon before closing brace
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }
}