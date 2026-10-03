<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeveloperPaymentController extends Controller
{
    use AuditLogger;

    /**
     * ✅ List all payments (replaces pendingPayments)
     * Shows both auto-confirmed and manual payments
     */
    public function paymentHistory(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Get developer settings
            $developerSettings = \App\Models\DeveloperSetting::first();
            if (!$developerSettings) {
                return redirect()->back()->with('error', 'Billing settings not configured');
            }
            
            $payments = DB::table('agreement_payments as p')
                ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                ->join('users as u', 'a.super_admin_id', '=', 'u.id')
                ->where('a.developer_setting_id', $developerSettings->id)
                ->select('p.*', 'a.agreement_number', 'u.name as super_admin_name', 'u.email as super_admin_email')
                ->orderBy('p.created_at', 'desc')
                ->paginate($request->per_page ?? 20)
                ->withQueryString();
            
            // Get stats
            $stats = [
                'total_payments' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->count(),
                'total_amount' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->sum('p.amount_paid'),
                'confirmed_payments' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->where('p.status', 'confirmed')
                    ->count(),
                'pending_payments' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->where('p.status', 'pending_confirmation')
                    ->count(),
                'today_payments' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->whereDate('p.payment_date', today())
                    ->count(),
            ];
            
            return view('developer.payments.history', compact('payments', 'stats'));
            
        } catch (\Exception $e) {
            Log::error('Failed to load payment history: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payment history: ' . $e->getMessage());
        }
    }

    /**
     * ✅ View payment details - KEEP (required)
     */
    public function viewPaymentDetails($paymentId)
    {
        try {
            $payment = DB::table('agreement_payments')->find($paymentId);
            
            if (!$payment) {
                abort(404, 'Payment record not found');
            }
            
            $agreement = AdminBillingRecord::find($payment->admin_billing_record_id);
            
            // Get payment details with user info
            $paymentDetails = DB::table('agreement_payments as p')
                ->leftJoin('users as recorded_by', 'p.recorded_by', '=', 'recorded_by.id')
                ->leftJoin('users as confirmed_by', 'p.confirmed_by', '=', 'confirmed_by.id')
                ->leftJoin('users as rejected_by', 'p.rejected_by', '=', 'rejected_by.id')
                ->select('p.*', 
                    'recorded_by.name as recorded_by_name',
                    'recorded_by.email as recorded_by_email',
                    'confirmed_by.name as confirmed_by_name',
                    'rejected_by.name as rejected_by_name'
                )
                ->where('p.id', $paymentId)
                ->first();
            
            return view('developer.payments.payment-details', compact('paymentDetails', 'agreement'));
            
        } catch (\Exception $e) {
            Log::error('Failed to view payment details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payment details');
        }
    }

    /**
     * ✅ Download payment proof - KEEP (required)
     */
    public function downloadPaymentProof($paymentId)
    {
        try {
            $payment = DB::table('agreement_payments')->find($paymentId);
            
            if (!$payment) {
                abort(404, 'Payment record not found');
            }
            
            if (!$payment->receipt_path && !$payment->provider_data) {
                return redirect()->back()->with('error', 'No payment proof available');
            }
            
            // Check if it's a provider proof (online payment)
            if ($payment->provider_data) {
                $providerData = json_decode($payment->provider_data, true);
                if (isset($providerData['receipt_url'])) {
                    // Redirect to provider's receipt
                    return redirect($providerData['receipt_url']);
                }
            }
            
            // If it's a local file
            if ($payment->receipt_path) {
                $path = storage_path('app/public/' . $payment->receipt_path);
                
                if (!file_exists($path)) {
                    return redirect()->back()->with('error', 'File not found');
                }
                
                return response()->download($path, 'payment-proof-' . $payment->payment_reference . '.' . pathinfo($path, PATHINFO_EXTENSION));
            }
            
            return redirect()->back()->with('error', 'No payment proof available');
            
        } catch (\Exception $e) {
            Log::error('Failed to download payment proof: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error downloading file');
        }
    }

    /**
     * ⚠️ Fallback: Super Admin records offline payment (emergency use only)
     * This should be rarely used - only when online payment fails
     */
    public function recordOfflinePayment(Request $request, $agreementId)
    {
        // This is the same as the old recordOwnPayment but with clear warning
        // ... keep existing code but add warning that this is for offline/emergency only
    }

    /**
     * ❌ DEPRECATED: Manual confirmation (replaced by webhook)
     * Keep for backward compatibility but redirect to dashboard
     */
    public function confirmAgreementPayment(Request $request, $agreementId)
    {
        Log::warning('Manual payment confirmation called - this is deprecated with online payments', [
            'agreement_id' => $agreementId,
            'user_id' => auth()->id()
        ]);
        
        return redirect()->route('developer.billing.dashboard')
            ->with('info', 'Online payments are automatically confirmed. This feature is deprecated.');
    }

    /**
     * ❌ DEPRECATED: Manual rejection (replaced by webhook)
     */
    public function rejectSuperAdminPayment(Request $request, $paymentId)
    {
        Log::warning('Manual payment rejection called - this is deprecated with online payments', [
            'payment_id' => $paymentId,
            'user_id' => auth()->id()
        ]);
        
        return redirect()->route('developer.billing.dashboard')
            ->with('info', 'Online payments are automatically verified. Manual rejection is not needed.');
    }

    /**
     * ✅ Payment statistics dashboard
     */
    public function paymentStatistics(Request $request)
    {
        try {
            $developerSettings = \App\Models\DeveloperSetting::first();
            if (!$developerSettings) {
                return redirect()->back()->with('error', 'Billing settings not configured');
            }
            
            $stats = [
                'total_agreements' => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->count(),
                'active_agreements' => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                    ->where('status', 'active')->count(),
                'total_paid' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->where('p.status', 'confirmed')
                    ->sum('p.amount_paid'),
                'total_pending' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->where('p.status', 'pending_confirmation')
                    ->sum('p.amount_paid'),
                'monthly_revenue' => DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->where('p.status', 'confirmed')
                    ->whereMonth('p.payment_date', now()->month)
                    ->whereYear('p.payment_date', now()->year)
                    ->sum('p.amount_paid'),
            ];
            
            // Get payment method breakdown
            $methodBreakdown = DB::table('agreement_payments as p')
                ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                ->where('a.developer_setting_id', $developerSettings->id)
                ->where('p.status', 'confirmed')
                ->select('p.payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(p.amount_paid) as total'))
                ->groupBy('p.payment_method')
                ->get();
            
            return view('developer.payments.statistics', compact('stats', 'methodBreakdown'));
            
        } catch (\Exception $e) {
            Log::error('Failed to load payment statistics: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading statistics');
        }
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    private function sanitizeInput($value)
    {
        if (is_string($value)) {
            return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }
}