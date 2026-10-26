<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdInvoice;
use App\Models\AdPricingPlan;
use App\Models\PropertyUnit;                    // ← ADDED: used by destroyAdCampaign
use App\Models\SocialShare;                     // ← ADDED: used by trackShare/listShares
use App\Models\SystemSetting;
use App\Services\AdCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;              // ← ADDED: used by destroyAdCampaign
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;       // ← ADDED: used by trackShare
use Illuminate\Support\Str;

class HomepageContentController extends Controller
{
    public function __construct(
        private AdCampaignService $adService
    ) {}

    /* =========================================================
     *  INDEX — editor page
     * ========================================================= */
    public function index()
    {
        $settings = SystemSetting::getSettings();

        if (!$settings->exists) {
            return redirect()
                ->route('admin.system-settings.create')
                ->with('info', 'Create system settings first.');
        }

        $config     = $settings->getHomepageConfig();
        $heroUrl    = $settings->getHomepageHeroImageUrl();
        $loginBgUrl = $settings->getLoginPageBackgroundUrl();

        // Expose theme presets to the view so the login editor
        // can offer the same one-click presets as the homepage.
        $themePresets = SystemSetting::THEME_PRESETS;

        return view('admin.homepage-content.index', compact(
            'settings', 'config', 'heroUrl', 'loginBgUrl', 'themePresets'
        ));
    }

    /* =========================================================
     *  UPDATE — save text / sections / social / contact / theme
     * ========================================================= */
    public function update(Request $request)
    {
        $settings = SystemSetting::getSettingsOrFail();

        $validated = $request->validate([
            // Hero text
            'hero.title'         => 'nullable|string|max:120',
            'hero.subtitle'      => 'nullable|string|max:500',
            'hero.cta_primary'   => 'nullable|string|max:80',
            'hero.cta_secondary' => 'nullable|string|max:80',

            // Sections
            'sections'       => 'array',
            'sections.*'     => 'boolean',

            // Social
            'social.facebook'  => 'nullable|url|max:255',
            'social.twitter'   => 'nullable|url|max:255',
            'social.instagram' => 'nullable|url|max:255',
            'social.whatsapp'  => 'nullable|string|max:255',
            'social.linkedin'  => 'nullable|url|max:255',
            'social.youtube'   => 'nullable|url|max:255',
            'social.tiktok'    => 'nullable|url|max:255',

            // Contact
            'contact.email'   => 'nullable|email|max:120',
            'contact.phone'   => 'nullable|string|max:40',
            'contact.address' => 'nullable|string|max:255',
            'contact.hours'   => 'nullable|string|max:500',

            // Footer
            'footer.copyright' => 'nullable|string|max:255',

            // 🎨 Theme — colors
            'theme.colors.primary'    => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'theme.colors.secondary'  => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'theme.colors.accent'     => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'theme.colors.hero_bg'    => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'theme.colors.hero_text'  => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',

            // 🎨 Theme — typography
            'theme.fonts.family'         => 'nullable|in:system,inter,poppins,roboto,playfair',
            'theme.fonts.base_size'      => 'nullable|integer|min:12|max:22',
            'theme.fonts.heading_weight' => 'nullable|in:400,500,600,700,800',

            // 🎨 Theme — hero
            'theme.hero.overlay_opacity' => 'nullable|numeric|min:0|max:1',
            'theme.hero.text_align'      => 'nullable|in:left,center,right',
            'theme.hero.height'          => 'nullable|in:compact,standard,tall,full',

            // 🎨 Theme — layout
            'theme.layout.container_width' => 'nullable|integer|min:900|max:1600',
            'theme.layout.section_padding' => 'nullable|in:compact,normal,spacious',
            'theme.layout.radius_style'    => 'nullable|in:sharp,rounded,pill',

            // 🎨 Theme — buttons
            'theme.buttons.shape'  => 'nullable|in:rounded,pill,square',
            'theme.buttons.shadow' => 'nullable|boolean',

            // Preset name
            'theme.preset' => 'nullable|string|max:32',
        ]);

        $config = array_replace_recursive(
            $settings->getHomepageConfig(),
            $validated
        );

        if ($this->isThemeCustomised($request, $settings)) {
            $config['theme']['preset'] = 'custom';
        }

        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', 'Homepage content updated successfully.');
    }

    /* =========================================================
     *  BACKGROUND IMAGE
     * ========================================================= */
    public function uploadBackground(Request $request)
    {
        $request->validate([
            'hero_background' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $settings = SystemSetting::getSettingsOrFail();
        $config   = $settings->getHomepageConfig();

        if (!empty($config['hero']['background'])) {
            Storage::disk('public')->delete($config['hero']['background']);
        }

        $path = $request->file('hero_background')->store('homepage', 'public');

        $config['hero']['background'] = $path;
        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', 'Hero background updated.');
    }

    public function removeBackground()
    {
        $settings = SystemSetting::getSettingsOrFail();
        $config   = $settings->getHomepageConfig();

        if (!empty($config['hero']['background'])) {
            Storage::disk('public')->delete($config['hero']['background']);
        }
        $config['hero']['background'] = null;
        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', 'Hero background removed. Using default image.');
    }

    /* =========================================================
     *  RESET EVERYTHING
     * ========================================================= */
    public function reset()
    {
        $settings = SystemSetting::getSettingsOrFail();

        $config = $settings->getHomepageConfig();
        if (!empty($config['hero']['background'])) {
            Storage::disk('public')->delete($config['hero']['background']);
        }

        $settings->homepage_config = null;
        $settings->save();

        return back()->with('success', 'Homepage reset to defaults.');
    }

    /* =========================================================
     *  🎨 THEME PRESET — apply one-click preset (homepage)
     * ========================================================= */
    public function applyThemePreset(Request $request)
    {
        $request->validate([
            'preset' => 'required|string|max:32',
        ]);

        $presetKey = $request->input('preset');
        $presets   = SystemSetting::THEME_PRESETS;

        if (!isset($presets[$presetKey])) {
            return back()->with('error', 'Unknown theme preset: ' . $presetKey);
        }

        $settings = SystemSetting::getSettingsOrFail();

        $config       = $settings->getHomepageConfig();
        $currentTheme = $config['theme'] ?? SystemSetting::HOMEPAGE_DEFAULTS['theme'];

        $mergedTheme = $this->arrayReplaceRecursiveDeep(
            $currentTheme,
            $presets[$presetKey]['values']
        );
        $mergedTheme['preset'] = $presetKey;

        $config['theme'] = $mergedTheme;

        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', sprintf(
            'Theme preset "%s" applied.',
            $presets[$presetKey]['name']
        ));
    }

    /* =========================================================
     *  🎨 THEME RESET — back to defaults
     * ========================================================= */
    public function resetTheme()
    {
        $settings = SystemSetting::getSettingsOrFail();

        $config = $settings->getHomepageConfig();
        $config['theme'] = SystemSetting::HOMEPAGE_DEFAULTS['theme'];

        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', 'Theme reset to defaults.');
    }

    /* =========================================================
     *  🔐 LOGIN PAGE — background image
     * ========================================================= */
    public function uploadLoginBackground(Request $request)
    {
        $request->validate([
            'login_background' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $settings = SystemSetting::getSettingsOrFail();
        $path = $request->file('login_background')->store('login-page', 'public');

        $settings->setLoginPageBackground($path);

        return back()->with('success', 'Login background image updated.');
    }

    public function removeLoginBackground()
    {
        $settings = SystemSetting::getSettingsOrFail();
        $settings->setLoginPageBackground(null);

        return back()->with('success', 'Login background image removed.');
    }

    /* =========================================================
     *  🔐 LOGIN PAGE — content save
     * ========================================================= */
    public function updateLoginPage(Request $request)
    {
        $settings = SystemSetting::getSettingsOrFail();

        $validated = $request->validate([
            // ── Existing content fields ─────────────────────────
            'login.background_url'       => 'nullable|url|max:500',
            'login.overlay_opacity'      => 'nullable|numeric|min:0|max:1',
            'login.default_theme'        => 'nullable|in:dark,light,system',
            'login.show_welcome_panel'   => 'nullable|boolean',
            'login.show_social_icons'    => 'nullable|boolean',
            'login.show_cookie_banner'   => 'nullable|boolean',
            'login.welcome_title'        => 'nullable|string|max:120',
            'login.welcome_subtitle'     => 'nullable|string|max:400',
            'login.system_name_override' => 'nullable|string|max:120',

            'login.welcome_features'        => 'nullable|array|max:6',
            'login.welcome_features.*.icon' => 'nullable|string|max:80',
            'login.welcome_features.*.text' => 'nullable|string|max:120',

            'login.footer_socials'            => 'nullable|array|max:8',
            'login.footer_socials.*.platform' => 'nullable|in:facebook,instagram,linkedin,twitter,whatsapp,youtube,tiktok',
            'login.footer_socials.*.url'      => 'nullable|url|max:255',

            // ── Login theme — colors ────────────────────────────
            'login.theme.colors.primary'          => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'login.theme.colors.secondary'        => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'login.theme.colors.accent'           => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'login.theme.colors.panel_bg'         => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'login.theme.colors.panel_text'       => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'login.theme.colors.form_bg'          => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',
            'login.theme.colors.form_text'        => 'nullable|string|regex:/^#?[0-9A-Fa-f]{3,8}$/',

            // ── Login theme — typography ────────────────────────
            'login.theme.fonts.family'         => 'nullable|in:system,inter,poppins,roboto,playfair',
            'login.theme.fonts.base_size'      => 'nullable|integer|min:12|max:22',
            'login.theme.fonts.heading_weight' => 'nullable|in:400,500,600,700,800',

            // ── Login theme — layout ────────────────────────────
            'login.theme.layout.split'          => 'nullable|in:panel-left,panel-right,single-column',
            'login.theme.layout.panel_width'    => 'nullable|integer|min:30|max:70',
            'login.theme.layout.radius_style'   => 'nullable|in:sharp,rounded,pill',
            'login.theme.layout.card_shadow'    => 'nullable|boolean',

            // ── Login theme — buttons ───────────────────────────
            'login.theme.buttons.shape'  => 'nullable|in:rounded,pill,square',
            'login.theme.buttons.shadow' => 'nullable|boolean',

            // Preset name (mirrors homepage behaviour)
            'login.theme.preset' => 'nullable|string|max:32',
        ]);

        $config        = $settings->getHomepageConfig();
        $existingLogin = $config['login'] ?? SystemSetting::HOMEPAGE_DEFAULTS['login'];

        $incoming = $validated['login'] ?? [];

        // Clean features — remove empty rows
        if (isset($incoming['welcome_features']) && is_array($incoming['welcome_features'])) {
            $incoming['welcome_features'] = array_values(array_filter(
                $incoming['welcome_features'],
                fn($f) => !empty($f['text']) || !empty($f['icon'])
            ));
        }

        // Clean socials — keep only rows with a URL
        if (isset($incoming['footer_socials']) && is_array($incoming['footer_socials'])) {
            $incoming['footer_socials'] = array_values(array_filter(
                $incoming['footer_socials'],
                fn($s) => !empty($s['url'])
            ));
        }

        // Merge existing login config with incoming values.
        $config['login'] = array_replace_recursive($existingLogin, $incoming);

        // If the admin tweaked theme fields manually, mark the
        // preset as "custom" so the UI stops highlighting a preset.
        if ($this->isLoginThemeCustomised($request)) {
            $config['login']['theme']['preset'] = 'custom';
        }

        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', 'Login page updated.');
    }

    /* =========================================================
     *  🔐 LOGIN PAGE — reset
     * ========================================================= */
    public function resetLoginPage()
    {
        $settings = SystemSetting::getSettingsOrFail();
        $settings->resetLoginPageConfig();

        return back()->with('success', 'Login page reset to defaults.');
    }

    /* =========================================================
     *  🆕 LOGIN THEME PRESET — apply one-click preset
     * ========================================================= */
    public function applyLoginThemePreset(Request $request)
    {
        $request->validate([
            'preset' => 'required|string|max:32',
        ]);

        $presetKey = $request->input('preset');
        $presets   = SystemSetting::THEME_PRESETS;

        if (!isset($presets[$presetKey])) {
            return back()->with('error', 'Unknown theme preset: ' . $presetKey);
        }

        $settings = SystemSetting::getSettingsOrFail();

        $config        = $settings->getHomepageConfig();
        $login         = $config['login'] ?? SystemSetting::HOMEPAGE_DEFAULTS['login'];
        $currentTheme  = $login['theme'] ?? SystemSetting::HOMEPAGE_DEFAULTS['login']['theme'];

        $presetValues = $presets[$presetKey]['values'];
        $loginPreset  = $this->mapHomepagePresetToLogin($presetValues);

        $mergedTheme = $this->arrayReplaceRecursiveDeep($currentTheme, $loginPreset);
        $mergedTheme['preset'] = $presetKey;

        $login['theme'] = $mergedTheme;

        $config['login'] = $login;

        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', sprintf(
            'Login theme preset "%s" applied.',
            $presets[$presetKey]['name']
        ));
    }

    /* =========================================================
     *  🆕 LOGIN THEME RESET — back to defaults
     * ========================================================= */
    public function resetLoginTheme()
    {
        $settings = SystemSetting::getSettingsOrFail();

        $config = $settings->getHomepageConfig();
        $login  = $config['login'] ?? SystemSetting::HOMEPAGE_DEFAULTS['login'];

        $login['theme'] = SystemSetting::HOMEPAGE_DEFAULTS['login']['theme'];

        $config['login'] = $login;

        $settings->homepage_config = $config;
        $settings->save();

        return back()->with('success', 'Login theme reset to defaults.');
    }

    /* =========================================================
     *  🔐 LOGIN PAGE — preview
     * ========================================================= */
    public function previewLogin()
    {
        $settings = SystemSetting::getSettings();

        return response()
            ->view('auth.login', [
                'systemSettings' => $settings,
                'loginPreview'   => true,
            ]);
    }

    /* =========================================================
     *  PREVIEW — renders welcome directly (bypasses HomeController)
     * ========================================================= */
    public function preview(Request $request)
    {
        $settings = SystemSetting::getSettings();

        $stats = [
            'total_properties'   => $this->safeCount('App\\Models\\Property'),
            'under_construction' => $this->safeCount('App\\Models\\Property', function ($query) {
                if (Schema::hasColumn('properties', 'status')) {
                    return $query->where('status', 'under_construction');
                }
                if (Schema::hasColumn('properties', 'property_status')) {
                    return $query->where('property_status', 'under_construction');
                }
                return $query->whereRaw('1 = 0');
            }),
            'total_tenants'      => $this->safeCount('App\\Models\\Tenant')
                                    ?: $this->safeCount('App\\Models\\TenantProfile')
                                    ?: $this->safeCount('App\\Models\\PropertyTenant'),
            'total_landlords'    => $this->safeCount('App\\Models\\User', function ($query) {
                if (Schema::hasColumn('users', 'type')) {
                    return $query->where('type', 2);
                }
                if (Schema::hasColumn('users', 'user_type')) {
                    return $query->where('user_type', 2);
                }
                if (Schema::hasColumn('users', 'role')) {
                    return $query->where('role', 'landlord');
                }
                return $query->whereRaw('1 = 0');
            }),
        ];

        // ✅ FIX: load featured ads for the preview so the admin sees
        //    exactly what the homepage will look like — including the
        //    "Sponsored Listings" section (with a demo card if no
        //    campaigns exist yet, handled by the blade's preview mode).
        $featuredAds = $this->adService->homepageAds();

        return view('welcome', [
            'systemSettings' => $settings,
            'stats'          => $stats,
            'preview'        => true,
            'featuredAds'    => $featuredAds,   // ← ADDED
        ]);
    }

    /* =========================================================
     *  ╔══════════════════════════════════════════════════════╗
     *  ║  📢 AD SPACE — SETTINGS + PLANS + CAMPAIGN REVIEW     ║
     *  ╚══════════════════════════════════════════════════════╝
     * ========================================================= */

    public function adSettings()
    {
        $settings = SystemSetting::getSettingsOrFail();
        $config   = $settings->getAdConfig();

        $plans = AdPricingPlan::orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'active'            => AdCampaign::where('status', AdCampaign::STATUS_ACTIVE)->count(),
            'pending_payment'   => AdCampaign::where('status', AdCampaign::STATUS_PENDING_PAYMENT)->count(),
            'rented_early'      => AdCampaign::where('status', AdCampaign::STATUS_RENTED_EARLY)->count(),
            'expired'           => AdCampaign::where('status', AdCampaign::STATUS_EXPIRED)->count(),
            'pending_rented'    => AdCampaign::whereNotNull('rented_requested_at')
                                    ->whereNull('rented_approved_at')
                                    ->count(),
            'total_revenue'     => (float) AdInvoice::where('status', AdInvoice::STATUS_PAID)->sum('amount'),
        ];

        return view('admin.ads.settings', compact('settings', 'config', 'plans', 'stats'));
    }

    public function updateAdSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled'                  => 'sometimes|boolean',
            'max_active_per_landlord'  => 'required|integer|min:1|max:50',
            'max_on_homepage'          => 'required|integer|min:1|max:20',
            'require_admin_approval'   => 'sometimes|boolean',
            'auto_approve_mark_rented' => 'sometimes|boolean',
            'channels'                 => 'array',
            'channels.*'               => 'boolean',
        ]);

        $settings = SystemSetting::getSettingsOrFail();
        $current  = $settings->getAdConfig();
        $merged   = array_replace_recursive($current, $validated);

        $settings->setAdConfig($merged);

        Log::info('Ad settings updated', [
            'admin_id' => auth()->id(),
            'changes'  => $validated,
        ]);

        return back()->with('success', 'Ad settings updated successfully.');
    }

    public function storeAdPlan(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'billing_period'  => 'required|in:monthly,yearly',
            'duration_months' => 'required|integer|min:1|max:24',
            'price'           => 'required|numeric|min:1',
            'currency'        => 'nullable|string|size:3',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'description'     => 'nullable|string|max:500',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'sometimes|boolean',
        ]);

        $slug = Str::slug($validated['name']);
        $base = $slug;
        $i    = 1;
        while (AdPricingPlan::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$i;
        }

        $validated['slug']            = $slug;
        $validated['currency']        = $validated['currency'] ?? 'GHS';
        $validated['sort_order']      = $validated['sort_order'] ?? 0;
        $validated['is_active']       = $request->boolean('is_active', true);
        $validated['discount_percent'] = $validated['discount_percent'] ?? 0;
        $validated['created_by']      = auth()->id();

        AdPricingPlan::create($validated);

        Log::info('Ad pricing plan created', [
            'admin_id' => auth()->id(),
            'name'     => $validated['name'],
            'price'    => $validated['price'],
        ]);

        return back()->with('success', 'Pricing plan created successfully.');
    }

    public function updateAdPlan(Request $request, int $id)
    {
        $plan = AdPricingPlan::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'price'            => 'required|numeric|min:1',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'description'      => 'nullable|string|max:500',
            'sort_order'       => 'nullable|integer|min:0',
            'is_active'        => 'sometimes|boolean',
        ]);

        $validated['is_active']        = $request->boolean('is_active', $plan->is_active);
        $validated['sort_order']       = $validated['sort_order'] ?? $plan->sort_order;
        $validated['discount_percent'] = $validated['discount_percent'] ?? $plan->discount_percent;

        $plan->update($validated);

        Log::info('Ad pricing plan updated', [
            'admin_id' => auth()->id(),
            'plan_id'  => $plan->id,
            'changes'  => $validated,
        ]);

        return back()->with('success', 'Pricing plan updated.');
    }

    public function toggleAdPlan(int $id)
    {
        $plan = AdPricingPlan::findOrFail($id);
        $plan->is_active = !$plan->is_active;
        $plan->save();

        Log::info('Ad pricing plan toggled', [
            'admin_id'  => auth()->id(),
            'plan_id'   => $plan->id,
            'is_active' => $plan->is_active,
        ]);

        return back()->with(
            'success',
            $plan->is_active
                ? 'Plan activated. Landlords can now purchase it.'
                : 'Plan deactivated. Existing campaigns are unaffected.'
        );
    }

    public function deleteAdPlan(int $id)
    {
        $plan = AdPricingPlan::findOrFail($id);

        $hasActive = AdCampaign::where('ad_pricing_plan_id', $plan->id)
            ->whereIn('status', [AdCampaign::STATUS_ACTIVE, AdCampaign::STATUS_PENDING_PAYMENT])
            ->exists();

        if ($hasActive) {
            return back()->with(
                'error',
                'Cannot delete this plan — it has active campaigns. Deactivate it instead.'
            );
        }

        $plan->delete();

        Log::info('Ad pricing plan deleted', [
            'admin_id' => auth()->id(),
            'plan_id'  => $plan->id,
        ]);

        return back()->with('success', 'Pricing plan deleted.');
    }

    public function adCampaigns(Request $request)
    {
        $query = AdCampaign::with(['unit.property', 'landlord', 'plan', 'invoices']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('pending_rented')) {
            $query->whereNotNull('rented_requested_at')
                ->whereNull('rented_approved_at')
                ->where('status', AdCampaign::STATUS_ACTIVE);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->whereHas('landlord', fn($q) => $q->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('unit', fn($q) => $q->where('unit_number', 'like', "%{$term}%")
                        ->orWhere('unit_name', 'like', "%{$term}%"));
            });
        }

        $campaigns = $query->latest()->paginate(20)->withQueryString();

        $statusOptions = [
            AdCampaign::STATUS_ACTIVE           => 'Active',
            AdCampaign::STATUS_PENDING_PAYMENT  => 'Pending Payment',
            AdCampaign::STATUS_PAUSED           => 'Paused',
            AdCampaign::STATUS_RENTED_EARLY     => 'Rented (Stopped Early)',
            AdCampaign::STATUS_EXPIRED          => 'Expired',
            AdCampaign::STATUS_CANCELLED        => 'Cancelled',
            AdCampaign::STATUS_REJECTED         => 'Rejected',
        ];

        return view('admin.ads.campaigns', compact('campaigns', 'statusOptions'));
    }

    public function approveRentedCampaign(int $id)
    {
        $campaign = AdCampaign::findOrFail($id);

        try {
            $this->adService->approveRentedEarly($campaign, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Campaign marked as rented and stopped. Unit marked occupied.');
    }

    public function cancelAdCampaign(Request $request, int $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $campaign = AdCampaign::findOrFail($id);

        try {
            $this->adService->cancelCampaign(
                $campaign,
                auth()->user(),
                $request->input('reason')
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Campaign cancelled.');
    }

    public function adInvoices(Request $request)
    {
        $query = AdInvoice::with(['campaign.unit', 'landlord']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('landlord', fn($q) => $q->where('name', 'like', "%{$term}%"));
            });
        }

        $invoices = $query->latest()->paginate(20)->withQueryString();

        $totals = [
            'paid'    => (float) AdInvoice::where('status', AdInvoice::STATUS_PAID)->sum('amount'),
            'pending' => (float) AdInvoice::where('status', AdInvoice::STATUS_PENDING)->sum('amount'),
            'count'   => AdInvoice::count(),
        ];

        return view('admin.ads.invoices', compact('invoices', 'totals'));
    }

    /* =========================================================
     *  DESTROY — permanently delete an ad campaign
     * ========================================================= */
    /**
     * Only safe when the campaign has no invoices (no financial record to
     * preserve). Active or pending campaigns must be cancelled first.
     */
    public function destroyAdCampaign(int $id)
    {
        $campaign = AdCampaign::with('invoices')->findOrFail($id);

        // Guard 1: cannot delete if it ever took money.
        $hasPaidInvoices = $campaign->invoices()
            ->where('status', AdInvoice::STATUS_PAID)
            ->exists();

        if ($hasPaidInvoices) {
            return back()->with(
                'error',
                'Cannot delete this campaign — it has paid invoices. Cancel it instead to preserve the financial record.'
            );
        }

        // Guard 2: cannot delete if it has any invoice records at all.
        $hasAnyInvoices = $campaign->invoices()->exists();

        if ($hasAnyInvoices) {
            return back()->with(
                'error',
                'Cannot delete this campaign — it has invoice records. Cancel it instead to preserve the audit trail.'
            );
        }

        // Guard 3: active or pending campaigns must be cancelled first.
        if (in_array($campaign->status, [
            AdCampaign::STATUS_ACTIVE,
            AdCampaign::STATUS_PENDING_PAYMENT,
        ], true)) {
            return back()->with(
                'error',
                'Active or pending campaigns must be cancelled before they can be deleted.'
            );
        }

        try {
            DB::beginTransaction();

            // Release the unit if it was somehow marked unavailable by this
            // campaign. (In practice, terminal campaigns already released it,
            // but this is defensive.)
            $unit = $campaign->unit;
            if ($unit && $unit->status === PropertyUnit::STATUS_OCCUPIED) {
                $unit->status = PropertyUnit::STATUS_AVAILABLE;
                $unit->save();
            }

            $campaign->delete();

            DB::commit();

            Log::info('Ad campaign deleted', [
                'campaign_id'    => $id,
                'campaign_uuid'  => $campaign->uuid,
                'admin_id'       => auth()->id(),
                'previous_status'=> $campaign->status,
            ]);

            return back()->with('success', 'Campaign deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete ad campaign', [
                'campaign_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to delete campaign: ' . $e->getMessage());
        }
    }

    /* =========================================================
     *  SHARE TRACKING
     * ========================================================= */

    /**
     * Record that an admin shared a campaign to a specific channel.
     *
     * @route POST /admin/homepage-content/ads/campaigns/{id}/track-share
     */
    public function trackShare(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'channel'    => ['required', 'string', 'in:' . implode(',', SocialShare::CHANNELS)],
            'shared_url' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $campaign = AdCampaign::findOrFail($id);

        // Sanity guard: don't record shares for campaigns that shouldn't
        // be shared.
        $shareableStatuses = [
            AdCampaign::STATUS_ACTIVE,
            AdCampaign::STATUS_PAUSED,
            AdCampaign::STATUS_RENTED_EARLY,
            AdCampaign::STATUS_EXPIRED,
        ];
        if (!in_array($campaign->status, $shareableStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => "Campaign cannot be shared in its current state ({$campaign->status}).",
            ], 422);
        }

        // Deduplicate: don't record the same channel twice within 30s.
        $recentDuplicate = SocialShare::forCampaign($campaign->id)
            ->forChannel($request->input('channel'))
            ->where('shared_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($recentDuplicate) {
            return response()->json([
                'success'   => true,
                'message'   => 'Share already recorded (deduplicated).',
                'duplicate' => true,
            ]);
        }

        $share = SocialShare::create([
            'ad_campaign_id' => $campaign->id,
            'shared_by'      => auth()->id(),
            'channel'        => $request->input('channel'),
            'shared_url'     => $request->input('shared_url'),
            'shared_at'      => now(),
        ]);

        Log::info('Ad campaign shared to social', [
            'share_id'    => $share->id,
            'campaign_id' => $campaign->id,
            'channel'     => $share->channel,
            'admin_id'    => auth()->id(),
        ]);

        return response()->json([
            'success'       => true,
            'share_id'      => $share->id,
            'channel'       => $share->channel,
            'shared_at'     => $share->shared_at->toDateTimeString(),
            'shared_at_iso' => $share->shared_at->toIso8601String(),
            'count'         => SocialShare::forCampaign($campaign->id)->count(),
        ]);
    }

    /**
     * List share history for a campaign (JSON).
     *
     * @route GET /admin/homepage-content/ads/campaigns/{id}/shares
     */
    public function listShares(int $id)
    {
        $campaign = AdCampaign::findOrFail($id);

        $shares = SocialShare::with('sharer')
            ->forCampaign($campaign->id)
            ->recentFirst()
            ->limit(50)
            ->get()
            ->map(function (SocialShare $share) {
                return [
                    'id'         => $share->id,
                    'channel'    => $share->channel,
                    'shared_url' => $share->shared_url,
                    'shared_at'  => $share->shared_at->toIso8601String(),
                    'sharer'     => $share->sharer ? [
                        'id'   => $share->sharer->id,
                        'name' => $share->sharer->name,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'count'   => $shares->count(),
            'shares'  => $shares,
        ]);
    }

    /* =========================================================
     *  HELPERS
     * ========================================================= */

    protected function isThemeCustomised(Request $request, SystemSetting $settings): bool
    {
        $incoming = $request->input('theme', []);
        if (empty($incoming)) {
            return false;
        }

        if (($incoming['preset'] ?? null) && $incoming['preset'] !== 'custom') {
            return false;
        }

        return true;
    }

    protected function isLoginThemeCustomised(Request $request): bool
    {
        $incoming = $request->input('login.theme', []);
        if (empty($incoming)) {
            return false;
        }

        if (($incoming['preset'] ?? null) && $incoming['preset'] !== 'custom') {
            return false;
        }

        return true;
    }

    protected function mapHomepagePresetToLogin(array $presetValues): array
    {
        return [
            'colors' => [
                'primary'    => $presetValues['colors']['primary']    ?? null,
                'secondary'  => $presetValues['colors']['secondary']  ?? null,
                'accent'     => $presetValues['colors']['accent']     ?? null,
                'panel_bg'   => $presetValues['colors']['hero_bg']    ?? null,
                'panel_text' => $presetValues['colors']['hero_text']  ?? null,
                'form_bg'    => $presetValues['colors']['primary']    ?? null,
                'form_text'  => $presetValues['colors']['hero_text']  ?? null,
            ],
            'fonts'   => $presetValues['fonts']   ?? [],
            'layout'  => [
                'radius_style' => $presetValues['layout']['radius_style'] ?? null,
                'card_shadow'  => true,
            ],
            'buttons' => $presetValues['buttons'] ?? [],
        ];
    }

    protected function arrayReplaceRecursiveDeep(array $defaults, array $stored): array
    {
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = $this->arrayReplaceRecursiveDeep($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }
        return $defaults;
    }

    protected function safeCount(string $modelClass, ?callable $constraint = null): int
    {
        try {
            if (!class_exists($modelClass)) {
                return 0;
            }
            $query = $modelClass::query();
            if ($constraint !== null) {
                $query = $constraint($query) ?? $query;
            }
            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}