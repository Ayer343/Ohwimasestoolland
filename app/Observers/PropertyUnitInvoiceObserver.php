<?php

namespace App\Observers;

use App\Models\PropertyUnitInvoice;

class PropertyUnitInvoiceObserver
{
    /**
     * Before saving: recompute status and balance from amount_paid.
     */
    public function saving(PropertyUnitInvoice $invoice): void
    {
        $amount = (float) $invoice->amount;
        $paid   = (float) $invoice->amount_paid;

        // Auto-mark as paid when fully paid
        if ($paid >= $amount && $amount > 0) {
            $invoice->status = PropertyUnitInvoice::STATUS_PAID;
            if (!$invoice->paid_at) {
                $invoice->paid_at = now();
            }
        }
        // Partial
        elseif ($paid > 0 && $paid < $amount) {
            if ($invoice->status !== PropertyUnitInvoice::STATUS_VOID) {
                $invoice->status = PropertyUnitInvoice::STATUS_PARTIAL;
            }
        }
        // Pending (unpaid)
        elseif ($paid <= 0) {
            if ($invoice->status !== PropertyUnitInvoice::STATUS_VOID
                && $invoice->status !== PropertyUnitInvoice::STATUS_OVERDUE) {
                $invoice->status = PropertyUnitInvoice::STATUS_PENDING;
            }
        }

        // Auto-mark overdue if past due and not paid
        if ($invoice->due_date
            && $invoice->due_date->isPast()
            && !in_array($invoice->status, [
                PropertyUnitInvoice::STATUS_PAID,
                PropertyUnitInvoice::STATUS_VOID,
            ])) {
            $invoice->status = PropertyUnitInvoice::STATUS_OVERDUE;
        }

        // Record when the last payment happened
        if ($paid > 0 && !$invoice->last_payment_at) {
            $invoice->last_payment_at = now();
        }
    }

    /**
     * After creating: void any conflicting duplicate advance invoice.
     * (Guardrail — only one advance invoice per lease is meaningful.)
     */
    public function created(PropertyUnitInvoice $invoice): void
    {
        if ($invoice->invoice_type !== PropertyUnitInvoice::TYPE_ADVANCE_RENT) {
            return;
        }

        PropertyUnitInvoice::where('lease_id', $invoice->lease_id)
            ->where('invoice_type', PropertyUnitInvoice::TYPE_ADVANCE_RENT)
            ->where('id', '!=', $invoice->id)
            ->whereIn('status', [
                PropertyUnitInvoice::STATUS_PENDING,
                PropertyUnitInvoice::STATUS_PARTIAL,
            ])
            ->update([
                'status'      => PropertyUnitInvoice::STATUS_VOID,
                'void_reason' => 'Superseded by newer advance-rent invoice #' . $invoice->id,
                'voided_at'   => now(),
            ]);
    }
}