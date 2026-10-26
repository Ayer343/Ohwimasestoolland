<?php

namespace App\Observers;

use App\Models\AdminBillingRecord;
use App\Models\DeveloperSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AdminBillingRecord observer.
 *
 * Moved out of AppServiceProvider so the provider can stay focused on
 * wiring. Registered via AdminBillingRecord::observe(...) in
 * AppServiceProvider::registerObservers().
 *
 * IMPORTANT: Do NOT add duplicate listeners in the model's boot() —
 * that would double-fire every hook below.
 */
class AdminBillingRecordObserver
{
    /* ============================================================
     | CREATING — populate defaults
     * ============================================================ */

    public function creating(AdminBillingRecord $record): void
    {
        if (empty($record->invoice_number)) {
            $record->invoice_number = 'SA-REQ-' . strtoupper(uniqid());
        }

        if (empty($record->agreement_number) && method_exists($record, 'generateAgreementNumber')) {
            $record->agreement_number = $record->generateAgreementNumber();
        }

        if (empty($record->requested_by) && Auth::check()) {
            $record->requested_by = Auth::id();
            $record->requested_at = now();
        }

        if (empty($record->created_by) && Auth::check()) {
            $record->created_by = Auth::id();
        }

        if (empty($record->due_date) && !empty($record->start_date)) {
            $record->due_date = Carbon::parse($record->start_date)->addDays(30);
        }

        if (empty($record->payment_method)) {
            $record->payment_method = AdminBillingRecord::PAYMENT_METHOD_BANK_TRANSFER;
        }

        if (empty($record->billing_month)) {
            $record->billing_month = Carbon::now()->format('Y-m');
        }

        // description is NOT NULL with no default — backfill so a caller
        // who forgot it doesn't trigger a strict-mode error.
        if (empty($record->description)) {
            $record->description = 'System billing record — '
                . ($record->agreement_number ?? $record->invoice_number ?? 'pending');
        }
    }

    /* ============================================================
     | SAVING — normalize, guard, auto-flip
     * ============================================================ */

    public function saving(AdminBillingRecord $record): void
    {
        // (a) Trim whitespace on incoming status
        $incoming = $record->getAttributes()['status'] ?? null;
        if (is_string($incoming)) {
            $trimmed = trim($incoming);
            if ($trimmed !== $incoming) {
                $record->status = $trimmed;
            }
        }

        // (b) Never let status be wiped on an existing record
        if ($record->exists && $record->isDirty('status')) {
            $incoming = $record->getAttributes()['status'] ?? null;

            if ($incoming === '' || $incoming === null) {
                $existing = AdminBillingRecord::query()
                    ->whereKey($record->getKey())
                    ->value('status');

                if (!empty($existing)) {
                    $record->status = $existing;

                    Log::warning('[AdminBillingRecord] Blocked empty status overwrite', [
                        'record_id'        => $record->getKey(),
                        'preserved_status' => $existing,
                        'dirty_attributes' => array_keys($record->getDirty()),
                    ]);
                }
            }
        }

        // (c) Auto-flip to overdue from a live status
        if ($record->exists) {
            $currentStatus = $record->getAttributes()['status'] ?? null;

            if (in_array($currentStatus, [
                AdminBillingRecord::STATUS_ACTIVE,
                AdminBillingRecord::STATUS_APPROVED,
            ], true)) {
                $before = $currentStatus;
                $record->updateStatusBasedOnDueDate();
                $after = $record->getAttributes()['status'] ?? null;

                if ($after !== $before) {
                    Log::info('[AdminBillingRecord] Status auto-flipped on save', [
                        'record_id'        => $record->getKey(),
                        'from'             => $before,
                        'to'               => $after,
                        'dirty_attributes' => array_keys($record->getDirty()),
                    ]);
                }
            }
        }
    }

    /* ============================================================
     | AUDIT LOG
     * ============================================================ */

    public function created(AdminBillingRecord $record): void
    {
        Log::info('Admin billing record created', [
            'record_id'        => $record->id,
            'agreement_number' => $record->agreement_number,
            'amount'           => $record->amount,
            'super_admin_id'   => $record->super_admin_id,
            'is_primary'       => $record->is_primary_for_billing,
            'user_id'          => Auth::id(),
            'timestamp'        => now()->toDateTimeString(),
        ]);
    }

    public function updated(AdminBillingRecord $record): void
    {
        $changes = $record->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $original = $record->getOriginal();
        unset($original['updated_at']);

        Log::info('Admin billing record updated', [
            'record_id'        => $record->id,
            'agreement_number' => $record->agreement_number,
            'changes'          => $changes,
            'old_values'       => $original,
            'user_id'          => Auth::id(),
            'timestamp'        => now()->toDateTimeString(),
        ]);
    }

    public function deleted(AdminBillingRecord $record): void
    {
        Log::info('Admin billing record deleted', [
            'record_id'        => $record->id,
            'agreement_number' => $record->agreement_number,
            'record_data'      => $record->toArray(),
            'user_id'          => Auth::id(),
            'timestamp'        => now()->toDateTimeString(),
        ]);
    }

    /* ============================================================
     | CACHE INVALIDATION
     * ============================================================ */

    public function saved(AdminBillingRecord $record): void
    {
        DeveloperSetting::forgetCurrent();
    }
}