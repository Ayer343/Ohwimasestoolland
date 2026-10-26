@extends('layouts.app')

@section('title', 'Homepage Content')

@php
    use Illuminate\Support\Str;
    use App\Models\SystemSetting;
    use App\Models\AdCampaign;
    use App\Models\AdInvoice;
    use App\Models\AdPricingPlan;

    // ⭐ Defensive theme config resolution — safe on every PHP version
    $themeDefaults = SystemSetting::HOMEPAGE_DEFAULTS['theme'];
    $presets       = SystemSetting::THEME_PRESETS;

    $theme        = (isset($config['theme'])  && is_array($config['theme']))  ? $config['theme']  : [];
    $themeColors  = (isset($theme['colors'])  && is_array($theme['colors']))  ? $theme['colors']  : $themeDefaults['colors'];
    $themeFonts   = (isset($theme['fonts'])   && is_array($theme['fonts']))   ? $theme['fonts']   : $themeDefaults['fonts'];
    $themeHero    = (isset($theme['hero'])    && is_array($theme['hero']))    ? $theme['hero']    : $themeDefaults['hero'];
    $themeLayout  = (isset($theme['layout'])  && is_array($theme['layout']))  ? $theme['layout']  : $themeDefaults['layout'];
    $themeButtons = (isset($theme['buttons']) && is_array($theme['buttons'])) ? $theme['buttons'] : $themeDefaults['buttons'];

    $currentPreset = old('theme.preset', isset($theme['preset']) ? $theme['preset'] : 'default');
    $isCustomTheme = ($currentPreset === 'custom');

    // ⭐ Login page config resolution
    $loginDefaults = SystemSetting::HOMEPAGE_DEFAULTS['login'];
    $login         = (isset($config['login']) && is_array($config['login'])) ? $config['login'] : [];
    $loginMerged   = array_replace_recursive($loginDefaults, $login);

    $loginThemeDefaults  = $loginDefaults['theme'] ?? [];
    $loginTheme          = (isset($loginMerged['theme']) && is_array($loginMerged['theme']))
                            ? $loginMerged['theme']
                            : $loginThemeDefaults;

    $loginThemeColors    = (isset($loginTheme['colors'])  && is_array($loginTheme['colors']))  ? $loginTheme['colors']  : ($loginThemeDefaults['colors']  ?? []);
    $loginThemeFonts     = (isset($loginTheme['fonts'])   && is_array($loginTheme['fonts']))   ? $loginTheme['fonts']   : ($loginThemeDefaults['fonts']   ?? []);
    $loginThemeLayout    = (isset($loginTheme['layout'])  && is_array($loginTheme['layout']))  ? $loginTheme['layout']  : ($loginThemeDefaults['layout']  ?? []);
    $loginThemeButtons   = (isset($loginTheme['buttons']) && is_array($loginTheme['buttons'])) ? $loginTheme['buttons'] : ($loginThemeDefaults['buttons'] ?? []);

    $currentLoginPreset    = old('login.theme.preset', $loginTheme['preset'] ?? 'default');
    $isCustomLoginTheme    = ($currentLoginPreset === 'custom');

    $loginThemeIsCustomised = false;
    if (!empty($login['theme']) && is_array($login['theme'])) {
        foreach (['colors', 'fonts', 'layout', 'buttons'] as $group) {
            foreach (($login['theme'][$group] ?? []) as $k => $v) {
                if ($v !== ($loginThemeDefaults[$group][$k] ?? null)) {
                    $loginThemeIsCustomised = true;
                    break 2;
                }
            }
        }
        if (($login['theme']['preset'] ?? 'default') !== 'default') {
            $loginThemeIsCustomised = true;
        }
    }

    $loginFeatures = old('login.welcome_features', $loginMerged['welcome_features'] ?? $loginDefaults['welcome_features']);
    if (!is_array($loginFeatures)) {
        $loginFeatures = $loginDefaults['welcome_features'];
    }
    $loginFeatures = array_slice($loginFeatures, 0, 6);

    $loginSocials = old('login.footer_socials', $loginMerged['footer_socials'] ?? $loginDefaults['footer_socials']);
    if (!is_array($loginSocials)) {
        $loginSocials = $loginDefaults['footer_socials'];
    }
    while (count($loginSocials) < 4) {
        $loginSocials[] = ['platform' => '', 'url' => ''];
    }

    $loginBgUrl = isset($loginBgUrl) ? $loginBgUrl : null;

    $loginIsCustomised = false;
    if (!empty($login)) {
        foreach (['default_theme','overlay_opacity','system_name_override',
                  'welcome_title','welcome_subtitle','background_url'] as $k) {
            if (isset($login[$k]) && $login[$k] !== ($loginDefaults[$k] ?? null)) {
                $loginIsCustomised = true; break;
            }
        }
        foreach (['show_welcome_panel','show_social_icons','show_cookie_banner'] as $k) {
            if (array_key_exists($k, $login) && (bool)$login[$k] !== (bool)($loginDefaults[$k] ?? true)) {
                $loginIsCustomised = true; break;
            }
        }
        if (!empty($login['welcome_features']) || !empty($login['footer_socials'])) {
            $loginIsCustomised = true;
        }
    }

    // ============================================================
    // 📢 AD SPACE — data
    // ============================================================
    $adConfig      = $settings->getAdConfig();
    $adPlans       = AdPricingPlan::orderByDesc('is_active')
                        ->orderBy('sort_order')
                        ->orderByDesc('created_at')
                        ->get();
    $adStats = [
        'active'          => AdCampaign::where('status', AdCampaign::STATUS_ACTIVE)->count(),
        'pending_payment' => AdCampaign::where('status', AdCampaign::STATUS_PENDING_PAYMENT)->count(),
        'rented_early'    => AdCampaign::where('status', AdCampaign::STATUS_RENTED_EARLY)->count(),
        'expired'         => AdCampaign::where('status', AdCampaign::STATUS_EXPIRED)->count(),
        'pending_rented'  => AdCampaign::whereNotNull('rented_requested_at')
                                ->whereNull('rented_approved_at')
                                ->count(),
        'total_revenue'   => (float) AdInvoice::where('status', AdInvoice::STATUS_PAID)->sum('amount'),
    ];
    $recentCampaigns = AdCampaign::with(['unit.property', 'landlord', 'plan'])
                        ->latest()
                        ->limit(5)
                        ->get();

    // Active tab — driven by query string so refresh keeps the position
    $activeTab = request('tab', 'homepage');

    // ⭐ Feature icon catalogue — grouped by category (login features)
    $featureIconGroups = [
        'Property & Estate' => [
            'fas fa-home'              => 'Home',
            'fas fa-building'          => 'Building',
            'fas fa-city'              => 'City',
            'fas fa-house-user'        => 'House User',
            'fas fa-warehouse'         => 'Warehouse',
            'fas fa-hotel'             => 'Hotel',
            'fas fa-store'             => 'Store',
            'fas fa-landmark'          => 'Landmark',
            'fas fa-map-marker-alt'    => 'Location Pin',
            'fas fa-map'               => 'Map',
            'fas fa-key'               => 'Key',
            'fas fa-door-open'         => 'Door Open',
            'fas fa-bed'               => 'Bed',
            'fas fa-couch'             => 'Couch',
            'fas fa-tree'              => 'Tree',
        ],
        'Security & Trust' => [
            'fas fa-shield-alt'        => 'Shield',
            'fas fa-shield-halved'     => 'Shield (halved)',
            'fas fa-lock'              => 'Lock',
            'fas fa-unlock'            => 'Unlock',
            'fas fa-user-shield'       => 'User Shield',
            'fas fa-fingerprint'       => 'Fingerprint',
            'fas fa-eye'               => 'Eye',
            'fas fa-video'             => 'Video / CCTV',
            'fas fa-bell'              => 'Bell / Alert',
            'fas fa-check-circle'      => 'Check Circle',
        ],
        'Amenities & Comfort' => [
            'fas fa-swimming-pool'     => 'Swimming Pool',
            'fas fa-dumbbell'          => 'Gym',
            'fas fa-spa'               => 'Spa',
            'fas fa-utensils'          => 'Restaurant',
            'fas fa-wifi'              => 'Wi-Fi',
            'fas fa-parking'           => 'Parking',
            'fas fa-bolt'              => 'Electricity',
            'fas fa-water'             => 'Water',
            'fas fa-fire'              => 'Heating',
            'fas fa-snowflake'         => 'Air Conditioning',
            'fas fa-sun'               => 'Solar',
            'fas fa-leaf'              => 'Green / Eco',
            'fas fa-seedling'          => 'Landscaping',
            'fas fa-baby'              => 'Family / Child',
            'fas fa-paw'               => 'Pet Friendly',
        ],
        'Money & Billing' => [
            'fas fa-credit-card'       => 'Credit Card',
            'fas fa-wallet'            => 'Wallet',
            'fas fa-money-bill-wave'   => 'Cash',
            'fas fa-money-check'       => 'Cheque',
            'fas fa-receipt'           => 'Receipt',
            'fas fa-file-invoice-dollar'=> 'Invoice',
            'fas fa-coins'             => 'Coins',
            'fas fa-piggy-bank'        => 'Savings',
            'fas fa-chart-line'        => 'Growth',
            'fas fa-percent'           => 'Discount',
        ],
        'Communication' => [
            'fas fa-envelope'          => 'Email',
            'fas fa-phone'             => 'Phone',
            'fas fa-comments'          => 'Chat',
            'fas fa-comment-dots'      => 'Messages',
            'fas fa-bullhorn'          => 'Announcement',
            'fas fa-headset'           => 'Support',
            'fas fa-share-alt'         => 'Share',
        ],
        'Users & Community' => [
            'fas fa-user'              => 'User',
            'fas fa-users'             => 'Users',
            'fas fa-user-tie'          => 'Landlord',
            'fas fa-user-friends'      => 'Community',
            'fas fa-handshake'         => 'Handshake',
            'fas fa-heart'             => 'Favorite',
            'fas fa-star'              => 'Star',
            'fas fa-award'             => 'Award',
        ],
        'Documents & Process' => [
            'fas fa-file-signature'    => 'Sign Document',
            'fas fa-file-contract'     => 'Contract',
            'fas fa-file-alt'          => 'Document',
            'fas fa-clipboard-check'   => 'Approved',
            'fas fa-calendar-check'    => 'Calendar',
            'fas fa-clock'             => 'Clock',
            'fas fa-tasks'             => 'Tasks',
            'fas fa-check'             => 'Check',
            'fas fa-list'              => 'List',
            'fas fa-list-ol'           => 'Numbered List',
        ],
        'Analytics & Reports' => [
            'fas fa-chart-bar'         => 'Bar Chart',
            'fas fa-chart-pie'         => 'Pie Chart',
            'fas fa-chart-area'        => 'Area Chart',
            'fas fa-search'            => 'Search',
            'fas fa-database'          => 'Database',
            'fas fa-cog'               => 'Settings',
            'fas fa-sliders-h'         => 'Sliders',
        ],
        'Miscellaneous' => [
            'fas fa-check'             => 'Check',
            'fas fa-times'             => 'Times',
            'fas fa-plus'              => 'Plus',
            'fas fa-rocket'            => 'Rocket',
            'fas fa-gem'               => 'Diamond / Premium',
            'fas fa-crown'             => 'Crown / VIP',
            'fas fa-trophy'            => 'Trophy',
            'fas fa-thumbs-up'         => 'Thumbs Up',
            'fas fa-gift'              => 'Gift',
            'fas fa-globe'             => 'Globe',
        ],
    ];

    $knownFeatureIcons = [];
    foreach ($featureIconGroups as $group) {
        foreach ($group as $cls => $label) {
            $knownFeatureIcons[$cls] = true;
        }
    }

    if (!function_exists('theme_val')) {
        function theme_val(array $theme, array $defaults, string $group, string $key, $fallback = '') {
            $fromTheme   = isset($theme[$group][$key])    ? $theme[$group][$key]    : null;
            $fromDefault = isset($defaults[$group][$key]) ? $defaults[$group][$key] : null;

            return old("theme.{$group}.{$key}", $fromTheme !== null ? $fromTheme : ($fromDefault !== null ? $fromDefault : $fallback));
        }
    }

    if (!function_exists('login_theme_val')) {
        function login_theme_val(array $theme, array $defaults, string $group, string $key, $fallback = '') {
            $fromTheme   = isset($theme[$group][$key])    ? $theme[$group][$key]    : null;
            $fromDefault = isset($defaults[$group][$key]) ? $defaults[$group][$key] : null;

            return old("login.theme.{$group}.{$key}", $fromTheme !== null ? $fromTheme : ($fromDefault !== null ? $fromDefault : $fallback));
        }
    }

    // Status badge map for campaign rows
    $campaignStatusBadges = [
        AdCampaign::STATUS_DRAFT           => ['Draft',           'secondary'],
        AdCampaign::STATUS_PENDING_PAYMENT => ['Awaiting Payment','warning'],
        AdCampaign::STATUS_ACTIVE          => ['Active',          'success'],
        AdCampaign::STATUS_PAUSED          => ['Paused',          'secondary'],
        AdCampaign::STATUS_RENTED_EARLY    => ['Rented',          'info'],
        AdCampaign::STATUS_EXPIRED         => ['Expired',         'secondary'],
        AdCampaign::STATUS_CANCELLED       => ['Cancelled',       'danger'],
        AdCampaign::STATUS_REJECTED        => ['Rejected',        'danger'],
    ];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ==================== HEADER ==================== --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 mr-4"
                     style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary);">
                    <i class="fas fa-home text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-home mr-2" style="color: var(--primary);"></i>
                        Homepage Content
                        @if($settings->hasCustomHomepage())
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">Custom</span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-info">Defaults</span>
                        @endif
                    </h2>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-sliders-h mr-2"></i>
                        Configure the public homepage, login page, and AD space
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" id="openPreviewBtn"
                        class="btn-info px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-eye mr-2"></i> Preview
                </button>
                <a href="{{ route('admin.system-settings.index') }}"
                   class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
            </div>
        </div>
    </div>

    {{-- Flash: success --}}
    @if(session('success'))
        <div class="card">
            <div class="p-4 rounded-lg flex items-center"
                 style="background-color: rgba(var(--success-rgb), 0.1); border-left: 4px solid var(--success);">
                <i class="fas fa-check-circle mr-3 text-xl" style="color: var(--success);"></i>
                <span style="color: var(--text-primary);">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- Flash: error --}}
    @if(session('error'))
        <div class="card">
            <div class="p-4 rounded-lg flex items-center"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <i class="fas fa-times-circle mr-3 text-xl" style="color: var(--danger);"></i>
                <span style="color: var(--text-primary);">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Validation errors --}}
    @if($errors->any())
        <div class="card">
            <div class="p-4 rounded-lg"
                 style="background-color: rgba(var(--danger-rgb), 0.1); border-left: 4px solid var(--danger);">
                <p class="font-semibold mb-2" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Please fix the following:
                </p>
                <ul class="text-sm list-disc pl-6" style="color: var(--text-primary);">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ============================================================
         TAB NAVIGATION — Homepage / Login Page / AD Space
         ============================================================ --}}
    <div class="card">
        <div class="flex flex-wrap gap-1 p-2" role="tablist">
            @php
                $tabs = [
                    'homepage' => ['Homepage',      'fas fa-home'],
                    'login'    => ['Login Page',    'fas fa-sign-in-alt'],
                    'ads'      => ['AD Space',      'fas fa-bullhorn'],
                ];
            @endphp
            @foreach($tabs as $key => [$label, $icon])
                <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
                   class="px-4 py-2.5 rounded-lg text-sm font-medium inline-flex items-center transition-all"
                   style="background: {{ $activeTab === $key ? 'var(--primary)' : 'transparent' }};
                          color: {{ $activeTab === $key ? 'white' : 'var(--text-secondary)' }};">
                    <i class="{{ $icon }} mr-2"></i> {{ $label }}
                    @if($key === 'ads' && $adStats['pending_rented'] > 0)
                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-semibold"
                              style="background: rgba(var(--warning-rgb), 0.25); color: {{ $activeTab === 'ads' ? 'white' : 'var(--warning)' }};">
                            {{ $adStats['pending_rented'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- ============================================================
         TAB: HOMEPAGE (existing content)
         ============================================================ --}}
    @if($activeTab === 'homepage')

        {{-- HERO BACKGROUND --}}
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--primary);">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-image text-xl" style="color: var(--primary);"></i>
                    </div>
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                        Homepage Hero Background Image
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <div class="rounded-lg overflow-hidden border-2"
                             style="border-color: var(--border-color); height: 220px; position: relative;">
                            <img id="bg-preview-img"
                                 src="{{ $heroUrl ?? 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=70' }}"
                                 alt="Hero background"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Recommended: 1920×1080, under 5 MB. JPG, PNG, or WebP.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <form action="{{ route('admin.homepage-content.background.upload') }}"
                              method="POST" enctype="multipart/form-data" id="bg-upload-form">
                            @csrf
                            <input type="file" name="hero_background" id="bg-file-input"
                                   accept="image/jpeg,image/png,image/webp" class="hidden">
                            <button type="button" id="bg-upload-btn"
                                    class="btn-primary w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                                <i class="fas fa-upload mr-2"></i> Upload New Image
                            </button>
                        </form>

                        @if($heroUrl)
                            <form action="{{ route('admin.homepage-content.background.remove') }}" method="POST"
                                  onsubmit="return confirm('Remove the custom hero background? The default image will be used.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="btn-danger w-full px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center">
                                    <i class="fas fa-trash mr-2"></i> Remove Image
                                </button>
                            </form>
                        @endif

                        <div class="p-3 rounded text-xs"
                             style="background-color: rgba(var(--info-rgb), 0.05);
                                    border: 1px solid rgba(var(--info-rgb), 0.15);">
                            <p style="color: var(--text-secondary);">
                                <i class="fas fa-lightbulb mr-1" style="color: var(--info);"></i>
                                Use a photo with an area of relative darkness (bottom-center) so the
                                white hero text remains readable.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MAIN HOMEPAGE FORM --}}
        <form action="{{ route('admin.homepage-content.update') }}" method="POST" id="homepage-form">
            @csrf
            @method('PUT')

            {{-- HERO TEXT --}}
            <div class="card mb-6">
                <div class="p-6" style="border-top: 4px solid var(--secondary);">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-heading text-xl" style="color: var(--secondary);"></i>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Hero Text & Buttons
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Hero Title
                            </label>
                            <input type="text" name="hero[title]"
                                   value="{{ old('hero.title', $config['hero']['title']) }}"
                                   placeholder="{{ $settings->system_name }}"
                                   maxlength="120"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                            @error('hero.title')
                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Leave blank to use the system name: <strong>{{ $settings->system_name }}</strong>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Hero Subtitle
                            </label>
                            <textarea name="hero[subtitle]" rows="3" maxlength="500"
                                      class="form-input w-full px-3 py-2 rounded-lg">{{ old('hero.subtitle', $config['hero']['subtitle']) }}</textarea>
                            @error('hero.subtitle')
                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Primary Button Text
                                </label>
                                <input type="text" name="hero[cta_primary]"
                                       value="{{ old('hero.cta_primary', $config['hero']['cta_primary']) }}"
                                       maxlength="80"
                                       class="form-input w-full px-3 py-2 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    Secondary Button Text
                                </label>
                                <input type="text" name="hero[cta_secondary]"
                                       value="{{ old('hero.cta_secondary', $config['hero']['cta_secondary']) }}"
                                       maxlength="80"
                                       class="form-input w-full px-3 py-2 rounded-lg">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- THEME CUSTOMIZATION --}}
            <div class="card mb-6">
                <div class="p-6" style="border-top: 4px solid var(--primary);">
                    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-palette text-xl" style="color: var(--primary);"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                    Theme Customization
                                </h3>
                                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                    Colors, typography, hero styling, layout & buttons
                                </p>
                            </div>
                        </div>

                        @if($isCustomTheme)
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-warning">
                                <i class="fas fa-pencil-ruler mr-1"></i> Custom Theme
                            </span>
                        @elseif($currentPreset !== 'default')
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">
                                <i class="fas fa-check-circle mr-1"></i>
                                Preset: {{ $presets[$currentPreset]['name'] ?? ucfirst($currentPreset) }}
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-medium badge-info">
                                <i class="fas fa-info-circle mr-1"></i> Default Theme
                            </span>
                        @endif
                    </div>

                    {{-- PRESETS --}}
                    <div class="mb-6 p-4 rounded-lg"
                         style="background-color: rgba(var(--secondary-rgb), 0.04);
                                border: 1px solid var(--border-color);">
                        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                            <p class="text-sm font-medium" style="color: var(--text-primary);">
                                <i class="fas fa-magic mr-2" style="color: var(--secondary);"></i>
                                Quick Presets
                            </p>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Click any preset to instantly apply its look
                            </p>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            @foreach($presets as $key => $preset)
                                @php $isActive = ($currentPreset === $key); @endphp
                                <button type="button"
                                        class="theme-preset-btn p-3 rounded-lg text-center transition-all"
                                        data-preset="{{ $key }}"
                                        style="background: {{ $isActive ? 'linear-gradient(135deg, rgba(var(--primary-rgb), 0.15), rgba(var(--secondary-rgb), 0.15))' : 'var(--card-bg)' }};
                                               border: 2px solid {{ $isActive ? 'var(--primary)' : 'var(--border-color)' }};
                                               cursor: pointer;">
                                    <div class="flex justify-center gap-1 mb-2">
                                        <span style="width: 16px; height: 16px; border-radius: 50%;
                                                     background-color: {{ $preset['values']['colors']['primary'] }};
                                                     border: 1px solid rgba(0,0,0,0.15);"></span>
                                        <span style="width: 16px; height: 16px; border-radius: 50%;
                                                     background-color: {{ $preset['values']['colors']['secondary'] }};
                                                     border: 1px solid rgba(0,0,0,0.15);"></span>
                                        <span style="width: 16px; height: 16px; border-radius: 50%;
                                                     background-color: {{ $preset['values']['colors']['accent'] }};
                                                     border: 1px solid rgba(0,0,0,0.15);"></span>
                                    </div>
                                    <p class="text-xs font-semibold" style="color: var(--text-primary);">{{ $preset['name'] }}</p>
                                    <p class="text-xs mt-1" style="color: var(--text-secondary); line-height: 1.2;">
                                        {{ Str::limit($preset['desc'], 32) }}
                                    </p>
                                    @if($isActive)
                                        <p class="text-xs mt-1 font-semibold" style="color: var(--primary);">
                                            <i class="fas fa-check"></i> Active
                                        </p>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <input type="hidden" name="theme[preset]" id="theme-preset-input"
                               value="{{ $currentPreset }}">

                        @if($currentPreset !== 'default' || $isCustomTheme)
                            <div class="mt-3 text-right">
                                <button type="button" id="resetThemeBtn"
                                        class="btn-outline px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                        style="border: 1px solid var(--primary); color: var(--primary);">
                                    <i class="fas fa-undo mr-1"></i> Reset to Default Theme
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- COLORS --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        @php
                            $colorFields = [
                                'primary'   => ['Primary Color',   '#3b82f6'],
                                'secondary' => ['Secondary Color', '#8b5cf6'],
                                'accent'    => ['Accent Color',    '#06b6d4'],
                            ];
                        @endphp
                        @foreach($colorFields as $key => $meta)
                            @php
                                $label   = $meta[0];
                                $default = $meta[1];
                                $current = old("theme.colors.{$key}", $themeColors[$key] ?? '');
                            @endphp
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    {{ $label }}
                                </label>
                                <div class="flex gap-2">
                                    <input type="color"
                                           id="color-{{ $key }}-picker"
                                           value="{{ $current !== '' ? $current : $default }}"
                                           data-target="color-{{ $key }}-text"
                                           class="theme-color-picker rounded-lg"
                                           style="width: 48px; height: 42px; cursor: pointer;
                                                  border: 1px solid var(--border-color); padding: 2px;">
                                    <input type="text"
                                           name="theme[colors][{{ $key }}]"
                                           id="color-{{ $key }}-text"
                                           value="{{ $current }}"
                                           placeholder="Auto ({{ $default }})"
                                           maxlength="9"
                                           class="form-input flex-1 px-3 py-2 rounded-lg font-mono text-sm">
                                </div>
                                @error("theme.colors.{$key}")
                                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    {{-- GRID: Typography | Hero | Layout | Buttons --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Typography --}}
                        <div class="p-4 rounded-lg"
                             style="background-color: rgba(var(--secondary-rgb), 0.02);
                                    border: 1px solid var(--border-color);">
                            <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-font mr-2" style="color: var(--secondary);"></i> Typography
                            </p>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Font Family
                            </label>
                            @php $currentFamily = theme_val($theme, $themeDefaults, 'fonts', 'family', 'system'); @endphp
                            <select name="theme[fonts][family]"
                                    class="form-input w-full px-3 py-2 rounded-lg mb-3">
                                @foreach([
                                    'system'   => 'System Default',
                                    'inter'    => 'Inter (Modern)',
                                    'poppins'  => 'Poppins (Friendly)',
                                    'roboto'   => 'Roboto (Neutral)',
                                    'playfair' => 'Playfair Display (Elegant)',
                                ] as $optValue => $optLabel)
                                    <option value="{{ $optValue }}" {{ $currentFamily === $optValue ? 'selected' : '' }}>
                                        {{ $optLabel }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                        Base Font Size
                                    </label>
                                    @php $currentSize = (int) theme_val($theme, $themeDefaults, 'fonts', 'base_size', 16); @endphp
                                    <select name="theme[fonts][base_size]"
                                            class="form-input w-full px-3 py-2 rounded-lg">
                                        @foreach([14, 15, 16, 17, 18, 20] as $sizeOpt)
                                            <option value="{{ $sizeOpt }}" {{ $currentSize === $sizeOpt ? 'selected' : '' }}>
                                                {{ $sizeOpt }}px
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                        Heading Weight
                                    </label>
                                    @php $currentWeight = (string) theme_val($theme, $themeDefaults, 'fonts', 'heading_weight', '700'); @endphp
                                    <select name="theme[fonts][heading_weight]"
                                            class="form-input w-full px-3 py-2 rounded-lg">
                                        @foreach(['400' => 'Regular', '500' => 'Medium', '600' => 'Semibold', '700' => 'Bold', '800' => 'Black'] as $wValue => $wLabel)
                                            <option value="{{ $wValue }}" {{ $currentWeight === (string) $wValue ? 'selected' : '' }}>
                                                {{ $wLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Hero Styling --}}
                        <div class="p-4 rounded-lg"
                             style="background-color: rgba(var(--secondary-rgb), 0.02);
                                    border: 1px solid var(--border-color);">
                            <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-image mr-2" style="color: var(--secondary);"></i> Hero Styling
                            </p>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Height
                            </label>
                            @php $currentHeight = theme_val($theme, $themeDefaults, 'hero', 'height', 'standard'); @endphp
                            <select name="theme[hero][height]"
                                    class="form-input w-full px-3 py-2 rounded-lg mb-3">
                                @foreach([
                                    'compact'  => 'Compact (55vh)',
                                    'standard' => 'Standard (75vh)',
                                    'tall'     => 'Tall (90vh)',
                                    'full'     => 'Full Screen (100vh)',
                                ] as $hValue => $hLabel)
                                    <option value="{{ $hValue }}" {{ $currentHeight === $hValue ? 'selected' : '' }}>
                                        {{ $hLabel }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Text Alignment
                            </label>
                            @php $currentAlign = theme_val($theme, $themeDefaults, 'hero', 'text_align', 'center'); @endphp
                            <select name="theme[hero][text_align]"
                                    class="form-input w-full px-3 py-2 rounded-lg mb-3">
                                @foreach(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $alignValue => $alignLabel)
                                    <option value="{{ $alignValue }}" {{ $currentAlign === $alignValue ? 'selected' : '' }}>
                                        {{ $alignLabel }}
                                    </option>
                                @endforeach
                            </select>

                            @php $currentOpacity = theme_val($theme, $themeDefaults, 'hero', 'overlay_opacity', 0.35); @endphp
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Overlay Opacity:
                                <span id="overlay-opacity-value" class="font-semibold">{{ $currentOpacity }}</span>
                            </label>
                            <input type="range"
                                   name="theme[hero][overlay_opacity]"
                                   id="overlay-opacity-slider"
                                   min="0" max="1" step="0.05"
                                   value="{{ $currentOpacity }}"
                                   class="w-full">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Lower = brighter image · Higher = darker (better text contrast)
                            </p>
                        </div>

                        {{-- Layout --}}
                        <div class="p-4 rounded-lg"
                             style="background-color: rgba(var(--secondary-rgb), 0.02);
                                    border: 1px solid var(--border-color);">
                            <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-th-large mr-2" style="color: var(--secondary);"></i> Layout
                            </p>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Container Width
                            </label>
                            @php $currentWidth = (int) theme_val($theme, $themeDefaults, 'layout', 'container_width', 1200); @endphp
                            <select name="theme[layout][container_width]"
                                    class="form-input w-full px-3 py-2 rounded-lg mb-3">
                                @foreach([
                                    1100 => 'Narrow (1100px)',
                                    1200 => 'Standard (1200px)',
                                    1320 => 'Wide (1320px)',
                                    1440 => 'Extra Wide (1440px)',
                                ] as $cwValue => $cwLabel)
                                    <option value="{{ $cwValue }}" {{ $currentWidth === $cwValue ? 'selected' : '' }}>
                                        {{ $cwLabel }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Section Spacing
                            </label>
                            @php $currentPadding = theme_val($theme, $themeDefaults, 'layout', 'section_padding', 'normal'); @endphp
                            <select name="theme[layout][section_padding]"
                                    class="form-input w-full px-3 py-2 rounded-lg mb-3">
                                @foreach(['compact' => 'Compact', 'normal' => 'Normal', 'spacious' => 'Spacious'] as $spValue => $spLabel)
                                    <option value="{{ $spValue }}" {{ $currentPadding === $spValue ? 'selected' : '' }}>
                                        {{ $spLabel }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Corner Radius
                            </label>
                            @php $currentRadius = theme_val($theme, $themeDefaults, 'layout', 'radius_style', 'rounded'); @endphp
                            <select name="theme[layout][radius_style]"
                                    class="form-input w-full px-3 py-2 rounded-lg">
                                @foreach([
                                    'sharp'   => 'Sharp (0px)',
                                    'rounded' => 'Rounded (0.75rem)',
                                    'pill'    => 'Pill (2rem)',
                                ] as $rValue => $rLabel)
                                    <option value="{{ $rValue }}" {{ $currentRadius === $rValue ? 'selected' : '' }}>
                                        {{ $rLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Buttons --}}
                        <div class="p-4 rounded-lg"
                             style="background-color: rgba(var(--secondary-rgb), 0.02);
                                    border: 1px solid var(--border-color);">
                            <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-mouse-pointer mr-2" style="color: var(--secondary);"></i> Buttons
                            </p>

                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Shape
                            </label>
                            @php $currentShape = theme_val($theme, $themeDefaults, 'buttons', 'shape', 'rounded'); @endphp
                            <select name="theme[buttons][shape]"
                                    class="form-input w-full px-3 py-2 rounded-lg mb-3">
                                @foreach(['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square'] as $shValue => $shLabel)
                                    <option value="{{ $shValue }}" {{ $currentShape === $shValue ? 'selected' : '' }}>
                                        {{ $shLabel }}
                                    </option>
                                @endforeach
                            </select>

                            @php $currentShadow = (bool) theme_val($theme, $themeDefaults, 'buttons', 'shadow', true); @endphp
                            <label class="flex items-center gap-2 cursor-pointer mt-4">
                                <input type="hidden" name="theme[buttons][shadow]" value="0">
                                <input type="checkbox"
                                       name="theme[buttons][shadow]"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $currentShadow ? 'checked' : '' }}>
                                <span class="text-sm" style="color: var(--text-primary);">
                                    Enable button shadows
                                </span>
                            </label>

                            <div class="mt-3 p-2 rounded text-xs"
                                 style="background-color: rgba(var(--info-rgb), 0.05);
                                        border: 1px solid rgba(var(--info-rgb), 0.15);">
                                <p style="color: var(--text-secondary);">
                                    <i class="fas fa-lightbulb mr-1" style="color: var(--info);"></i>
                                    Shadows add subtle depth on hover. Turn off for a flatter, more minimal look.
                                </p>
                            </div>
                        </div>

                        {{-- Hero Text Color --}}
                        <div class="p-4 rounded-lg md:col-span-2"
                             style="background-color: rgba(var(--secondary-rgb), 0.02);
                                    border: 1px solid var(--border-color);">
                            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tint mr-2" style="color: var(--secondary);"></i>
                                Hero Text Color
                            </label>
                            @php $currentHeroText = old('theme.colors.hero_text', $themeColors['hero_text'] ?? '#ffffff'); @endphp
                            <div class="flex gap-2 max-w-xs">
                                <input type="color"
                                       id="color-hero_text-picker"
                                       value="{{ $currentHeroText }}"
                                       data-target="color-hero_text-text"
                                       class="theme-color-picker rounded-lg"
                                       style="width: 48px; height: 42px; cursor: pointer;
                                              border: 1px solid var(--border-color); padding: 2px;">
                                <input type="text"
                                       name="theme[colors][hero_text]"
                                       id="color-hero_text-text"
                                       value="{{ $currentHeroText }}"
                                       maxlength="9"
                                       class="form-input flex-1 px-3 py-2 rounded-lg font-mono text-sm">
                            </div>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                Choose a color with strong contrast against your hero background image.
                            </p>
                        </div>
                    </div>

                    @if($isCustomTheme)
                        <div class="mt-4 p-3 rounded-lg"
                             style="background-color: rgba(var(--warning-rgb), 0.1);
                                    border-left: 4px solid var(--warning);">
                            <p class="text-xs" style="color: var(--warning);">
                                <i class="fas fa-info-circle mr-1"></i>
                                You've manually customized the theme. Clicking a preset above will
                                <strong>overwrite</strong> your custom colors and fonts.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- SECTION VISIBILITY --}}
            <div class="card mb-6">
                <div class="p-6" style="border-top: 4px solid var(--info);">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-eye text-xl" style="color: var(--info);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                Sections Visibility
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Toggle which sections appear on the homepage
                            </p>
                        </div>
                    </div>

                    @php
                        $sectionLabels = [
                            'how_it_works'    => ['How It Works',   'fas fa-list-ol'],
                            'services'        => ['Services',       'fas fa-cogs'],
                            'benefits'        => ['Benefits',       'fas fa-star'],
                            'stats'           => ['Stats / Impact', 'fas fa-chart-bar'],
                            'testimonials'    => ['Testimonials',   'fas fa-comments'],
                            'payment_methods' => ['Payment Methods','fas fa-credit-card'],
                            'faq'             => ['FAQ',            'fas fa-question-circle'],
                            'cta'             => ['Final CTA',      'fas fa-bullhorn'],
                            'contact'         => ['Contact',        'fas fa-envelope'],
                        ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach($sectionLabels as $key => $meta)
                            @php
                                $label = $meta[0];
                                $icon  = $meta[1];
                                $on    = isset($config['sections'][$key]) ? (bool) $config['sections'][$key] : true;
                            @endphp
                            <label class="flex items-center justify-between p-3 rounded-lg cursor-pointer"
                                   style="background: rgba(var(--secondary-rgb), 0.04);
                                          border: 1px solid var(--border-color);">
                                <span class="flex items-center text-sm" style="color: var(--text-primary);">
                                    <i class="{{ $icon }} mr-2" style="color: var(--secondary);"></i>
                                    {{ $label }}
                                </span>
                                <input type="hidden" name="sections[{{ $key }}]" value="0">
                                <input type="checkbox"
                                       name="sections[{{ $key }}]"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $on ? 'checked' : '' }}>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- SOCIAL MEDIA --}}
            <div class="card mb-6">
                <div class="p-6" style="border-top: 4px solid var(--success);">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-share-alt text-xl" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                Homepage Social Media Links
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Leave blank to hide an icon
                            </p>
                        </div>
                    </div>

                    @php
                        $socialMeta = [
                            'facebook'  => ['Facebook',   'fab fa-facebook-f',  '#1877F2', 'https://facebook.com/yourpage'],
                            'twitter'   => ['Twitter / X','fab fa-x-twitter',   '#000000', 'https://x.com/yourhandle'],
                            'instagram' => ['Instagram',  'fab fa-instagram',   '#E4405F', 'https://instagram.com/yourhandle'],
                            'whatsapp'  => ['WhatsApp',   'fab fa-whatsapp',    '#25D366', 'https://wa.me/233...'],
                            'linkedin'  => ['LinkedIn',   'fab fa-linkedin-in', '#0A66C2', 'https://linkedin.com/company/...'],
                            'youtube'   => ['YouTube',    'fab fa-youtube',     '#FF0000', 'https://youtube.com/@channel'],
                            'tiktok'    => ['TikTok',     'fab fa-tiktok',      '#000000', 'https://tiktok.com/@handle'],
                        ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($socialMeta as $key => $meta)
                            @php
                                $sLabel       = $meta[0];
                                $sIcon        = $meta[1];
                                $sColor       = $meta[2];
                                $sPlaceholder = $meta[3];
                                $sValue       = old("social.{$key}", $config['social'][$key] ?? '');
                            @endphp
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    <i class="{{ $sIcon }} mr-2" style="color: {{ $sColor }};"></i>
                                    {{ $sLabel }}
                                </label>
                                <input type="text"
                                       name="social[{{ $key }}]"
                                       value="{{ $sValue }}"
                                       placeholder="{{ $sPlaceholder }}"
                                       maxlength="255"
                                       class="form-input w-full px-3 py-2 rounded-lg">
                                @error("social.{$key}")
                                    <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- CONTACT --}}
            <div class="card mb-6">
                <div class="p-6" style="border-top: 4px solid var(--warning);">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-address-card text-xl" style="color: var(--warning);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                Contact Information
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Leave blank to fall back to system defaults
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Public Email
                            </label>
                            <input type="email" name="contact[email]"
                                   value="{{ old('contact.email', $config['contact']['email']) }}"
                                   placeholder="{{ $settings->system_email }}"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                            @error('contact.email')
                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Public Phone
                            </label>
                            <input type="text" name="contact[phone]"
                                   value="{{ old('contact.phone', $config['contact']['phone']) }}"
                                   placeholder="{{ $settings->system_phone }}"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Address
                            </label>
                            <input type="text" name="contact[address]"
                                   value="{{ old('contact.address', $config['contact']['address']) }}"
                                   placeholder="Estate Office, {{ $settings->system_name }}"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Office Hours (one line per row)
                            </label>
                            <textarea name="contact[hours]" rows="3"
                                      class="form-input w-full px-3 py-2 rounded-lg">{{ old('contact.hours', $config['contact']['hours']) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- STICKY ACTIONS (homepage tab only) --}}
        <div class="card" style="position: sticky; bottom: 0; z-index: 20;">
            <div class="p-4 flex justify-between items-center flex-wrap gap-3">
                <button type="button" id="openPreviewBtnBottom"
                        class="btn-outline px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="border: 1px solid var(--primary); color: var(--primary);">
                    <i class="fas fa-eye mr-2"></i> Preview Homepage
                </button>
                <div class="flex gap-3 flex-wrap">
                    <a href="{{ route('admin.system-settings.index') }}"
                       class="btn-secondary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        Cancel
                    </a>
                    <button type="submit" form="homepage-form"
                            class="btn-primary px-6 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================
         TAB: LOGIN PAGE (existing content — unchanged)
         ============================================================ --}}
    @if($activeTab === 'login')
        <div class="card mb-6">
            <div class="p-6" style="border-top: 4px solid var(--secondary);">
                <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-sign-in-alt text-xl" style="color: var(--secondary);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                Login Page
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Customize the sign-in experience — background, welcome panel, socials
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" id="openLoginPreviewBtn"
                                class="btn-info px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center">
                            <i class="fas fa-eye mr-1"></i> Preview Login
                        </button>
                        @if($loginIsCustomised || $loginBgUrl)
                            <button type="button" id="resetLoginBtn"
                                    class="btn-outline px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center"
                                    style="border: 1px solid var(--warning); color: var(--warning);">
                                <i class="fas fa-undo mr-1"></i> Reset
                            </button>
                        @endif
                    </div>
                </div>

                {{-- LOGIN BACKGROUND IMAGE --}}
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Login Background Image
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <div class="rounded-lg overflow-hidden border-2"
                                 style="border-color: var(--border-color); height: 180px; position: relative;">
                                <img id="login-bg-preview-img"
                                     src="{{ $loginBgUrl ?? 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=70' }}"
                                     alt="Login background"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                                @if(!$loginBgUrl)
                                    <span class="px-2 py-1 rounded text-xs"
                                          style="position: absolute; top: 12px; right: 12px;
                                                 background: rgba(var(--warning-rgb), 0.9); color: white;">
                                        <i class="fas fa-image mr-1"></i> Default
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs"
                                          style="position: absolute; top: 12px; right: 12px;
                                                 background: rgba(var(--success-rgb), 0.9); color: white;">
                                        <i class="fas fa-check mr-1"></i> Custom
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs mt-2" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Recommended: 2076×1200 or similar. Under 5 MB. JPG, PNG, or WebP.
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <form action="{{ route('admin.homepage-content.login.background.upload') }}"
                                  method="POST" enctype="multipart/form-data" id="login-bg-upload-form">
                                @csrf
                                <input type="file" name="login_background" id="login-bg-file-input"
                                       accept="image/jpeg,image/png,image/webp" class="hidden">
                                <button type="button" id="login-bg-upload-btn"
                                        class="btn-primary w-full px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center">
                                    <i class="fas fa-upload mr-2"></i> Upload Image
                                </button>
                            </form>

                            @if($loginBgUrl)
                                <form action="{{ route('admin.homepage-content.login.background.remove') }}" method="POST"
                                      onsubmit="return confirm('Remove the login background? The default will be used.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-danger w-full px-3 py-2 rounded-lg text-sm font-medium inline-flex items-center justify-center">
                                        <i class="fas fa-trash mr-2"></i> Remove
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- LOGIN PAGE FORM --}}
                <form action="{{ route('admin.homepage-content.login.update') }}"
                      method="POST" id="login-page-form">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                External Background URL (fallback)
                            </label>
                            <input type="url"
                                   name="login[background_url]"
                                   value="{{ old('login.background_url', $loginMerged['background_url'] ?? '') }}"
                                   placeholder="https://images.unsplash.com/photo-..."
                                   class="form-input w-full px-3 py-2 rounded-lg text-sm">
                            @error('login.background_url')
                                <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                            @enderror
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Used if no image is uploaded above.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Default Theme
                            </label>
                            @php $curLoginTheme = old('login.default_theme', $loginMerged['default_theme'] ?? 'dark'); @endphp
                            <select name="login[default_theme]"
                                    class="form-input w-full px-3 py-2 rounded-lg">
                                <option value="dark"   {{ $curLoginTheme === 'dark'   ? 'selected' : '' }}>Dark (default)</option>
                                <option value="light"  {{ $curLoginTheme === 'light'  ? 'selected' : '' }}>Light</option>
                                <option value="system" {{ $curLoginTheme === 'system' ? 'selected' : '' }}>Follow system</option>
                            </select>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                User's saved preference always wins over this default.
                            </p>
                        </div>

                        <div>
                            @php $curLoginOpacity = old('login.overlay_opacity', $loginMerged['overlay_opacity'] ?? 0.85); @endphp
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Overlay Darkness:
                                <span id="login-overlay-opacity-value" class="font-semibold">{{ $curLoginOpacity }}</span>
                            </label>
                            <input type="range"
                                   name="login[overlay_opacity]"
                                   id="login-overlay-opacity-slider"
                                   min="0" max="1" step="0.05"
                                   value="{{ $curLoginOpacity }}"
                                   class="w-full">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Higher = darker overlay for better text contrast.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                System Name Override
                            </label>
                            <input type="text"
                                   name="login[system_name_override]"
                                   value="{{ old('login.system_name_override', $loginMerged['system_name_override'] ?? '') }}"
                                   placeholder="{{ $settings->system_name }}"
                                   maxlength="120"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Leave blank to use the system name.
                            </p>
                        </div>
                    </div>

                    {{-- Toggles --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        @php
                            $loginToggles = [
                                'show_welcome_panel' => ['Welcome Panel',  'Show the left welcome panel',  isset($loginMerged['show_welcome_panel'])  ? (bool) $loginMerged['show_welcome_panel']  : true],
                                'show_social_icons'  => ['Social Sign-in', 'Show Google/Microsoft buttons', isset($loginMerged['show_social_icons'])  ? (bool) $loginMerged['show_social_icons']  : true],
                                'show_cookie_banner' => ['Cookie Banner',  'Show cookie consent banner',    isset($loginMerged['show_cookie_banner']) ? (bool) $loginMerged['show_cookie_banner'] : true],
                            ];
                        @endphp
                        @foreach($loginToggles as $key => $meta)
                            <label class="flex items-center justify-between p-3 rounded-lg cursor-pointer"
                                   style="background: rgba(var(--secondary-rgb), 0.04);
                                          border: 1px solid var(--border-color);">
                                <div>
                                    <div class="text-sm font-medium" style="color: var(--text-primary);">{{ $meta[0] }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $meta[1] }}</div>
                                </div>
                                <input type="hidden" name="login[{{ $key }}]" value="0">
                                <input type="checkbox"
                                       name="login[{{ $key }}]"
                                       value="1"
                                       class="w-5 h-5"
                                       {{ $meta[2] ? 'checked' : '' }}>
                            </label>
                        @endforeach
                    </div>

                    {{-- Welcome panel --}}
                    <div class="p-4 rounded-lg mb-4"
                         style="background-color: rgba(var(--secondary-rgb), 0.02);
                                border: 1px solid var(--border-color);">
                        <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-columns mr-2" style="color: var(--secondary);"></i>
                            Welcome Panel Content
                        </p>

                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    Welcome Title
                                </label>
                                <input type="text"
                                       name="login[welcome_title]"
                                       value="{{ old('login.welcome_title', $loginMerged['welcome_title'] ?? $loginDefaults['welcome_title']) }}"
                                       maxlength="120"
                                       class="form-input w-full px-3 py-2 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                    Welcome Subtitle
                                </label>
                                <textarea name="login[welcome_subtitle]"
                                          rows="2" maxlength="400"
                                          class="form-input w-full px-3 py-2 rounded-lg">{{ old('login.welcome_subtitle', $loginMerged['welcome_subtitle'] ?? $loginDefaults['welcome_subtitle']) }}</textarea>
                            </div>
                        </div>

                        <p class="text-sm font-semibold mb-2 mt-4" style="color: var(--text-primary);">
                            <i class="fas fa-list mr-2" style="color: var(--secondary);"></i>
                            Features (up to 6)
                        </p>
                        <p class="text-xs mb-2" style="color: var(--text-secondary);">
                            <i class="fas fa-info-circle mr-1"></i>
                            Pick an icon from the dropdown and type the feature text next to it.
                        </p>

                        <template id="login-feature-row-template">
                            <div class="flex gap-2 login-feature-row items-center">
                                <select
                                        class="form-input px-2 py-2 rounded-lg text-sm login-feature-icon-select"
                                        style="width: 220px;"
                                        aria-label="Feature icon"
                                        data-role="icon-select">
                                    <option value="">— Pick an icon —</option>
                                    @foreach($featureIconGroups as $groupName => $icons)
                                        <optgroup label="{{ $groupName }}">
                                            @foreach($icons as $cls => $lbl)
                                                <option value="{{ $cls }}" data-icon="{{ $cls }}">
                                                    {{ $lbl }} ({{ str_replace('fa-', '', $cls) }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>

                                <span class="login-feature-icon-preview inline-flex items-center justify-center rounded-lg"
                                      style="width: 42px; height: 42px; flex-shrink: 0;
                                             background: rgba(var(--secondary-rgb), 0.08);
                                             border: 1px solid var(--border-color);
                                             color: var(--secondary);"
                                      aria-hidden="true">
                                    <i class="fas fa-question"></i>
                                </span>

                                <input type="text"
                                       placeholder="Feature text"
                                       maxlength="120"
                                       class="form-input flex-1 px-3 py-2 rounded-lg text-sm"
                                       data-role="text"
                                       aria-label="Feature text">

                                <button type="button"
                                        class="login-feature-remove btn-outline px-3 rounded-lg text-sm"
                                        style="border: 1px solid var(--danger); color: var(--danger); height: 42px;"
                                        aria-label="Remove feature">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </template>

                        <div class="space-y-2" id="login-features-container" data-max="6">
                            @foreach($loginFeatures as $i => $feat)
                                @php
                                    $iconVal = (string) ($feat['icon'] ?? '');
                                    $textVal = (string) ($feat['text'] ?? '');
                                    $inCatalog = $iconVal !== '' && isset($knownFeatureIcons[$iconVal]);
                                @endphp
                                <div class="flex gap-2 login-feature-row items-center">
                                    @if($inCatalog || $iconVal === '')
                                        <select
                                                name="login[welcome_features][{{ $i }}][icon]"
                                                class="form-input px-2 py-2 rounded-lg text-sm login-feature-icon-select"
                                                style="width: 220px;"
                                                aria-label="Feature icon"
                                                data-role="icon-select">
                                            <option value="" {{ $iconVal === '' ? 'selected' : '' }}>— Pick an icon —</option>
                                            @foreach($featureIconGroups as $groupName => $icons)
                                                <optgroup label="{{ $groupName }}">
                                                    @foreach($icons as $cls => $lbl)
                                                        <option value="{{ $cls }}"
                                                                data-icon="{{ $cls }}"
                                                                {{ $iconVal === $cls ? 'selected' : '' }}>
                                                            {{ $lbl }} ({{ str_replace('fa-', '', $cls) }})
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    @else
                                        <div style="position: relative; width: 220px;">
                                            <input type="text"
                                                   name="login[welcome_features][{{ $i }}][icon]"
                                                   value="{{ $iconVal }}"
                                                   placeholder="fas fa-home"
                                                   class="form-input w-full px-3 py-2 rounded-lg font-mono text-sm"
                                                   style="padding-right: 2.5rem;"
                                                   aria-label="Custom feature icon">
                                            <span class="inline-flex items-center justify-center"
                                                  title="Custom icon class (legacy)"
                                                  style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
                                                         font-size: 10px; color: var(--text-secondary);">
                                                <i class="fas fa-pen"></i>
                                            </span>
                                        </div>
                                    @endif

                                    <span class="login-feature-icon-preview inline-flex items-center justify-center rounded-lg"
                                          style="width: 42px; height: 42px; flex-shrink: 0;
                                                 background: rgba(var(--secondary-rgb), 0.08);
                                                 border: 1px solid var(--border-color);
                                                 color: var(--secondary);"
                                          aria-hidden="true">
                                        <i class="{{ $iconVal !== '' ? $iconVal : 'fas fa-question' }}"></i>
                                    </span>

                                    <input type="text"
                                           name="login[welcome_features][{{ $i }}][text]"
                                           value="{{ $textVal }}"
                                           placeholder="Feature text"
                                           maxlength="120"
                                           class="form-input flex-1 px-3 py-2 rounded-lg text-sm"
                                           data-role="text"
                                           aria-label="Feature text">

                                    <button type="button"
                                            class="login-feature-remove btn-outline px-3 rounded-lg text-sm"
                                            style="border: 1px solid var(--danger); color: var(--danger); height: 42px;"
                                            aria-label="Remove feature">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-login-feature-btn"
                                class="btn-outline mt-2 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                style="border: 1px dashed var(--primary); color: var(--primary);">
                            <i class="fas fa-plus mr-1"></i> Add Feature
                        </button>
                    </div>

                    {{-- Footer socials --}}
                    <div class="p-4 rounded-lg mb-4"
                         style="background-color: rgba(var(--secondary-rgb), 0.02);
                                border: 1px solid var(--border-color);">
                        <p class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-share-alt mr-2" style="color: var(--secondary);"></i>
                            Footer Social Links
                        </p>

                        @php
                            $socialPlatforms = [
                                'facebook'  => 'Facebook',
                                'instagram' => 'Instagram',
                                'linkedin'  => 'LinkedIn',
                                'twitter'   => 'Twitter / X',
                                'whatsapp'  => 'WhatsApp',
                                'youtube'   => 'YouTube',
                                'tiktok'    => 'TikTok',
                            ];
                        @endphp
                        <div class="space-y-2">
                            @foreach($loginSocials as $i => $social)
                                <div class="flex gap-2">
                                    <select name="login[footer_socials][{{ $i }}][platform]"
                                            class="form-input px-3 py-2 rounded-lg text-sm"
                                            style="width: 160px;">
                                        <option value="">— Platform —</option>
                                        @foreach($socialPlatforms as $val => $lbl)
                                            <option value="{{ $val }}"
                                                {{ ($social['platform'] ?? '') === $val ? 'selected' : '' }}>
                                                {{ $lbl }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="url"
                                           name="login[footer_socials][{{ $i }}][url]"
                                           value="{{ $social['url'] ?? '' }}"
                                           placeholder="https://..."
                                           maxlength="255"
                                           class="form-input flex-1 px-3 py-2 rounded-lg text-sm">
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs mt-2" style="color: var(--text-secondary);">
                            Leave the URL blank to hide that icon on the login page.
                        </p>
                    </div>

                    {{-- LOGIN THEME CUSTOMIZATION --}}
                    <div class="p-4 rounded-lg mb-4"
                         style="background-color: rgba(var(--primary-rgb), 0.02);
                                border: 1px solid var(--border-color);">

                        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                     style="background-color: rgba(var(--primary-rgb), 0.1);">
                                    <i class="fas fa-palette" style="color: var(--primary);"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--text-primary);">
                                        Login Theme
                                    </p>
                                    <p class="text-xs" style="color: var(--text-secondary);">
                                        Colors, fonts, layout & buttons for the login page
                                    </p>
                                </div>
                            </div>

                            @if($isCustomLoginTheme)
                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-warning">
                                    <i class="fas fa-pencil-ruler mr-1"></i> Custom Login Theme
                                </span>
                            @elseif($currentLoginPreset !== 'default')
                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Preset: {{ $presets[$currentLoginPreset]['name'] ?? ucfirst($currentLoginPreset) }}
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-medium badge-info">
                                    <i class="fas fa-info-circle mr-1"></i> Default Login Theme
                                </span>
                            @endif
                        </div>

                        {{-- LOGIN PRESETS --}}
                        <div class="mb-4 p-3 rounded-lg"
                             style="background-color: rgba(var(--secondary-rgb), 0.04);
                                    border: 1px solid var(--border-color);">
                            <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                                <p class="text-xs font-medium" style="color: var(--text-primary);">
                                    <i class="fas fa-magic mr-1" style="color: var(--secondary);"></i>
                                    Quick Presets
                                </p>
                                <p class="text-xs" style="color: var(--text-secondary);">
                                    Same palette as the homepage — click to apply
                                </p>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                @foreach($presets as $key => $preset)
                                    @php $isActiveLoginPreset = ($currentLoginPreset === $key); @endphp
                                    <button type="button"
                                            class="login-theme-preset-btn p-2 rounded-lg text-center"
                                            data-preset="{{ $key }}"
                                            style="background: {{ $isActiveLoginPreset ? 'linear-gradient(135deg, rgba(var(--primary-rgb), 0.15), rgba(var(--secondary-rgb), 0.15))' : 'var(--card-bg)' }};
                                                   border: 2px solid {{ $isActiveLoginPreset ? 'var(--primary)' : 'var(--border-color)' }};
                                                   cursor: pointer;">
                                        <div class="flex justify-center gap-1 mb-1">
                                            <span style="width: 14px; height: 14px; border-radius: 50%;
                                                         background-color: {{ $preset['values']['colors']['primary'] }};
                                                         border: 1px solid rgba(0,0,0,0.15);"></span>
                                            <span style="width: 14px; height: 14px; border-radius: 50%;
                                                         background-color: {{ $preset['values']['colors']['secondary'] }};
                                                         border: 1px solid rgba(0,0,0,0.15);"></span>
                                            <span style="width: 14px; height: 14px; border-radius: 50%;
                                                         background-color: {{ $preset['values']['colors']['accent'] }};
                                                         border: 1px solid rgba(0,0,0,0.15);"></span>
                                        </div>
                                        <p class="text-xs font-semibold" style="color: var(--text-primary);">{{ $preset['name'] }}</p>
                                        @if($isActiveLoginPreset)
                                            <p class="text-xs mt-0.5 font-semibold" style="color: var(--primary);">
                                                <i class="fas fa-check"></i>
                                            </p>
                                        @endif
                                    </button>
                                @endforeach
                            </div>

                            <input type="hidden" name="login[theme][preset]" id="login-theme-preset-input"
                                   value="{{ $currentLoginPreset }}">

                            @if($currentLoginPreset !== 'default' || $isCustomLoginTheme)
                                <div class="mt-2 text-right">
                                    <button type="button" id="resetLoginThemeBtn"
                                            class="btn-outline px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                                            style="border: 1px solid var(--primary); color: var(--primary);">
                                        <i class="fas fa-undo mr-1"></i> Reset Login Theme
                                    </button>
                                </div>
                            @endif
                        </div>

                        {{-- LOGIN COLORS --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                            @php
                                $loginColorFields = [
                                    'primary'    => ['Primary',     '#2563eb'],
                                    'secondary'  => ['Secondary',   '#1e40af'],
                                    'accent'     => ['Accent',      '#f59e0b'],
                                    'panel_bg'   => ['Panel BG',    '#0f172a'],
                                    'panel_text' => ['Panel Text',  '#f8fafc'],
                                    'form_bg'    => ['Form BG',     '#ffffff'],
                                    'form_text'  => ['Form Text',   '#0f172a'],
                                ];
                            @endphp
                            @foreach($loginColorFields as $key => $meta)
                                @php
                                    $lLabel   = $meta[0];
                                    $lDefault = $meta[1];
                                    $lCurrent = old("login.theme.colors.{$key}", $loginThemeColors[$key] ?? '');
                                @endphp
                                <div>
                                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                        {{ $lLabel }}
                                    </label>
                                    <div class="flex gap-2">
                                        <input type="color"
                                               id="login-color-{{ $key }}-picker"
                                               value="{{ $lCurrent !== '' ? $lCurrent : $lDefault }}"
                                               data-target="login-color-{{ $key }}-text"
                                               data-login-theme-field="1"
                                               class="theme-color-picker rounded-lg"
                                               style="width: 42px; height: 38px; cursor: pointer;
                                                      border: 1px solid var(--border-color); padding: 2px;">
                                        <input type="text"
                                               name="login[theme][colors][{{ $key }}]"
                                               id="login-color-{{ $key }}-text"
                                               value="{{ $lCurrent }}"
                                               placeholder="Auto ({{ $lDefault }})"
                                               maxlength="9"
                                               class="form-input flex-1 px-2 py-1 rounded-lg font-mono text-xs">
                                    </div>
                                    @error("login.theme.colors.{$key}")
                                        <p class="text-xs mt-1" style="color: var(--danger);">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        {{-- LOGIN TYPOGRAPHY / LAYOUT / BUTTONS --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="p-3 rounded-lg"
                                 style="background-color: rgba(var(--secondary-rgb), 0.02);
                                        border: 1px solid var(--border-color);">
                                <p class="text-xs font-semibold mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-font mr-1" style="color: var(--secondary);"></i> Typography
                                </p>

                                @php $curLoginFamily = login_theme_val($loginTheme, $loginThemeDefaults, 'fonts', 'family', 'system'); @endphp
                                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Font Family</label>
                                <select name="login[theme][fonts][family]"
                                        class="form-input w-full px-2 py-1.5 rounded-lg text-sm mb-2">
                                    @foreach([
                                        'system'   => 'System Default',
                                        'inter'    => 'Inter (Modern)',
                                        'poppins'  => 'Poppins (Friendly)',
                                        'roboto'   => 'Roboto (Neutral)',
                                        'playfair' => 'Playfair Display (Elegant)',
                                    ] as $optValue => $optLabel)
                                        <option value="{{ $optValue }}" {{ $curLoginFamily === $optValue ? 'selected' : '' }}>
                                            {{ $optLabel }}
                                        </option>
                                    @endforeach
                                </select>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        @php $curLoginSize = (int) login_theme_val($loginTheme, $loginThemeDefaults, 'fonts', 'base_size', 16); @endphp
                                        <label class="block text-xs mb-1" style="color: var(--text-secondary);">Base Size</label>
                                        <select name="login[theme][fonts][base_size]"
                                                class="form-input w-full px-2 py-1.5 rounded-lg text-sm">
                                            @foreach([14, 15, 16, 17, 18, 20] as $sizeOpt)
                                                <option value="{{ $sizeOpt }}" {{ $curLoginSize === $sizeOpt ? 'selected' : '' }}>
                                                    {{ $sizeOpt }}px
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        @php $curLoginWeight = (string) login_theme_val($loginTheme, $loginThemeDefaults, 'fonts', 'heading_weight', '700'); @endphp
                                        <label class="block text-xs mb-1" style="color: var(--text-secondary);">Heading</label>
                                        <select name="login[theme][fonts][heading_weight]"
                                                class="form-input w-full px-2 py-1.5 rounded-lg text-sm">
                                            @foreach(['400' => 'Regular', '500' => 'Medium', '600' => 'Semibold', '700' => 'Bold', '800' => 'Black'] as $wValue => $wLabel)
                                                <option value="{{ $wValue }}" {{ $curLoginWeight === (string) $wValue ? 'selected' : '' }}>
                                                    {{ $wLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg"
                                 style="background-color: rgba(var(--secondary-rgb), 0.02);
                                        border: 1px solid var(--border-color);">
                                <p class="text-xs font-semibold mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-th-large mr-1" style="color: var(--secondary);"></i> Layout
                                </p>

                                @php $curLoginSplit = login_theme_val($loginTheme, $loginThemeDefaults, 'layout', 'split', 'panel-left'); @endphp
                                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Split</label>
                                <select name="login[theme][layout][split]"
                                        class="form-input w-full px-2 py-1.5 rounded-lg text-sm mb-2">
                                    @foreach([
                                        'panel-left'    => 'Welcome panel on the left',
                                        'panel-right'   => 'Welcome panel on the right',
                                        'single-column' => 'Single column (form only)',
                                    ] as $spValue => $spLabel)
                                        <option value="{{ $spValue }}" {{ $curLoginSplit === $spValue ? 'selected' : '' }}>
                                            {{ $spLabel }}
                                        </option>
                                    @endforeach
                                </select>

                                @php $curLoginPanelWidth = (int) login_theme_val($loginTheme, $loginThemeDefaults, 'layout', 'panel_width', 50); @endphp
                                <label class="block text-xs mb-1" style="color: var(--text-secondary);">
                                    Panel Width:
                                    <span id="login-panel-width-value" class="font-semibold">{{ $curLoginPanelWidth }}%</span>
                                </label>
                                <input type="range"
                                       name="login[theme][layout][panel_width]"
                                       id="login-panel-width-slider"
                                       min="30" max="70" step="5"
                                       value="{{ $curLoginPanelWidth }}"
                                       class="w-full mb-2">

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        @php $curLoginRadius = login_theme_val($loginTheme, $loginThemeDefaults, 'layout', 'radius_style', 'rounded'); @endphp
                                        <label class="block text-xs mb-1" style="color: var(--text-secondary);">Radius</label>
                                        <select name="login[theme][layout][radius_style]"
                                                class="form-input w-full px-2 py-1.5 rounded-lg text-sm">
                                            <option value="sharp"   {{ $curLoginRadius === 'sharp'   ? 'selected' : '' }}>Sharp</option>
                                            <option value="rounded" {{ $curLoginRadius === 'rounded' ? 'selected' : '' }}>Rounded</option>
                                            <option value="pill"    {{ $curLoginRadius === 'pill'    ? 'selected' : '' }}>Pill</option>
                                        </select>
                                    </div>
                                    <div>
                                        @php $curLoginCardShadow = (bool) login_theme_val($loginTheme, $loginThemeDefaults, 'layout', 'card_shadow', true); @endphp
                                        <label class="block text-xs mb-1" style="color: var(--text-secondary);">Card Shadow</label>
                                        <label class="flex items-center gap-2 mt-2">
                                            <input type="hidden" name="login[theme][layout][card_shadow]" value="0">
                                            <input type="checkbox"
                                                   name="login[theme][layout][card_shadow]"
                                                   value="1"
                                                   class="w-4 h-4"
                                                   {{ $curLoginCardShadow ? 'checked' : '' }}>
                                            <span class="text-xs" style="color: var(--text-primary);">Enable</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg md:col-span-2"
                                 style="background-color: rgba(var(--secondary-rgb), 0.02);
                                        border: 1px solid var(--border-color);">
                                <p class="text-xs font-semibold mb-2" style="color: var(--text-primary);">
                                    <i class="fas fa-mouse-pointer mr-1" style="color: var(--secondary);"></i> Buttons
                                </p>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        @php $curLoginBtnShape = login_theme_val($loginTheme, $loginThemeDefaults, 'buttons', 'shape', 'rounded'); @endphp
                                        <label class="block text-xs mb-1" style="color: var(--text-secondary);">Shape</label>
                                        <select name="login[theme][buttons][shape]"
                                                class="form-input w-full px-2 py-1.5 rounded-lg text-sm">
                                            <option value="rounded" {{ $curLoginBtnShape === 'rounded' ? 'selected' : '' }}>Rounded</option>
                                            <option value="pill"    {{ $curLoginBtnShape === 'pill'    ? 'selected' : '' }}>Pill</option>
                                            <option value="square"  {{ $curLoginBtnShape === 'square'  ? 'selected' : '' }}>Square</option>
                                        </select>
                                    </div>
                                    <div>
                                        @php $curLoginBtnShadow = (bool) login_theme_val($loginTheme, $loginThemeDefaults, 'buttons', 'shadow', true); @endphp
                                        <label class="block text-xs mb-1" style="color: var(--text-secondary);">Shadow</label>
                                        <label class="flex items-center gap-2 mt-2">
                                            <input type="hidden" name="login[theme][buttons][shadow]" value="0">
                                            <input type="checkbox"
                                                   name="login[theme][buttons][shadow]"
                                                   value="1"
                                                   class="w-4 h-4"
                                                   {{ $curLoginBtnShadow ? 'checked' : '' }}>
                                            <span class="text-xs" style="color: var(--text-primary);">Enable</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($isCustomLoginTheme)
                            <div class="mt-3 p-2 rounded-lg"
                                 style="background-color: rgba(var(--warning-rgb), 0.1);
                                        border-left: 4px solid var(--warning);">
                                <p class="text-xs" style="color: var(--warning);">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    You've customized the login theme. Clicking a preset above will
                                    <strong>overwrite</strong> your custom login colors and fonts.
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="submit"
                                class="btn-primary px-5 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-save mr-2"></i> Save Login Page
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ============================================================
         TAB: AD SPACE — settings + plans + campaigns
         ============================================================ --}}
    @if($activeTab === 'ads')

        {{-- ============ QUICK STATS ============ --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @php
                $statCards = [
                    ['Active',          $adStats['active'],          'success',  'fas fa-play-circle'],
                    ['Awaiting Payment',$adStats['pending_payment'], 'warning',  'fas fa-clock'],
                    ['Rented (Early)',  $adStats['rented_early'],    'info',     'fas fa-handshake'],
                    ['Expired',         $adStats['expired'],         'secondary','fas fa-calendar-times'],
                    ['Review Pending',  $adStats['pending_rented'],  'danger',   'fas fa-exclamation-circle'],
                    ['Revenue',         '₵' . number_format($adStats['total_revenue'], 2), 'primary', 'fas fa-coins'],
                ];
            @endphp
            @foreach($statCards as [$label, $value, $color, $icon])
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium" style="color: var(--text-secondary);">{{ $label }}</span>
                        <i class="{{ $icon }}" style="color: var(--{{ $color }});"></i>
                    </div>
                    <p class="text-xl font-bold" style="color: var(--{{ $color }});">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- ============ GLOBAL AD SETTINGS ============ --}}
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--primary);">
                <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-cog text-xl" style="color: var(--primary);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                AD Space Settings
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Control availability, limits, and channels
                            </p>
                        </div>
                    </div>

                    @if($adConfig['enabled'])
                        <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">
                            <i class="fas fa-check-circle mr-1"></i> Enabled
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-medium badge-danger">
                            <i class="fas fa-ban mr-1"></i> Disabled
                        </span>
                    @endif
                </div>

                <form action="{{ route('admin.homepage-content.ads.settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        {{-- Master toggle --}}
                        <label class="flex items-center justify-between p-3 rounded-lg cursor-pointer"
                               style="background: rgba(var(--secondary-rgb), 0.04);
                                      border: 1px solid var(--border-color);">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    Enable AD Space
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Master switch — off disables landlord campaigns entirely
                                </div>
                            </div>
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" class="w-5 h-5"
                                   {{ $adConfig['enabled'] ? 'checked' : '' }}>
                        </label>

                        {{-- Require admin approval --}}
                        <label class="flex items-center justify-between p-3 rounded-lg cursor-pointer"
                               style="background: rgba(var(--secondary-rgb), 0.04);
                                      border: 1px solid var(--border-color);">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    Require Admin Approval
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Campaigns must be reviewed before going live
                                </div>
                            </div>
                            <input type="hidden" name="require_admin_approval" value="0">
                            <input type="checkbox" name="require_admin_approval" value="1" class="w-5 h-5"
                                   {{ $adConfig['require_admin_approval'] ? 'checked' : '' }}>
                        </label>

                        {{-- Auto-approve "mark as rented" --}}
                        <label class="flex items-center justify-between p-3 rounded-lg cursor-pointer"
                               style="background: rgba(var(--secondary-rgb), 0.04);
                                      border: 1px solid var(--border-color);">
                            <div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    Auto-Approve "Mark as Rented"
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    Skip admin review for stop-request (use with caution)
                                </div>
                            </div>
                            <input type="hidden" name="auto_approve_mark_rented" value="0">
                            <input type="checkbox" name="auto_approve_mark_rented" value="1" class="w-5 h-5"
                                   {{ $adConfig['auto_approve_mark_rented'] ? 'checked' : '' }}>
                        </label>

                        {{-- Max active per landlord --}}
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Max Active per Landlord
                            </label>
                            <input type="number" name="max_active_per_landlord" min="1" max="50"
                                   value="{{ old('max_active_per_landlord', $adConfig['max_active_per_landlord']) }}"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Includes pending-payment campaigns
                            </p>
                        </div>

                        {{-- Max on homepage --}}
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                Max Ads on Homepage
                            </label>
                            <input type="number" name="max_on_homepage" min="1" max="20"
                                   value="{{ old('max_on_homepage', $adConfig['max_on_homepage']) }}"
                                   class="form-input w-full px-3 py-2 rounded-lg">
                        </div>
                    </div>

                    {{-- Channels --}}
                    <div class="p-3 rounded-lg mb-4"
                         style="background: rgba(var(--info-rgb), 0.04);
                                border: 1px solid rgba(var(--info-rgb), 0.15);">
                        <p class="text-sm font-semibold mb-2" style="color: var(--text-primary);">
                            <i class="fas fa-broadcast-tower mr-2" style="color: var(--info);"></i>
                            Available Channels
                        </p>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach(['homepage','facebook','whatsapp','twitter','linkedin','instagram'] as $channel)
                                @php $on = !empty($adConfig['channels'][$channel]); @endphp
                                <label class="flex items-center justify-between p-2 rounded-lg cursor-pointer"
                                       style="background: var(--card-bg);
                                              border: 1px solid var(--border-color);">
                                    <span class="text-sm capitalize" style="color: var(--text-primary);">
                                        @if($channel === 'homepage')<i class="fas fa-home mr-1"></i>
                                        @elseif($channel === 'facebook')<i class="fab fa-facebook-f mr-1"></i>
                                        @elseif($channel === 'whatsapp')<i class="fab fa-whatsapp mr-1"></i>
                                        @elseif($channel === 'twitter')<i class="fab fa-x-twitter mr-1"></i>
                                        @elseif($channel === 'linkedin')<i class="fab fa-linkedin-in mr-1"></i>
                                        @elseif($channel === 'instagram')<i class="fab fa-instagram mr-1"></i>
                                        @endif
                                        {{ $channel }}
                                    </span>
                                    <input type="hidden" name="channels[{{ $channel }}]" value="0">
                                    <input type="checkbox" name="channels[{{ $channel }}]" value="1" class="w-4 h-4"
                                           {{ $on ? 'checked' : '' }}>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="btn-primary px-5 py-2 rounded-lg font-medium inline-flex items-center">
                            <i class="fas fa-save mr-2"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ============ PRICING PLANS ============ --}}
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--success);">
                <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-tags text-xl" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                Pricing Plans
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Landlords choose from these plans when creating a campaign
                            </p>
                        </div>
                    </div>

                    <button type="button" id="openAdPlanModal"
                            class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i> New Plan
                    </button>
                </div>

                @if($adPlans->isEmpty())
                    <div class="text-center py-10 rounded-lg"
                         style="background-color: rgba(var(--secondary-rgb), 0.03);
                                border: 1px dashed var(--border-color);">
                        <i class="fas fa-tags text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.4;"></i>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            No pricing plans yet. Create one to start selling ad space.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th class="text-left py-2 px-2 font-semibold" style="color: var(--text-primary);">Plan</th>
                                    <th class="text-left py-2 px-2 font-semibold" style="color: var(--text-primary);">Period</th>
                                    <th class="text-left py-2 px-2 font-semibold" style="color: var(--text-primary);">Duration</th>
                                    <th class="text-right py-2 px-2 font-semibold" style="color: var(--text-primary);">Price</th>
                                    <th class="text-left py-2 px-2 font-semibold" style="color: var(--text-primary);">Status</th>
                                    <th class="text-right py-2 px-2 font-semibold" style="color: var(--text-primary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($adPlans as $plan)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td class="py-3 px-2">
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $plan->name }}</div>
                                            @if($plan->description)
                                                <div class="text-xs" style="color: var(--text-secondary);">{{ Str::limit($plan->description, 60) }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-2 capitalize" style="color: var(--text-secondary);">{{ $plan->billing_period }}</td>
                                        <td class="py-3 px-2" style="color: var(--text-secondary);">{{ $plan->duration_months }} mo</td>
                                        <td class="py-3 px-2 text-right font-semibold" style="color: var(--primary);">{{ $plan->formatted_price }}</td>
                                        <td class="py-3 px-2">
                                            @if($plan->is_active)
                                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-success">Active</span>
                                            @else
                                                <span class="px-2 py-1 rounded-full text-xs font-medium badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-2 text-right">
                                            <div class="inline-flex gap-1 flex-wrap justify-end">
                                                <button type="button"
                                                        class="px-2 py-1 rounded text-xs font-medium edit-plan-btn"
                                                        data-plan='@json($plan)'
                                                        style="background: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="{{ route('admin.homepage-content.ads.plans.toggle', $plan->id) }}"
                                                      method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-2 py-1 rounded text-xs font-medium"
                                                            style="background: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                                            title="{{ $plan->is_active ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fas fa-{{ $plan->is_active ? 'pause' : 'play' }}"></i>
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.homepage-content.ads.plans.destroy', $plan->id) }}"
                                                      method="POST" class="inline"
                                                      onsubmit="return confirm('Delete this plan? Existing campaigns keep their snapshot.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="px-2 py-1 rounded text-xs font-medium"
                                                            style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============ RECENT CAMPAIGNS ============ --}}
        <div class="card">
            <div class="p-6" style="border-top: 4px solid var(--warning);">
                <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3"
                             style="background-color: rgba(var(--warning-rgb), 0.1);">
                            <i class="fas fa-bullhorn text-xl" style="color: var(--warning);"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                Recent Campaigns
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                Latest 5 campaigns across all landlords
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('admin.homepage-content.ads.campaigns') }}"
                       class="btn-outline px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="border: 1px solid var(--primary); color: var(--primary);">
                        <i class="fas fa-list mr-2"></i> View All
                    </a>
                </div>

                @if($recentCampaigns->isEmpty())
                    <div class="text-center py-10 rounded-lg"
                         style="background-color: rgba(var(--secondary-rgb), 0.03);
                                border: 1px dashed var(--border-color);">
                        <i class="fas fa-bullhorn text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.4;"></i>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            No campaigns yet. Once landlords start creating ads, they'll appear here.
                        </p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentCampaigns as $campaign)
                            @php
                                [$badgeLabel, $badgeColor] = $campaignStatusBadges[$campaign->status] ?? [ucfirst($campaign->status), 'secondary'];
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg"
                                 style="background: rgba(var(--secondary-rgb), 0.03);
                                        border: 1px solid var(--border-color);">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="font-medium truncate" style="color: var(--text-primary);">
                                            {{ $campaign->unit->unit_name ?? $campaign->unit->unit_number ?? 'Unit #' . $campaign->property_unit_id }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                              style="background: rgba(var(--{{ $badgeColor }}-rgb), 0.15);
                                                     color: var(--{{ $badgeColor }});">
                                            {{ $badgeLabel }}
                                        </span>
                                        @if($campaign->rented_requested_at && !$campaign->rented_approved_at)
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium badge-warning">
                                                <i class="fas fa-exclamation-circle mr-1"></i> Review Required
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        <i class="fas fa-user mr-1"></i>
                                        {{ $campaign->landlord->name ?? 'Unknown landlord' }}
                                        · {{ $campaign->plan_name }}
                                        · {{ '₵' . number_format($campaign->total_paid, 2) }}
                                        @if($campaign->ends_at)
                                            · Ends {{ $campaign->ends_at->format('M d, Y') }}
                                        @endif
                                    </div>
                                </div>

                                @if($campaign->rented_requested_at && !$campaign->rented_approved_at)
                                    <form method="POST"
                                          action="{{ route('admin.homepage-content.ads.campaigns.approve-rented', $campaign->id) }}"
                                          onsubmit="return confirm('Approve mark-as-rented for this campaign?')">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-lg text-xs font-medium"
                                                style="background: var(--success); color: white;">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>

{{-- ============================================================
     MODALS + HIDDEN FORMS
     ============================================================ --}}

{{-- Ad Plan create/edit modal --}}
<div id="adPlanModal"
     role="dialog"
     aria-modal="true"
     style="position: fixed; inset: 0; z-index: 9999;
            background-color: rgba(0,0,0,0.7);
            display: none; align-items: center; justify-content: center; padding: 1rem;">
    <div style="width: 100%; max-width: 520px;
                background-color: var(--card-bg);
                border-radius: var(--radius-lg);
                overflow: hidden;
                box-shadow: 0 25px 60px rgba(0,0,0,0.4);">
        <div class="flex items-center justify-between p-4"
             style="border-bottom: 1px solid var(--border-color);">
            <h3 class="font-semibold" id="adPlanModalTitle" style="color: var(--text-primary);">
                New Pricing Plan
            </h3>
            <button type="button" id="closeAdPlanModal"
                    class="w-8 h-8 rounded-full inline-flex items-center justify-center"
                    style="background: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.homepage-content.ads.plans.store') }}"
              id="adPlanForm" class="p-4">
            @csrf
            <input type="hidden" name="_method" id="adPlanFormMethod" value="POST">
            <input type="hidden" name="plan_id" id="adPlanId" value="">

            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Name</label>
                    <input type="text" name="name" id="adPlanName" required maxlength="100"
                           placeholder="e.g. Standard Monthly"
                           class="form-input w-full px-3 py-2 rounded-lg">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Period</label>
                        <select name="billing_period" id="adPlanPeriod" required
                                class="form-input w-full px-3 py-2 rounded-lg">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Duration (months)</label>
                        <input type="number" name="duration_months" id="adPlanDuration" required min="1" max="24" value="1"
                               class="form-input w-full px-3 py-2 rounded-lg">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Price</label>
                        <input type="number" name="price" id="adPlanPrice" required min="1" step="0.01"
                               placeholder="50.00"
                               class="form-input w-full px-3 py-2 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Currency</label>
                        <input type="text" name="currency" id="adPlanCurrency" maxlength="3"
                               value="GHS"
                               class="form-input w-full px-3 py-2 rounded-lg">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Description (optional)</label>
                    <textarea name="description" id="adPlanDescription" rows="2" maxlength="500"
                              class="form-input w-full px-3 py-2 rounded-lg"></textarea>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="adPlanActive" checked class="w-5 h-5">
                    <span class="text-sm" style="color: var(--text-primary);">Active</span>
                </label>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="cancelAdPlanModal"
                        class="px-4 py-2 rounded-lg text-sm font-medium"
                        style="background: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="submit"
                        class="btn-primary px-5 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> <span id="adPlanSubmitLabel">Create Plan</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Preview modal --}}
<div id="homepagePreviewModal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="previewModalTitle"
     style="position: fixed; inset: 0; z-index: 9999;
            background-color: rgba(0,0,0,0.7);
            display: none; align-items: center; justify-content: center; padding: 1rem;">

    <div style="width: 95vw; height: 92vh; max-width: 1400px;
                background-color: var(--card-bg);
                border-radius: var(--radius-lg);
                overflow: hidden;
                display: flex; flex-direction: column;
                box-shadow: 0 25px 60px rgba(0,0,0,0.4);">

        <div class="flex items-center justify-between p-3 px-4"
             style="border-bottom: 1px solid var(--border-color);
                    background-color: var(--card-bg);">
            <div class="flex items-center gap-3">
                <i class="fas fa-eye" style="color: var(--info);"></i>
                <h3 class="font-semibold" id="previewModalTitle" style="color: var(--text-primary);">
                    Homepage Preview
                </h3>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                      style="background-color: rgba(var(--warning-rgb), 0.15);
                             color: var(--warning);">
                    Preview Mode
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="reloadPreviewBtn"
                        class="btn-secondary px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center"
                        title="Reload the preview">
                    <i class="fas fa-sync mr-1"></i> Reload
                </button>
                <a id="openPreviewInTabBtn"
                   href="#"
                   target="_blank" rel="noopener noreferrer"
                   class="btn-secondary px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center"
                   title="Open in new tab">
                    <i class="fas fa-external-link-alt mr-1"></i> Open
                </a>
                <button type="button" id="closePreviewBtn"
                        class="btn-danger px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center"
                        title="Close preview">
                    <i class="fas fa-times mr-1"></i> Close
                </button>
            </div>
        </div>

        <div style="flex: 1; position: relative; background-color: #fff;">
            <iframe id="previewFrame"
                    src="about:blank"
                    style="width: 100%; height: 100%; border: 0;"
                    title="Preview"
                    loading="lazy"
                    referrerpolicy="no-referrer"
                    sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>

            <div id="previewLoader"
                 style="position: absolute; inset: 0;
                        display: none; align-items: center; justify-content: center;
                        background-color: rgba(255,255,255,0.85);">
                <div style="text-align: center;">
                    <div class="spinner"
                         style="width: 40px; height: 40px; border-width: 3px; margin: 0 auto 1rem;"></div>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Loading preview…
                    </p>
                </div>
            </div>
        </div>

        <div class="p-2 px-4 text-xs"
             style="border-top: 1px solid var(--border-color);
                    background-color: var(--bg-secondary);
                    color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-1"></i>
            Preview shows the <strong>saved</strong> state. Save your changes to see them here.
        </div>
    </div>
</div>

{{-- Hidden forms --}}
<form action="{{ route('admin.homepage-content.theme.preset') }}"
      method="POST"
      id="theme-preset-form"
      style="display: none;">
    @csrf
    <input type="hidden" name="preset" id="theme-preset-value" value="">
</form>

<form action="{{ route('admin.homepage-content.theme.reset') }}"
      method="POST"
      id="theme-reset-form"
      style="display: none;"
      onsubmit="return confirm('Reset theme to defaults?\n\nThis will remove all custom colors, fonts, layout, and button settings. Your hero image, text, social links, and contact info stay unchanged.')">
    @csrf
</form>

<form action="{{ route('admin.homepage-content.login.reset') }}"
      method="POST"
      id="login-reset-form"
      style="display: none;"
      onsubmit="return confirm('Reset the login page to defaults?\n\nThis will remove the custom background image and restore all login page settings.')">
    @csrf
</form>

<form action="{{ route('admin.homepage-content.login.theme.preset') }}"
      method="POST"
      id="login-theme-preset-form"
      style="display: none;">
    @csrf
    <input type="hidden" name="preset" id="login-theme-preset-value" value="">
</form>

<form action="{{ route('admin.homepage-content.login.theme.reset') }}"
      method="POST"
      id="login-theme-reset-form"
      style="display: none;"
      onsubmit="return confirm('Reset the login theme to defaults?\n\nThis will restore default login colors, fonts, and layout. Your login content (title, features, socials) stays unchanged.')">
    @csrf
</form>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    // Shared helper: guard file size before auto-submit
    // ============================================================
    function guardFileSize(input, maxBytes) {
        if (!input.files[0]) return false;
        if (input.files[0].size > maxBytes) {
            alert('Image is too large. Maximum size is 5 MB.');
            input.value = '';
            return false;
        }
        return true;
    }

    // ============================================================
    // Hero background upload
    // ============================================================
    const fileInput  = document.getElementById('bg-file-input');
    const uploadBtn  = document.getElementById('bg-upload-btn');
    const uploadForm = document.getElementById('bg-upload-form');
    const previewImg = document.getElementById('bg-preview-img');

    if (uploadBtn && fileInput && uploadForm) {
        uploadBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            if (!guardFileSize(fileInput, 5 * 1024 * 1024)) return;
            const reader = new FileReader();
            reader.onload = e => { if (previewImg) previewImg.src = e.target.result; };
            reader.readAsDataURL(fileInput.files[0]);
            uploadForm.submit();
        });
    }

    // ============================================================
    // Login background upload
    // ============================================================
    const loginFileInput  = document.getElementById('login-bg-file-input');
    const loginUploadBtn  = document.getElementById('login-bg-upload-btn');
    const loginUploadForm = document.getElementById('login-bg-upload-form');
    const loginPreviewImg = document.getElementById('login-bg-preview-img');

    if (loginUploadBtn && loginFileInput && loginUploadForm) {
        loginUploadBtn.addEventListener('click', () => loginFileInput.click());
        loginFileInput.addEventListener('change', () => {
            if (!guardFileSize(loginFileInput, 5 * 1024 * 1024)) return;
            const reader = new FileReader();
            reader.onload = e => { if (loginPreviewImg) loginPreviewImg.src = e.target.result; };
            reader.readAsDataURL(loginFileInput.files[0]);
            loginUploadForm.submit();
        });
    }

    // ============================================================
    // Preview modal
    // ============================================================
    const modal       = document.getElementById('homepagePreviewModal');
    const frame       = document.getElementById('previewFrame');
    const loader      = document.getElementById('previewLoader');
    const closeBtn    = document.getElementById('closePreviewBtn');
    const reloadBtn   = document.getElementById('reloadPreviewBtn');
    const openTabBtn  = document.getElementById('openPreviewInTabBtn');
    const modalTitle  = document.getElementById('previewModalTitle');
    const openBtns    = [
        document.getElementById('openPreviewBtn'),
        document.getElementById('openPreviewBtnBottom'),
    ];

    const HOMEPAGE_PREVIEW_URL = "{{ route('admin.homepage-content.preview') }}";
    const LOGIN_PREVIEW_URL    = "{{ route('admin.homepage-content.login.preview') }}";

    let currentPreviewUrl = HOMEPAGE_PREVIEW_URL;

    function openPreview(previewUrl, title) {
        currentPreviewUrl = previewUrl || HOMEPAGE_PREVIEW_URL;
        if (modalTitle && title) modalTitle.textContent = title;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (loader) loader.style.display = 'flex';
        const url = currentPreviewUrl + '?_t=' + Date.now();
        frame.src = url;
        if (openTabBtn) openTabBtn.href = url;
        frame.onload = () => { if (loader) loader.style.display = 'none'; };
    }

    function closePreview() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        frame.src = 'about:blank';
    }

    function reloadPreview() {
        if (frame.src === 'about:blank') return;
        if (loader) loader.style.display = 'flex';
        frame.src = currentPreviewUrl + '?_t=' + Date.now();
    }

    openBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', () => openPreview(HOMEPAGE_PREVIEW_URL, 'Homepage Preview'));
    });

    const loginPreviewBtn = document.getElementById('openLoginPreviewBtn');
    if (loginPreviewBtn) {
        loginPreviewBtn.addEventListener('click', () => openPreview(LOGIN_PREVIEW_URL, 'Login Page Preview'));
    }

    if (closeBtn)  closeBtn.addEventListener('click', closePreview);
    if (reloadBtn) reloadBtn.addEventListener('click', reloadPreview);

    if (modal) {
        modal.addEventListener('click', (e) => { if (e.target === modal) closePreview(); });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
            closePreview();
        }
    });

    // ============================================================
    // Color picker ↔ text input sync
    // ============================================================
    const HEX_RE = /^#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/;

    function normalizeHexForPicker(val) {
        let v = val.trim().replace(/^#/, '');
        if (v.length === 3) {
            v = v.split('').map(c => c + c).join('');
        } else if (v.length === 8) {
            v = v.slice(0, 6);
        }
        return '#' + v;
    }

    document.querySelectorAll('.theme-color-picker').forEach(picker => {
        const targetId  = picker.dataset.target;
        const textInput = targetId ? document.getElementById(targetId) : null;
        if (!textInput) return;

        const isLoginField = picker.dataset.loginThemeField === '1'
            || (textInput.name && textInput.name.indexOf('login[theme]') === 0);

        picker.addEventListener('input', () => {
            textInput.value = picker.value;
            if (isLoginField) markLoginThemeCustom(); else markThemeCustom();
        });

        textInput.addEventListener('input', () => {
            const val = textInput.value.trim();
            if (val === '') { isLoginField ? markLoginThemeCustom() : markThemeCustom(); return; }
            if (HEX_RE.test(val)) {
                const normalized = normalizeHexForPicker(val);
                if (/^#[0-9A-Fa-f]{6}$/.test(normalized)) picker.value = normalized;
                isLoginField ? markLoginThemeCustom() : markThemeCustom();
            }
        });
    });

    // ============================================================
    // Overlay opacity sliders
    // ============================================================
    const opacitySlider = document.getElementById('overlay-opacity-slider');
    const opacityLabel  = document.getElementById('overlay-opacity-value');
    if (opacitySlider && opacityLabel) {
        opacitySlider.addEventListener('input', () => {
            opacityLabel.textContent = parseFloat(opacitySlider.value).toFixed(2);
            markThemeCustom();
        });
    }

    const loginOpacitySlider = document.getElementById('login-overlay-opacity-slider');
    const loginOpacityLabel  = document.getElementById('login-overlay-opacity-value');
    if (loginOpacitySlider && loginOpacityLabel) {
        loginOpacitySlider.addEventListener('input', () => {
            loginOpacityLabel.textContent = parseFloat(loginOpacitySlider.value).toFixed(2);
        });
    }

    const loginPanelWidthSlider = document.getElementById('login-panel-width-slider');
    const loginPanelWidthLabel  = document.getElementById('login-panel-width-value');
    if (loginPanelWidthSlider && loginPanelWidthLabel) {
        loginPanelWidthSlider.addEventListener('input', () => {
            loginPanelWidthLabel.textContent = loginPanelWidthSlider.value + '%';
        });
    }

    // ============================================================
    // Mark theme as custom
    // ============================================================
    const presetInput = document.getElementById('theme-preset-input');
    function markThemeCustom() {
        if (presetInput && presetInput.value !== 'custom') presetInput.value = 'custom';
    }
    document.querySelectorAll('#homepage-form input[name^="theme["], #homepage-form select[name^="theme["]').forEach(el => {
        if (el.id === 'theme-preset-input') return;
        el.addEventListener('change', markThemeCustom);
        el.addEventListener('input', markThemeCustom);
    });

    const loginPresetInput = document.getElementById('login-theme-preset-input');
    function markLoginThemeCustom() {
        if (loginPresetInput && loginPresetInput.value !== 'custom') loginPresetInput.value = 'custom';
    }
    document.querySelectorAll('#login-page-form input[name^="login[theme]"], #login-page-form select[name^="login[theme]"]').forEach(el => {
        if (el.id === 'login-theme-preset-input') return;
        el.addEventListener('change', markLoginThemeCustom);
        el.addEventListener('input', markLoginThemeCustom);
    });

    // ============================================================
    // Theme preset buttons
    // ============================================================
    document.querySelectorAll('.theme-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const presetKey = btn.dataset.preset;
            if (!presetKey) return;
            const currentPreset = presetInput ? presetInput.value : 'default';
            if (currentPreset === 'custom') {
                const ok = confirm(
                    'You have custom theme settings.\n\n' +
                    'Applying this preset will overwrite your custom colors, fonts, layout, and button styles.\n\n' +
                    'Continue?'
                );
                if (!ok) return;
            }
            const presetForm  = document.getElementById('theme-preset-form');
            const presetValue = document.getElementById('theme-preset-value');
            if (presetForm && presetValue) {
                presetValue.value = presetKey;
                presetForm.submit();
            }
        });
    });

    document.querySelectorAll('.login-theme-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const presetKey = btn.dataset.preset;
            if (!presetKey) return;
            const currentPreset = loginPresetInput ? loginPresetInput.value : 'default';
            if (currentPreset === 'custom') {
                const ok = confirm(
                    'You have custom login theme settings.\n\n' +
                    'Applying this preset will overwrite your custom login colors, fonts, and layout.\n\n' +
                    'Continue?'
                );
                if (!ok) return;
            }
            const presetForm  = document.getElementById('login-theme-preset-form');
            const presetValue = document.getElementById('login-theme-preset-value');
            if (presetForm && presetValue) {
                presetValue.value = presetKey;
                presetForm.submit();
            }
        });
    });

    // ============================================================
    // Theme reset
    // ============================================================
    const resetThemeBtn = document.getElementById('resetThemeBtn');
    if (resetThemeBtn) {
        resetThemeBtn.addEventListener('click', () => {
            const resetForm = document.getElementById('theme-reset-form');
            if (resetForm) resetForm.submit();
        });
    }

    const resetLoginThemeBtn = document.getElementById('resetLoginThemeBtn');
    if (resetLoginThemeBtn) {
        resetLoginThemeBtn.addEventListener('click', () => {
            const resetForm = document.getElementById('login-theme-reset-form');
            if (resetForm) resetForm.submit();
        });
    }

    // ============================================================
    // LOGIN: Feature rows
    // ============================================================
    const addLoginFeatureBtn      = document.getElementById('add-login-feature-btn');
    const loginFeaturesContainer  = document.getElementById('login-features-container');
    const featureRowTemplate      = document.getElementById('login-feature-row-template');

    function updateIconPreview(row) {
        if (!row) return;
        const select  = row.querySelector('[data-role="icon-select"]');
        const preview = row.querySelector('.login-feature-icon-preview');
        if (!preview) return;
        const iconEl = preview.querySelector('i');
        if (!iconEl) return;
        if (!select) return;
        const val = select.value || '';
        iconEl.className = val !== '' ? val : 'fas fa-question';
    }

    function reindexFeatureRows() {
        if (!loginFeaturesContainer) return;
        const rows = loginFeaturesContainer.querySelectorAll('.login-feature-row');
        rows.forEach((row, idx) => {
            const iconSelect = row.querySelector('[data-role="icon-select"]');
            const textInput  = row.querySelector('[data-role="text"]');
            if (iconSelect) iconSelect.name = `login[welcome_features][${idx}][icon]`;
            if (textInput)  textInput.name  = `login[welcome_features][${idx}][text]`;
        });
    }

    function attachRowHandlers(row) {
        if (!row) return;
        const removeBtn = row.querySelector('.login-feature-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                row.remove();
                reindexFeatureRows();
            });
        }
        const iconSelect = row.querySelector('[data-role="icon-select"]');
        if (iconSelect) {
            iconSelect.addEventListener('change', () => updateIconPreview(row));
        }
        updateIconPreview(row);
    }

    if (loginFeaturesContainer) {
        loginFeaturesContainer.querySelectorAll('.login-feature-row').forEach(attachRowHandlers);
    }

    if (addLoginFeatureBtn && loginFeaturesContainer && featureRowTemplate) {
        addLoginFeatureBtn.addEventListener('click', () => {
            const max     = parseInt(loginFeaturesContainer.dataset.max || '6', 10);
            const current = loginFeaturesContainer.querySelectorAll('.login-feature-row').length;
            if (current >= max) {
                alert(`Maximum ${max} features allowed.`);
                return;
            }
            const fragment = featureRowTemplate.content.cloneNode(true);
            loginFeaturesContainer.appendChild(fragment);
            const newRow = loginFeaturesContainer.lastElementChild;
            attachRowHandlers(newRow);
            reindexFeatureRows();
            const textInput = newRow.querySelector('[data-role="text"]');
            if (textInput) textInput.focus();
        });
    }

    // ============================================================
    // Login page reset
    // ============================================================
    const resetLoginBtn = document.getElementById('resetLoginBtn');
    if (resetLoginBtn) {
        resetLoginBtn.addEventListener('click', () => {
            const loginResetForm = document.getElementById('login-reset-form');
            if (loginResetForm) loginResetForm.submit();
        });
    }

    // ============================================================
    // AD SPACE: Pricing plan modal
    // ============================================================
    const adPlanModal       = document.getElementById('adPlanModal');
    const adPlanForm        = document.getElementById('adPlanForm');
    const adPlanFormMethod  = document.getElementById('adPlanFormMethod');
    const adPlanId          = document.getElementById('adPlanId');
    const adPlanModalTitle  = document.getElementById('adPlanModalTitle');
    const adPlanSubmitLabel = document.getElementById('adPlanSubmitLabel');
    const adPlanName        = document.getElementById('adPlanName');
    const adPlanPeriod      = document.getElementById('adPlanPeriod');
    const adPlanDuration    = document.getElementById('adPlanDuration');
    const adPlanPrice       = document.getElementById('adPlanPrice');
    const adPlanCurrency    = document.getElementById('adPlanCurrency');
    const adPlanDescription = document.getElementById('adPlanDescription');
    const adPlanActive      = document.getElementById('adPlanActive');

    const STORE_URL_TEMPLATE = "{{ route('admin.homepage-content.ads.plans.store') }}";
    const UPDATE_URL_TEMPLATE = "{{ url('admin/homepage-content/ads/plans') }}/__ID__";

    function openAdPlanModal(plan) {
        if (!adPlanModal || !adPlanForm) return;

        if (plan && plan.id) {
            // Edit mode
            adPlanModalTitle.textContent = 'Edit Pricing Plan';
            adPlanSubmitLabel.textContent = 'Save Changes';
            adPlanForm.action = UPDATE_URL_TEMPLATE.replace('__ID__', plan.id);
            adPlanFormMethod.value = 'PATCH';
            adPlanId.value = plan.id;
            adPlanName.value = plan.name || '';
            adPlanPeriod.value = plan.billing_period || 'monthly';
            adPlanDuration.value = plan.duration_months || 1;
            adPlanPrice.value = plan.price || '';
            adPlanCurrency.value = plan.currency || 'GHS';
            adPlanDescription.value = plan.description || '';
            adPlanActive.checked = !!plan.is_active;

            // Lock period/duration on edit — changing them on an existing plan
            // would corrupt history. To change those, create a new plan.
            adPlanPeriod.disabled = true;
            adPlanDuration.disabled = true;
        } else {
            // Create mode
            adPlanModalTitle.textContent = 'New Pricing Plan';
            adPlanSubmitLabel.textContent = 'Create Plan';
            adPlanForm.action = STORE_URL_TEMPLATE;
            adPlanFormMethod.value = 'POST';
            adPlanId.value = '';
            adPlanForm.reset();
            adPlanCurrency.value = 'GHS';
            adPlanActive.checked = true;
            adPlanPeriod.disabled = false;
            adPlanDuration.disabled = false;
        }

        adPlanModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeAdPlanModal() {
        if (!adPlanModal) return;
        adPlanModal.style.display = 'none';
        document.body.style.overflow = '';
        adPlanPeriod.disabled = false;
        adPlanDuration.disabled = false;
    }

    const openAdPlanBtn = document.getElementById('openAdPlanModal');
    if (openAdPlanBtn) openAdPlanBtn.addEventListener('click', () => openAdPlanModal(null));

    const closeAdPlanBtn  = document.getElementById('closeAdPlanModal');
    const cancelAdPlanBtn = document.getElementById('cancelAdPlanModal');
    if (closeAdPlanBtn)  closeAdPlanBtn.addEventListener('click', closeAdPlanModal);
    if (cancelAdPlanBtn) cancelAdPlanBtn.addEventListener('click', closeAdPlanModal);

    if (adPlanModal) {
        adPlanModal.addEventListener('click', (e) => {
            if (e.target === adPlanModal) closeAdPlanModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && adPlanModal && adPlanModal.style.display !== 'none') {
            closeAdPlanModal();
        }
    });

    // Edit plan buttons
    document.querySelectorAll('.edit-plan-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                const plan = JSON.parse(btn.dataset.plan);
                openAdPlanModal(plan);
            } catch (err) {
                console.error('Failed to parse plan data:', err);
            }
        });
    });

});
</script>
@endsection