<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdPricingPlan;
use App\Models\SystemSetting;
use App\Services\AdCampaignService;
use Illuminate\Http\Request;

class AdSettingsController extends Controller
{
    public function __construct(private AdCampaignService $adService) {}

    public function index()
    {
        $settings = SystemSetting::getSettingsOrFail();
        $config   = $settings->getAdConfig();
        $plans    = AdPricingPlan::latest()->get();

        $stats = [
            'active'  => AdCampaign::where('status', AdCampaign::STATUS_ACTIVE)->count(),
            'pending' => AdCampaign::where('status', AdCampaign::STATUS_PENDING_PAYMENT)->count(),
            'rented'  => AdCampaign::where('status', AdCampaign::STATUS_RENTED_EARLY)->count(),
        ];

        return view('admin.ads.settings', compact('config', 'plans', 'stats', 'settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled'                 => 'boolean',
            'max_active_per_landlord' => 'integer|min:1|max:50',
            'max_on_homepage'         => 'integer|min:1|max:20',
            'require_admin_approval'  => 'boolean',
            'auto_approve_mark_rented'=> 'boolean',
            'channels'                => 'array',
            'channels.*'              => 'boolean',
        ]);

        $settings = SystemSetting::getSettingsOrFail();
        $current  = $settings->getAdConfig();
        $merged   = array_replace_recursive($current, $validated);
        $settings->setAdConfig($merged);

        return back()->with('success', 'Ad settings updated.');
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'billing_period'   => 'required|in:monthly,yearly',
            'duration_months'  => 'required|integer|min:1|max:24',
            'price'            => 'required|numeric|min:1',
            'currency'         => 'nullable|string|size:3',
            'description'      => 'nullable|string|max:500',
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        $validated['currency'] = $validated['currency'] ?? 'GHS';
        $validated['created_by'] = auth()->id();

        AdPricingPlan::create($validated);

        return back()->with('success', 'Pricing plan created.');
    }

    public function updatePlan(Request $request, int $id)
    {
        $plan = AdPricingPlan::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'price'       => 'required|numeric|min:1',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $plan->update($validated);

        return back()->with('success', 'Pricing plan updated.');
    }

    public function campaigns(Request $request)
    {
        $query = AdCampaign::with(['unit.property', 'landlord', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('rented_pending')) {
            $query->whereNotNull('rented_requested_at')->whereNull('rented_approved_at');
        }

        $campaigns = $query->latest()->paginate(20);

        return view('admin.ads.campaigns', compact('campaigns'));
    }

    public function approveRented(int $id)
    {
        $campaign = AdCampaign::findOrFail($id);

        try {
            $this->adService->approveRentedEarly($campaign, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Campaign marked as rented and stopped.');
    }

    public function cancelCampaign(Request $request, int $id)
    {
        $campaign = AdCampaign::findOrFail($id);

        try {
            $this->adService->cancelCampaign($campaign, auth()->user(), $request->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Campaign cancelled.');
    }
}