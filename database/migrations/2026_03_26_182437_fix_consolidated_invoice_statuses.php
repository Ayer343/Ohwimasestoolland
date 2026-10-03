<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixConsolidatedInvoiceStatuses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fix 1: Change any invoices that are marked as 'paid' but have a bulk_payment_id
        // These should be 'consolidated' instead
        $fixedFromPaid = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('bulk_payment_id')
            ->update([
                'status' => 'consolidated',
                'payment_date' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'notes' => DB::raw("CONCAT(IFNULL(notes, ''), '\n⚠️ Fixed: This invoice was incorrectly marked as paid but is actually part of a bulk payment. Status changed to consolidated.')")
            ]);
        
        // Fix 2: Ensure consolidated invoices have no payment info
        $cleanedPaymentInfo = DB::table('invoices')
            ->where('status', 'consolidated')
            ->where(function($query) {
                $query->whereNotNull('payment_date')
                      ->orWhereNotNull('payment_method')
                      ->orWhereNotNull('payment_reference');
            })
            ->update([
                'payment_date' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'notes' => DB::raw("CONCAT(IFNULL(notes, ''), '\n✅ Fixed: Removed incorrect payment information from consolidated invoice.')")
            ]);
        
        // Fix 3: Ensure bulk invoices that are paid have the correct status
        $fixedBulkStatus = DB::table('invoices')
            ->where('is_bulk_payment', true)
            ->where('status', 'processing')
            ->whereNotNull('payment_date')
            ->update([
                'status' => 'paid'
            ]);
        
        // Log the results (using DB::statement for logging instead of console output)
        \Log::info('Fixed consolidated invoice statuses', [
            'invoices_fixed_from_paid_to_consolidated' => $fixedFromPaid,
            'consolidated_invoices_with_clean_payment_info' => $cleanedPaymentInfo,
            'bulk_invoices_fixed_status' => $fixedBulkStatus,
            'note' => 'Consolidated invoices are now correctly marked and do not count as paid'
        ]);
        
        // We'll output to console using echo since we can't use $this->command
        // This will show in the migration output
        echo "\n✅ Fixed {$fixedFromPaid} invoices from paid to consolidated";
        echo "\n✅ Cleaned payment info for {$cleanedPaymentInfo} consolidated invoices";
        echo "\n✅ Fixed status for {$fixedBulkStatus} bulk invoices\n";
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // This migration cannot be safely reversed
        echo "\n⚠️ This migration cannot be reversed. Please restore from backup if needed.\n";
    }
}