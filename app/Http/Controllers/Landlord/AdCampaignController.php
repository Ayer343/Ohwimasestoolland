<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdInvoice;
use App\Models\AdPricingPlan;
use App\Models\PropertyUnit;
use App\Services\AdCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdCampaignController extends Controller
{
    public function __construct(private AdCampaignService $adService) {}

    // ================================================================
    //  INDEX
    // ================================================================
    public function index()
    {
        $landlord = auth()->user();

        $campaigns = AdCampaign::forLandlord($landlord->id)
            ->with(['unit.property', 'plan', 'invoices'])
            ->latest()
            ->paginate(10);

        return view('landlord.ads.index', compact('campaigns'));
    }

    // ================================================================
    //  CREATE — show the campaign creation form
    // ================================================================
    public function create(Request $request)
    {
        $landlord = auth()->user();

        $availableUnits = PropertyUnit::whereHas('property', function ($q) use ($landlord) {
            $q->where('landlord_id', $landlord->id);
        })->where('status', PropertyUnit::STATUS_AVAILABLE)->with('property')->get();

        $plans = AdPricingPlan::active()->get();

        $config = \App\Models\SystemSetting::getSettings()->getAdConfig();

        return view('landlord.ads.create', compact('availableUnits', 'plans', 'config'));
    }

    // ================================================================
    //  STORE — persist a new campaign
    // ================================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_unit_id' => 'required|integer|exists:property_units,id',
            'ad_pricing_plan_id' => 'required|integer|exists:ad_pricing_plans,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:homepage,facebook,whatsapp,twitter,linkedin,instagram',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $landlord = auth()->user();
        $unit     = PropertyUnit::with('property')->findOrFail($validated['property_unit_id']);
        $plan     = AdPricingPlan::active()->findOrFail($validated['ad_pricing_plan_id']);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('ad-proofs', 'public');
        }

        try {
            $campaign = $this->adService->createCampaign(
                $landlord,
                $unit,
                $plan,
                $validated['channels'],
                $proofPath
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('landlord.ads.pay', [
            'uuid' => $campaign->uuid,
        ])->with('success', 'Campaign created. Please complete payment to go live.');
    }

    // ================================================================
    //  SHOW — single campaign detail page
    // ================================================================
    public function show(string $uuid)
    {
        $landlord = auth()->user();
        $campaign = AdCampaign::where('uuid', $uuid)
            ->forLandlord($landlord->id)
            ->with(['unit.property', 'plan', 'invoices'])
            ->firstOrFail();

        return view('landlord.ads.show', [
            'campaign'       => $campaign,
            'systemSettings' => \App\Models\SystemSetting::getSettings(),
        ]);
    }

    // ================================================================
    //  MARK RENTED — landlord requests to stop the campaign
    // ================================================================
    public function markRented(Request $request, string $uuid)
    {
        $landlord = auth()->user();
        $campaign = AdCampaign::where('uuid', $uuid)->forLandlord($landlord->id)->firstOrFail();

        try {
            $this->adService->requestMarkAsRented($campaign, $landlord);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request submitted. An admin will review and confirm.');
    }

    // ================================================================
    //  CANCEL — landlord cancels their own campaign
    // ================================================================
    public function cancel(Request $request, string $uuid)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $landlord = auth()->user();
        $campaign = AdCampaign::where('uuid', $uuid)->forLandlord($landlord->id)->firstOrFail();

        try {
            $this->adService->cancelCampaign($campaign, $landlord, $request->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Campaign cancelled.');
    }

    // ================================================================
    //  DESTROY — landlord permanently removes their own campaign
    // ================================================================
    /**
     * ✅ FIXED: Delete a landlord's own campaign.
     *
     * A landlord may delete a campaign ONLY when ALL of these hold:
     *
     *   1. Status ∈ {draft, cancelled, rejected}
     *        — draft       = created, never paid
     *        — cancelled   = explicitly ended (e.g. unit rented)
     *        — rejected    = admin rejected, no money taken
     *
     *   2. No FINANCIAL invoice on record.
     *        Financial invoices are: paid, refunded, failed.
     *        Pending and cancelled invoices do NOT block deletion —
     *        they represent no money movement and are cleaned up in
     *        the same transaction as the campaign.
     *
     *   3. No public activity (views_count = 0 AND clicks_count = 0).
     *        Once a campaign has been shown publicly, deleting would
     *        corrupt aggregate analytics.
     *
     * The delegation to `$campaign->canBeDeletedByLandlord()` means
     * this guard and the index blade's Delete button visibility are
     * guaranteed to agree — they call the same method.
     *
     * Soft delete is used (the model has `SoftDeletes`), so the row
     * stays in the DB for admin audit and any financial reports.
     */
    public function destroy(string $uuid)
    {
        $landlord = auth()->user();

        $campaign = AdCampaign::where('uuid', $uuid)
            ->forLandlord($landlord->id)
            ->with('invoices')
            ->firstOrFail();

        // ── Guard: delegate to the model — single source of truth.
        if (!$campaign->canBeDeletedByLandlord()) {
            $reason = $campaign->landlordDeleteBlockReason();

            Log::warning('Landlord delete refused', [
                'campaign_id'   => $campaign->id,
                'campaign_uuid' => $uuid,
                'landlord_id'   => $landlord->id,
                'status'        => $campaign->status,
                'has_financial' => $campaign->hasFinancialInvoice(),
                'has_activity'  => $campaign->hasPublicActivity(),
                'reason'        => $reason,
            ]);

            return back()->with('error', $reason);
        }

        // ── All guards passed — soft-delete the campaign AND any
        //    pending/cancelled invoices attached to it (they represent
        //    no money movement and shouldn't dangle).
        $campaignId     = $campaign->id;
        $previousStatus = $campaign->status;
        $unitId         = $campaign->property_unit_id;

        try {
            DB::beginTransaction();

            // Clean up any non-financial invoices so no orphaned rows
            // point at a soft-deleted campaign.
            $invoicesDeleted = $campaign->invoices()
                ->whereIn('status', [
                    AdInvoice::STATUS_PENDING,
                    AdInvoice::STATUS_CANCELLED,
                ])
                ->delete();

            $campaign->delete(); // soft delete

            DB::commit();

            Log::info('Landlord deleted own ad campaign', [
                'campaign_id'      => $campaignId,
                'campaign_uuid'    => $uuid,
                'landlord_id'      => $landlord->id,
                'unit_id'          => $unitId,
                'previous_status'  => $previousStatus,
                'invoices_deleted' => $invoicesDeleted,
            ]);

            return redirect()
                ->route('landlord.ads.index')
                ->with('success', 'Campaign deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to delete landlord ad campaign', [
                'campaign_id' => $campaignId,
                'landlord_id' => $landlord->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return back()->with(
                'error',
                'Failed to delete campaign: ' . $e->getMessage()
            );
        }
    }
}