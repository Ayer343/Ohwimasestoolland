{{-- ============================================================ --}}
{{-- components/theme-color-picker.blade.php                       --}}
{{--                                                              --}}
{{-- A pure view component: renders fields, validates, emits       --}}
{{-- events. Does not touch the network.                          --}}
{{--                                                              --}}
{{-- Included with:                                               --}}
{{--   @include('components.theme-color-picker', [                --}}
{{--       'colors'        => $customColors,                      --}}
{{--       'defaultColors' => ThemeController::getDefaultColors() --}}
{{--   ])                                                         --}}
{{--                                                              --}}
{{-- Emits (window):                                              --}}
{{--   'theme-colors-updated' { colors }  — live preview, no save --}}
{{--   'theme-colors-save'    { colors }  — debounced persist     --}}
{{--   'theme-colors-reset'   {}          — user requested reset  --}}
{{-- ============================================================ --}}

@props(['colors', 'defaultColors'])

@php
    use App\DTOs\Theme\ThemeColorsDTO;

    // Ensure arrays, and merge defaults UNDER user colors so every
    // key is always present. This prevents "undefined" bindings.
    $defaults = is_array($defaultColors) && !empty($defaultColors)
        ? $defaultColors
        : ThemeColorsDTO::default()->toArray();

    $userColors = is_array($colors) ? $colors : [];

    $initial = array_merge(
        $defaults,
        array_filter($userColors, fn ($v) => $v !== null && $v !== '')
    );

    // ---- Field definitions ----
    // Each field: [label, input-type]
    //   'color' → native color picker + hex text + rgba detection
    //   'text'  → free-form text (rgba, shadows, borders)
    $groups = [
        'Sidebar' => [
            'sidebar_bg_start'  => ['label' => 'Background start', 'type' => 'color'],
            'sidebar_bg_end'    => ['label' => 'Background end',   'type' => 'color'],
            'sidebar_text'      => ['label' => 'Text',             'type' => 'color'],
            'sidebar_active_bg' => ['label' => 'Active BG',        'type' => 'text'],
            'sidebar_hover_bg'  => ['label' => 'Hover BG',         'type' => 'text'],
            'sidebar_border'    => ['label' => 'Border',           'type' => 'text'],
            'sidebar_shadow'    => ['label' => 'Shadow',           'type' => 'text'],
        ],
        'Global' => [
            'primary_color'   => ['label' => 'Primary',   'type' => 'color'],
            'secondary_color' => ['label' => 'Secondary', 'type' => 'color'],
            'accent_color'    => ['label' => 'Accent',    'type' => 'color'],
            'success_color'   => ['label' => 'Success',   'type' => 'color'],
            'warning_color'   => ['label' => 'Warning',   'type' => 'color'],
            'danger_color'    => ['label' => 'Danger',    'type' => 'color'],
            'info_color'      => ['label' => 'Info',      'type' => 'color'],
        ],
        'Header & Card' => [
            'header_bg'   => ['label' => 'Header BG',   'type' => 'color'],
            'header_text' => ['label' => 'Header text', 'type' => 'color'],
            'card_bg'     => ['label' => 'Card BG',     'type' => 'color'],
            'card_border' => ['label' => 'Card border', 'type' => 'color'],
        ],
    ];

    // Flex fields accept rgba()/shadow/gradient syntax rather than hex.
    $flexibleFields = [
        'sidebar_active_bg',
        'sidebar_hover_bg',
        'sidebar_border',
        'sidebar_shadow',
    ];

    $uid = 'tcp-' . substr(md5(uniqid('', true)), 0, 8);
@endphp

<div
    id="{{ $uid }}"
    class="theme-color-picker space-y-5"
    x-data="themeColorPicker({
        initial:  @js($initial),
        defaults: @js($defaults),
        flexible: @js($flexibleFields),
    })"
>
    {{-- ============================================================ --}}
    {{-- TOP BAR — status + reset-all                                 --}}
    {{-- ============================================================ --}}
    <div class="flex items-center justify-between p-2 rounded-lg"
         style="background: var(--bg-primary); border: 1px solid var(--border-color);">
        <span class="text-xs flex items-center gap-2"
              :style="dirty ? 'color: var(--warning)' : 'color: var(--text-secondary)'">
            <i class="fas" :class="dirty ? 'fa-circle text-[6px]' : 'fa-check-circle'"></i>
            <span x-text="dirty ? 'Unsaved changes' : 'Saved'"></span>
            <span x-show="saving" class="ml-1">
                <i class="fas fa-spinner fa-spin"></i>
            </span>
        </span>

        <button type="button"
                @click="resetAll()"
                class="text-xs px-3 py-1 rounded transition-colors hover:scale-105"
                style="color: var(--danger); border: 1px solid var(--danger); background: transparent;">
            <i class="fas fa-undo-alt mr-1"></i>Reset All
        </button>
    </div>

    {{-- ============================================================ --}}
    {{-- FIELD GROUPS                                                 --}}
    {{-- ============================================================ --}}
    @foreach ($groups as $groupName => $fields)
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-[10px] font-semibold uppercase tracking-wider"
                      style="color: var(--text-secondary);">
                    {{ $groupName }}
                </span>
                <div class="flex-1 h-px" style="background: var(--border-color);"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach ($fields as $key => $meta)
                    @php
                        $type     = $meta['type'];
                        $label    = $meta['label'];
                        $fieldId  = "{$uid}-{$key}";
                    @endphp

                    <div class="color-field flex items-center gap-2 p-2 rounded-lg transition-colors"
                         style="background: var(--bg-secondary); border: 1px solid var(--border-color);"
                         :class="{ 'has-error': validity['{{ $key }}'] === false }">

                        {{-- Label --}}
                        <label for="{{ $fieldId }}"
                               class="text-xs flex-shrink-0 w-24 truncate"
                               style="color: var(--text-secondary);"
                               title="{{ $label }}">
                            {{ $label }}
                        </label>

                        <div class="flex items-center gap-1 flex-1 min-w-0">
                            @if ($type === 'color')
                                {{-- Native color swatch. Value is always converted to
                                     a 6-digit hex so the input accepts it. --}}
                                <input
                                    type="color"
                                    aria-label="{{ $label }} picker"
                                    class="color-swatch flex-shrink-0"
                                    :value="hexForPicker(colors['{{ $key }}'])"
                                    @input="setFromPicker('{{ $key }}', $event.target.value)"
                                >

                                {{-- Hex text input --}}
                                <input
                                    type="text"
                                    id="{{ $fieldId }}"
                                    x-model="colors['{{ $key }}']"
                                    @input.debounce.400ms="commit()"
                                    spellcheck="false"
                                    autocomplete="off"
                                    placeholder="{{ $defaults[$key] ?? '#000000' }}"
                                    class="flex-1 min-w-0 px-2 py-1 rounded text-xs font-mono"
                                    style="color: var(--text-primary); background: var(--bg-primary); border: 1px solid var(--border-color); outline: none;"
                                >
                            @else
                                {{-- Text-only field for rgba/shadow/gradient values --}}
                                <input
                                    type="text"
                                    id="{{ $fieldId }}"
                                    x-model="colors['{{ $key }}']"
                                    @input.debounce.400ms="commit()"
                                    spellcheck="false"
                                    autocomplete="off"
                                    placeholder="{{ $defaults[$key] ?? '' }}"
                                    class="flex-1 min-w-0 px-2 py-1 rounded text-xs font-mono"
                                    style="color: var(--text-primary); background: var(--bg-primary); border: 1px solid var(--border-color); outline: none;"
                                >

                                {{-- Live preview swatch for text fields --}}
                                <div class="w-6 h-6 rounded flex-shrink-0 border"
                                     style="border-color: var(--border-color); background: var(--bg-primary);"
                                     :style="previewStyle('{{ $key }}')"
                                     :title="colors['{{ $key }}']"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- ============================================================ --}}
    {{-- LIVE PREVIEW                                                 --}}
    {{-- ============================================================ --}}
    <div class="p-3 rounded-lg"
         style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
        <div class="text-[10px] font-semibold uppercase tracking-wider mb-2"
             style="color: var(--text-secondary);">
            Live Preview
        </div>

        <div class="flex items-center gap-3">
            {{-- Sidebar mock --}}
            <div class="w-12 h-12 rounded-lg flex items-center justify-center text-sm font-bold flex-shrink-0"
                 :style="`background: linear-gradient(135deg, ${colors.sidebar_bg_start} 0%, ${colors.sidebar_bg_end} 100%); color: ${colors.sidebar_text};`">
                AB
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <div class="w-4 h-4 rounded-full border"
                         style="border-color: var(--border-color);"
                         :style="`background: ${colors.primary_color}`"
                         title="Primary"></div>
                    <div class="w-4 h-4 rounded-full border"
                         style="border-color: var(--border-color);"
                         :style="`background: ${colors.secondary_color}`"
                         title="Secondary"></div>
                    <div class="w-4 h-4 rounded-full border"
                         style="border-color: var(--border-color);"
                         :style="`background: ${colors.accent_color}`"
                         title="Accent"></div>
                </div>
                <div class="text-[10px] truncate" style="color: var(--text-secondary);"
                     x-text="`Primary ${colors.primary_color} · Accent ${colors.accent_color}`"></div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- ALPINE COMPONENT                                             --}}
{{-- ============================================================ --}}
<script>
document.addEventListener('alpine:init', () => {
    // Register once, no matter how many times this partial is included.
    if (Alpine.data('themeColorPicker')) return;

    Alpine.data('themeColorPicker', ({ initial, defaults, flexible }) => ({
        // ---- State ----
        colors:    { ...defaults, ...initial },
        defaults:  { ...defaults },
        flexible:  flexible || [],
        validity:  {},
        saving:    false,
        _debounce: null,
        _baseline: JSON.stringify({ ...defaults, ...initial }),

        // ---- Lifecycle ----
        init() {
            // Validate everything up front.
            Object.keys(this.colors).forEach(k => this.validate(k));

            // Parent can toggle saving indicator by dispatching events.
            document.addEventListener('theme-colors-save-start', () => {
                this.saving = true;
            });
            document.addEventListener('theme-colors-save-end', () => {
                this.saving = false;
                // Re-sync the baseline after a confirmed save.
                this._baseline = JSON.stringify(this.colors);
            });

            // If the parent tells us to revert (e.g., after a failed save),
            // pull the canonical state back from the server payload.
            document.addEventListener('theme-colors-revert', (e) => {
                if (e.detail?.colors) {
                    this.colors = { ...this.defaults, ...e.detail.colors };
                    this.validateAll();
                }
            });

            // Refresh from server (used after reset).
            document.addEventListener('theme-colors-refresh', (e) => {
                if (e.detail?.colors) {
                    this.colors = { ...this.defaults, ...e.detail.colors };
                    this._baseline = JSON.stringify(this.colors);
                    this.validateAll();
                }
            });
        },

        // ---- Input handling ----
        setFromPicker(key, hex) {
            this.colors[key] = hex;
            this.commit();
        },

        commit() {
            // Validate all fields, but only block on the one being edited.
            Object.keys(this.colors).forEach(k => this.validate(k));

            const invalid = Object.keys(this.colors).some(k => this.validity[k] === false);
            if (invalid) return;

            // Skip if nothing changed since the last baseline.
            if (JSON.stringify(this.colors) === this._baseline) {
                this.saving = false;
                return;
            }

            // Instant visual update (no save).
            this.emitUpdated();

            // Debounced persist.
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => this.emitSave(), 500);
        },

        // ---- Events ----
        emitUpdated() {
            document.dispatchEvent(new CustomEvent('theme-colors-updated', {
                detail: { colors: { ...this.colors } },
            }));
        },

        emitSave() {
            document.dispatchEvent(new CustomEvent('theme-colors-save', {
                detail: { colors: { ...this.colors } },
            }));
        },

        resetAll() {
            if (!confirm('Reset all custom colors to their defaults?')) return;

            this.colors = { ...this.defaults };
            this._baseline = JSON.stringify(this.colors);
            this.validateAll();
            this.emitUpdated();

            document.dispatchEvent(new CustomEvent('theme-colors-reset', {
                detail: { colors: { ...this.colors } },
            }));
        },

        // ---- Dirty check ----
        get dirty() {
            return JSON.stringify(this.colors) !== this._baseline;
        },

        // ---- Validation ----
        validateAll() {
            Object.keys(this.colors).forEach(k => this.validate(k));
        },

        validate(key) {
            const raw = this.colors[key];
            const v = String(raw ?? '').trim();

            if (v === '') {
                this.validity[key] = true;
                return true;
            }

            const HEX    = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;
            const RGB    = /^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/;
            const RGBA   = /^rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(0|0?\.\d+|1)\s*\)$/;
            const SHADOW = /^(-?\d+(\.\d+)?(px|em|rem)?\s+){2,4}(#[0-9a-f]{3,6}|rgba?\([^)]+\)|[a-z]+)\s*$/i;
            const GRAD   = /^(linear|radial|conic)-gradient\(.+\)$/i;
            const NAMED  = ['transparent', 'inherit', 'currentcolor', 'none'];

            const isFlexible = this.flexible.includes(key);

            const valid = isFlexible
                ? (HEX.test(v) || RGB.test(v) || RGBA.test(v) || SHADOW.test(v) || GRAD.test(v)
                    || NAMED.includes(v.toLowerCase()))
                : (HEX.test(v) || NAMED.includes(v.toLowerCase()));

            this.validity[key] = valid;
            return valid;
        },

        // ---- Color helpers ----
        // Produces a 6-digit hex suitable for <input type="color">,
        // converting rgb()/rgba() when possible.
        hexForPicker(value) {
            if (!value) return '#000000';
            const v = String(value).trim();

            if (/^#[0-9a-f]{6}$/i.test(v)) return v;
            if (/^#[0-9a-f]{3}$/i.test(v)) {
                return '#' + v[1]+v[1] + v[2]+v[2] + v[3]+v[3];
            }

            const m = v.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i);
            if (m) {
                const h = n => Math.min(255, parseInt(n, 10)).toString(16).padStart(2, '0');
                return '#' + h(m[1]) + h(m[2]) + h(m[3]);
            }

            return '#000000';
        },

        // Live preview for text-only fields.
        previewStyle(key) {
            const v = this.colors[key];
            if (!v) return 'background: transparent;';

            if (key === 'sidebar_shadow') {
                return `background: var(--bg-primary); box-shadow: ${v};`;
            }
            if (key === 'sidebar_border') {
                return `background: transparent; border: 2px solid ${v};`;
            }
            return `background: ${v};`;
        },
    }));
});
</script>

{{-- ============================================================ --}}
{{-- COMPONENT-SCOPED STYLES                                      --}}
{{-- ============================================================ --}}
<style>
.theme-color-picker .color-field:hover {
    border-color: var(--primary) !important;
}
.theme-color-picker .color-field.has-error {
    border-color: var(--danger) !important;
    box-shadow: 0 0 0 1px var(--danger);
}

.theme-color-picker .color-swatch {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    width: 28px;
    height: 28px;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    cursor: pointer;
    background: transparent;
}
.theme-color-picker .color-swatch::-webkit-color-swatch-wrapper { padding: 0; }
.theme-color-picker .color-swatch::-webkit-color-swatch { border: none; border-radius: 4px; }
.theme-color-picker .color-swatch::-moz-color-swatch   { border: none; border-radius: 4px; }

.theme-color-picker input[type="text"]:focus {
    border-color: var(--primary) !important;
}
</style>