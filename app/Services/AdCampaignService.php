<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdInvoice;
use App\Models\AdPricingPlan;
use App\Models\PropertyUnit;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdCampaignService
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * Create a campaign + invoice. Returns the campaign.
     *
     * @throws \RuntimeException when validation fails
     */
    public function createCampaign(
        User $landlord,
        PropertyUnit $unit,
        AdPricingPlan $plan,
        array $channels,
        ?string $proofPath = null
    ): AdCampaign {
        if ((int) $unit->property->landlord_id !== (int) $landlord->id) {
            throw new \RuntimeException('You do not own this unit.');
        }

        if ($unit->status !== PropertyUnit::STATUS_AVAILABLE) {
            throw new \RuntimeException('Only available units can be advertised.');
        }

        $config = SystemSetting::getSettings()->getAdConfig();
        if (empty($config['enabled'])) {
            throw new \RuntimeException('Ad space is currently disabled.');
        }

        $activeCount = AdCampaign::forLandlord($landlord->id)
            ->whereIn('status', [AdCampaign::STATUS_PENDING_PAYMENT, AdCampaign::STATUS_ACTIVE])
            ->count();

        if ($activeCount >= (int) $config['max_active_per_landlord']) {
            throw new \RuntimeException(
                'You already have ' . $activeCount . ' active or pending campaigns. '
                . 'Maximum allowed: ' . $config['max_active_per_landlord'] . '.'
            );
        }

        $existing = AdCampaign::where('property_unit_id', $unit->id)
            ->whereIn('status', [AdCampaign::STATUS_PENDING_PAYMENT, AdCampaign::STATUS_ACTIVE])
            ->exists();

        if ($existing) {
            throw new \RuntimeException('This unit already has an active or pending campaign.');
        }

        $channels = array_values(array_intersect(
            $channels,
            array_keys(array_filter($config['channels']))
        ));

        if (empty($channels)) {
            throw new \RuntimeException('Select at least one channel.');
        }

        return DB::transaction(function () use ($landlord, $unit, $plan, $channels, $proofPath) {
            $campaign = AdCampaign::create([
                'property_unit_id'    => $unit->id,
                'landlord_id'         => $landlord->id,
                'ad_pricing_plan_id'  => $plan->id,
                'plan_name'           => $plan->name,
                'billing_period'      => $plan->billing_period,
                'duration_months'     => $plan->duration_months,
                'unit_price_snapshot' => $plan->price,
                'total_paid'          => 0,
                'currency'            => $plan->currency,
                'status'              => AdCampaign::STATUS_PENDING_PAYMENT,
                'channels'            => $channels,
                'rented_proof_path'   => $proofPath,
            ]);

            AdInvoice::create([
                'ad_campaign_id' => $campaign->id,
                'landlord_id'    => $landlord->id,
                'amount'         => $plan->price,
                'currency'       => $plan->currency,
                'status'         => AdInvoice::STATUS_PENDING,
            ]);

            Log::info('Ad campaign created', [
                'campaign_id' => $campaign->id,
                'landlord_id' => $landlord->id,
                'unit_id'     => $unit->id,
                'plan_id'     => $plan->id,
                'amount'      => $plan->price,
                'channels'    => $channels,
            ]);

            return $campaign;
        });
    }

    public function markInvoicePaid(AdInvoice $invoice, string $gateway, ?string $reference = null, array $payload = []): void
    {
        DB::transaction(function () use ($invoice, $gateway, $reference, $payload) {
            $invoice->markPaid($gateway, $reference, $payload);

            $campaign = $invoice->campaign;
            if (!$campaign) {
                return;
            }

            $campaign->total_paid = (float) $campaign->total_paid + (float) $invoice->amount;
            $campaign->save();

            // Activate if this was the first successful payment.
            if ($campaign->status === AdCampaign::STATUS_PENDING_PAYMENT) {
                $campaign->markAsActive();
            }
        });

        Log::info('Ad invoice marked paid', [
            'invoice_id'  => $invoice->id,
            'campaign_id' => $invoice->ad_campaign_id,
            'gateway'     => $gateway,
        ]);
    }

    public function requestMarkAsRented(AdCampaign $campaign, User $landlord): void
    {
        if ((int) $campaign->landlord_id !== (int) $landlord->id) {
            throw new \RuntimeException('Unauthorized.');
        }
        if (!$campaign->canBeMarkedRented()) {
            throw new \RuntimeException('This campaign cannot be marked as rented.');
        }

        $config = SystemSetting::getSettings()->getAdConfig();

        DB::transaction(function () use ($campaign, $landlord, $config) {
            $campaign->rented_requested_at = now();

            if (!empty($config['auto_approve_mark_rented'])) {
                $campaign->status             = AdCampaign::STATUS_RENTED_EARLY;
                $campaign->ends_at            = now();
                $campaign->rented_approved_at = now();
                $campaign->rented_reviewed_by = null; // auto
            }

            $campaign->save();

            Log::info('Ad campaign rented request', [
                'campaign_id' => $campaign->id,
                'landlord_id' => $landlord->id,
                'auto'        => !empty($config['auto_approve_mark_rented']),
            ]);
        });
    }

    public function approveRentedEarly(AdCampaign $campaign, User $admin): void
    {
        if ($campaign->status !== AdCampaign::STATUS_ACTIVE) {
            throw new \RuntimeException('Only active campaigns can be marked rented.');
        }
        if ($campaign->rented_requested_at === null) {
            throw new \RuntimeException('Landlord has not requested mark-as-rented.');
        }

        DB::transaction(function () use ($campaign, $admin) {
            $campaign->markAsRentedEarly($admin);

            // Cancel any pending invoices so no further charges accrue.
            $campaign->invoices()
                ->where('status', AdInvoice::STATUS_PENDING)
                ->update(['status' => AdInvoice::STATUS_CANCELLED]);

            // Move the unit to occupied, through the existing model helper.
            $unit = $campaign->unit;
            if ($unit) {
                $unit->status = PropertyUnit::STATUS_OCCUPIED;
                $unit->save();
            }
        });

        Log::info('Ad campaign marked rented by admin', [
            'campaign_id' => $campaign->id,
            'admin_id'    => $admin->id,
        ]);
    }

    /**
     * Fetch the homepage featured ad payload — eager loaded for the welcome page.
     */
    public function homepageAds(int $limit = 6): \Illuminate\Support\Collection
    {
        $config = SystemSetting::getSettings()->getAdConfig();
        if (empty($config['enabled'])) {
            return collect();
        }

        $max = min((int) $config['max_on_homepage'], $limit);

        return AdCampaign::activeNow()
            ->whereJsonContains('channels', 'homepage')
            ->with(['unit.photos', 'unit.property', 'landlord'])
            ->orderByDesc('sort_weight')
            ->inRandomOrder()
            ->limit($max)
            ->get();
    }

    /**
     * ✅ UPDATED: cancel a campaign with admin-bypass and diagnostic
     *    logging so "ad cannot be cancelled" is never opaque again.
     *
     * Rules:
     *   - Owner OR admin can cancel.
     *   - Admins bypass the landlord state check entirely.
     *   - Landlords can cancel ACTIVE / PAUSED / DRAFT / PENDING_PAYMENT
     *     (i.e. anything non-terminal) — the model's
     *     `canBeCancelledByLandlord()` is the source of truth for that list.
     *   - Terminal states (CANCELLED / EXPIRED / RENTED_EARLY / REJECTED)
     *     are a safe no-op — cancelling them returns successfully.
     *   - Every refused or attempted cancel logs actor type, roles,
     *     campaign status, and which guard fired.
     *
     * @throws \RuntimeException when the actor is neither owner nor admin,
     *                            or when a landlord tries to cancel a
     *                            non-cancellable state.
     */
    public function cancelCampaign(AdCampaign $campaign, User $actor, ?string $reason = null): void
    {
        $isOwner = (int) $campaign->landlord_id === (int) $actor->id;
        $isAdmin = $actor->isAdmin() || $actor->isSuperAdmin();

        // Diagnostic before any decision — the log entry is the first
        // thing to check when a cancel is refused.
        Log::info('AdCampaignService::cancelCampaign guard', [
            'campaign_id'     => $campaign->id,
            'campaign_status' => $campaign->status,
            'actor_id'        => $actor->id,
            'actor_type'      => $actor->type ?? null,
            'is_owner'        => $isOwner,
            'is_admin'        => $isAdmin,
            'is_super_admin'  => method_exists($actor, 'isSuperAdmin') ? $actor->isSuperAdmin() : null,
            'actor_roles'     => method_exists($actor, 'getRoleNames')
                ? $actor->getRoleNames()->all()
                : null,
            'can_landlord_cancel' => method_exists($campaign, 'canBeCancelledByLandlord')
                ? $campaign->canBeCancelledByLandlord()
                : null,
        ]);

        // Authorization: owner OR admin.
        if (!$isOwner && !$isAdmin) {
            throw new \RuntimeException(
                'Unauthorized: only the campaign owner or an administrator can cancel this campaign.'
            );
        }

        // Landlord-specific state check — admins bypass this.
        if (!$isAdmin && $isOwner && !$campaign->canBeCancelledByLandlord()) {
            throw new \RuntimeException(
                "This campaign cannot be cancelled from its current state: {$campaign->status}. "
                . 'Only active, paused, draft, or pending-payment campaigns can be cancelled by the owner.'
            );
        }

        // Terminal states are already resolved — treat cancel as a no-op
        // but log it so we know the request arrived and was handled.
        $terminalStatuses = [
            AdCampaign::STATUS_CANCELLED,
            AdCampaign::STATUS_EXPIRED,
            AdCampaign::STATUS_RENTED_EARLY,
            AdCampaign::STATUS_REJECTED,
        ];

        if (in_array($campaign->status, $terminalStatuses, true)) {
            Log::info('Ad campaign cancel is a no-op (already terminal)', [
                'campaign_id' => $campaign->id,
                'status'      => $campaign->status,
                'actor_id'    => $actor->id,
            ]);
            return;
        }

        $previousStatus = $campaign->status;

        DB::transaction(function () use ($campaign, $reason) {
            $campaign->cancel($reason);

            // Cancel any pending invoices so no further charges accrue.
            $campaign->invoices()
                ->where('status', AdInvoice::STATUS_PENDING)
                ->update(['status' => AdInvoice::STATUS_CANCELLED]);
        });

        Log::info('Ad campaign cancelled', [
            'campaign_id'  => $campaign->id,
            'actor_id'     => $actor->id,
            'actor_kind'   => $isAdmin ? 'admin' : 'landlord',
            'old_status'   => $previousStatus,
            'new_status'   => $campaign->status,
            'reason'       => $reason,
        ]);
    }
}