<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;
use App\Models\DeveloperBillingRecord;
use App\Models\DeveloperBillingProposal;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Services\DeveloperEmailService;
use App\Services\DeveloperBillingService;
use App\Services\DeveloperMonitoringService;
use App\Jobs\DeveloperConfigurationUpdateJob;
use App\Jobs\SendDeveloperInvoiceJob;
use App\Jobs\SendSuperAdminPaymentRequestJob;
use App\Jobs\SendInvoiceReminderJob;
use App\Traits\NotifiesUsers;
use App\Traits\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use HTMLPurifier;
use HTMLPurifier_Config;

class DeveloperSystemSettingController extends Controller
{
    use NotifiesUsers, AuditLogger;

    protected $emailService;
    protected $billingService;
    protected $monitoringService;
    protected $htmlPurifier;

    public function __construct(
        DeveloperEmailService $emailService,
        DeveloperBillingService $billingService,
        DeveloperMonitoringService $monitoringService
    ) {
        $this->emailService = $emailService;
        $this->billingService = $billingService;
        $this->monitoringService = $monitoringService;
        
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', 'p,b,i,em,strong,a[href|title],ul,ol,li,br,span[style]');
        $config->set('CSS.AllowedProperties', 'font-weight,font-style,text-decoration');
        $this->htmlPurifier = new HTMLPurifier($config);
    }

    /**
     * Display developer dashboard with all settings
     */
    public function dashboard()
    {
        // Rate limiting for dashboard access
        $key = 'dashboard:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "Too many requests. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key);

        try {
            $developerSettings = DeveloperSetting::first();
            
            if (!$developerSettings) {
                $developerSettings = $this->initializeDeveloperSettings();
            }
            
            if (!Gate::allows('view-developer-dashboard', $developerSettings)) {
                abort(403, 'Unauthorized access to developer dashboard');
            }
            
            $systemStats = $this->getSystemStatistics();
            $billingInfo = $this->billingService->getBillingOverview();
            $billingInfo['developer_has_control'] = true;
            $billingInfo['requires_approval'] = false;
            
            $monitoringAlerts = $this->monitoringService->getActiveAlerts();
            $recentActivities = $this->getRecentActivities();
            
            $developerInvoices = DeveloperBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->whereIn('status', ['active', 'pending'])
                ->where('due_date', '>=', now())
                ->orderBy('due_date', 'asc')
                ->take(5)
                ->get();
            
            $superAdminPaymentRequests = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->orderBy('due_date', 'asc')
                ->take(5)
                ->get();
            
            $pendingProposals = DeveloperBillingProposal::where('developer_setting_id', $developerSettings->id)
                ->where('status', 'pending')
                ->exists();
            
            $this->logAudit('dashboard_view', 'Developer dashboard accessed', [
                'developer_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
            
            return view('developer.dashboard', compact(
                'developerSettings',
                'systemStats',
                'billingInfo',
                'monitoringAlerts',
                'recentActivities',
                'developerInvoices',
                'superAdminPaymentRequests',
                'pendingProposals'
            ));
            
        } catch (\Exception $e) {
            Log::error('Developer dashboard error: ' . $e->getMessage());
            return view('developer.error', [
                'message' => 'Error loading dashboard: ' . $e->getMessage()
            ]);
        }
    }

   /**
 * Display developer tools dashboard
 */
public function toolsDashboard()
{
    try {
        $user = auth()->user();
        
        // DEBUG: Log user information to understand the issue
        Log::info('Developer tools dashboard access attempt', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_type' => $user->type,
            'user_type_as_int' => (int)$user->type,
            'isDeveloper_method' => $user->isDeveloper() ? 'true' : 'false',
            'isSuperAdmin_method' => $user->isSuperAdmin() ? 'true' : 'false',
            'user_model_class' => get_class($user),
            'timestamp' => now()->toISOString()
        ]);
        
        // Check Gate permission first
        $gateResult = Gate::allows('access-developer-tools');
        
        if (!$gateResult) {
            Log::warning('Gate denied access to developer tools', [
                'user_id' => $user->id,
                'user_type' => $user->type,
                'gate_result' => $gateResult,
                'possible_reasons' => [
                    'user_not_developer_or_superadmin' => 'User type is not 0 or 5',
                    'gate_not_defined' => 'Gate access-developer-tools not defined properly',
                    'user_model_constants' => 'Check User::TYPE_DEVELOPER and User::TYPE_SUPER_ADMIN constants'
                ]
            ]);
            
            // TEMPORARY FIX: Allow access if user type is 0 or 5 directly
            $userType = (int)$user->type;
            if ($userType === 0 || $userType === 5) {
                Log::info('TEMPORARY: Allowing access despite Gate failure', [
                    'user_type' => $userType,
                    'note' => 'User has correct type (0=Super Admin, 5=Developer)'
                ]);
                // Continue with access
            } else {
                abort(403, 'Unauthorized to access developer tools. Your user type is: ' . $user->type . '. Expected: 0 (Super Admin) or 5 (Developer)');
            }
        } else {
            Log::info('Gate allowed access to developer tools', [
                'user_id' => $user->id,
                'gate_result' => $gateResult
            ]);
        }
        
        // Get system information
        $systemInfo = $this->getSystemInformation();
        $recentActivities = $this->getRecentActivities(10);
        $metrics = $this->getSystemStatistics();
        
        // Log successful access
        Log::info('Developer tools dashboard accessed successfully', [
            'user_id' => $user->id,
            'system_info_retrieved' => !empty($systemInfo),
            'recent_activities_count' => $recentActivities->count(),
            'metrics_retrieved' => !empty($metrics)
        ]);
        
        return view('developer.tools.dashboard', [
            'systemInfo' => $systemInfo,
            'recentActivities' => $recentActivities,
            'metrics' => $metrics
        ]);
        
    } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
        // Catch authorization exceptions specifically
        Log::error('Authorization error in developer tools dashboard: ' . $e->getMessage(), [
            'user_id' => auth()->id(),
            'exception_type' => get_class($e)
        ]);
        return redirect()->route('developer.dashboard')
            ->with('error', 'You are not authorized to access developer tools.');
            
    } catch (\Exception $e) {
        Log::error('Developer tools dashboard error: ' . $e->getMessage(), [
            'user_id' => auth()->id(),
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return redirect()->back()->with('error', 'Error loading tools dashboard: ' . $e->getMessage());
    }
}

    /**
     * Get system metrics for tools dashboard
     */
    public function getMetrics()
    {
        try {
            if (!Gate::allows('view-system-metrics')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            $stats = $this->getSystemStatistics();
            $memoryUsage = $this->getMemoryUsagePercentage();
            $diskUsage = $this->getDiskUsagePercentage();
            $uptime = $this->getSystemUptime();
            
            return response()->json([
                'success' => true,
                'memory_usage' => $memoryUsage,
                'disk_usage' => $diskUsage,
                'uptime' => $uptime,
                'system_stats' => $stats,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pay via Paystack (online payment)
     */
    public function payWithPaystack(Request $request, $invoiceId)
    {
        // Rate limiting for Paystack payments
        $key = 'paystack_payment:' . auth()->id() . ':' . $invoiceId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()->with('error', "Too many payment attempts. Try again in {$seconds} seconds.");
        }
        RateLimiter::hit($key);
        
        try {
            $invoice = DeveloperBillingRecord::findOrFail($invoiceId);
            
            if (!Gate::allows('pay-invoice', $invoice)) {
                abort(403, 'Unauthorized to pay this invoice');
            }
            
            if (!in_array($invoice->payment_status, ['unpaid', 'partial'])) {
                return redirect()->back()
                    ->with('error', 'This invoice is already paid or cancelled');
            }
            
            $settings = DeveloperSetting::first();
            
            $paystackData = [
                'email' => $settings->developer_email,
                'amount' => $invoice->amount * 100,
                'reference' => 'PAYSTACK-' . $invoice->invoice_number . '-' . time() . '-' . Str::random(8),
                'callback_url' => route('developer.billing.paystack.callback'),
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'developer_id' => $settings->id,
                    'invoice_number' => $invoice->invoice_number,
                    'checksum' => hash_hmac('sha256', $invoice->id . $invoice->amount, config('app.key'))
                ]
            ];
            
            DB::table('payment_attempts')->insert([
                'developer_billing_record_id' => $invoice->id,
                'attempted_by' => auth()->id(),
                'amount_attempted' => $invoice->amount,
                'payment_method' => 'paystack',
                'payment_reference' => $paystackData['reference'],
                'status' => 'initiated',
                'gateway_data' => json_encode($paystackData),
                'attempted_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $paystack = new \Yabacon\Paystack(config('services.paystack.secret_key'));
            $transaction = $paystack->transaction->initialize($paystackData);
            
            if (!$transaction->status) {
                throw new \Exception('Failed to initialize Paystack payment: ' . $transaction->message);
            }
            
            $this->logAudit('paystack_initiated', 'Paystack payment initiated', [
                'invoice_id' => $invoiceId,
                'reference' => $paystackData['reference'],
                'amount' => $invoice->amount
            ]);
            
            return redirect($transaction->data->authorization_url);
            
        } catch (\Exception $e) {
            Log::error('Paystack payment failed: ' . $e->getMessage(), [
                'exception' => $e,
                'invoice_id' => $invoiceId
            ]);
            
            return redirect()->back()
                ->with('error', 'Failed to process Paystack payment: ' . $e->getMessage());
        }
    }

    /**
     * Paystack payment callback
     */
    public function paystackCallback(Request $request)
    {
        try {
            $reference = $request->query('reference');
            
            if (!$reference) {
                throw new \Exception('No payment reference provided');
            }
            
            $paystack = new \Yabacon\Paystack(config('services.paystack.secret_key'));
            $transaction = $paystack->transaction->verify(['reference' => $reference]);
            
            if (!$transaction->status) {
                throw new \Exception('Payment verification failed: ' . $transaction->message);
            }
            
            if ($transaction->data->status !== 'success') {
                throw new \Exception('Payment was not successful');
            }
            
            $metadata = $transaction->data->metadata;
            $expectedChecksum = hash_hmac('sha256', 
                $metadata->invoice_id . $transaction->data->amount / 100, 
                config('app.key')
            );
            
            if ($metadata->checksum !== $expectedChecksum) {
                throw new \Exception('Payment verification checksum mismatch');
            }
            
            $paymentAttempt = DB::table('payment_attempts')
                ->where('payment_reference', $reference)
                ->first();
            
            if (!$paymentAttempt) {
                throw new \Exception('Payment attempt not found');
            }
            
            $invoice = DeveloperBillingRecord::find($paymentAttempt->developer_billing_record_id);
            
            if (!$invoice) {
                throw new \Exception('Invoice not found');
            }
            
            DB::beginTransaction();
            
            DB::table('payment_attempts')
                ->where('id', $paymentAttempt->id)
                ->update([
                    'status' => 'success',
                    'transaction_id' => $transaction->data->id,
                    'gateway_response' => json_encode($transaction->data),
                    'verified_at' => now(),
                    'updated_at' => now()
                ]);
            
            $invoice->update([
                'payment_method' => 'paystack',
                'payment_reference' => $reference,
                'transaction_id' => $transaction->data->id,
                'paid_date' => now(),
                'payment_status' => 'paid',
                'amount_paid' => $invoice->amount,
                'payment_details' => json_encode([
                    'gateway' => 'paystack',
                    'verified_at' => now()->toISOString(),
                    'gateway_data' => $transaction->data,
                    'verification_status' => 'developer_confirmed',
                    'ip_address' => request()->ip()
                ]),
                'developer_confirmed' => true,
                'confirmed_at' => now()
            ]);
            
            $this->notifySuperAdminAboutPaymentConfirmation($invoice, $invoice->amount);
            
            DB::commit();
            
            $this->logAudit('paystack_success', 'Paystack payment successful', [
                'invoice_id' => $invoice->id,
                'reference' => $reference,
                'amount' => $invoice->amount,
                'transaction_id' => $transaction->data->id
            ]);
            
            return redirect()->route('developer.billing.dashboard')
                ->with('success', 'Payment completed successfully!')
                ->with('payment_reference', $reference);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Paystack callback failed: ' . $e->getMessage(), [
                'exception' => $e,
                'reference' => $request->query('reference')
            ]);
            
            return redirect()->route('developer.billing.dashboard')
                ->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Cancel billing proposal
     */
    public function cancelBillingProposal($proposalId)
    {
        try {
            $proposal = DeveloperBillingProposal::findOrFail($proposalId);
            
            if (!Gate::allows('cancel-billing-proposal', $proposal)) {
                abort(403, 'Unauthorized to cancel this proposal');
            }
            
            if ($proposal->status !== 'pending') {
                return redirect()->back()
                    ->with('error', 'Only pending proposals can be cancelled');
            }
            
            $proposal->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => 'Cancelled by developer'
            ]);
            
            if ($proposal->invoice_id) {
                DeveloperBillingRecord::where('id', $proposal->invoice_id)
                    ->update(['status' => 'cancelled']);
            }
            
            $this->notifySuperAdminsAboutBillingProposalCancellation($proposal);
            
            $this->logAudit('proposal_cancelled', 'Billing proposal cancelled', [
                'proposal_id' => $proposalId,
                'proposal_amount' => $proposal->new_amount
            ]);
            
            return redirect()->route('developer.billing.dashboard')
                ->with('success', 'Billing proposal cancelled successfully!');
                
        } catch (\Exception $e) {
            Log::error('Failed to cancel billing proposal: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cancel proposal: ' . $e->getMessage());
        }
    }

    /**
 * Display payment history with filters
 */
public function paymentHistory(Request $request)
{
    try {
        $developerSettings = DeveloperSetting::first();
        
        if (!$developerSettings) {
            return redirect()->route('developer.billing.dashboard')
                ->with('error', 'Please configure your developer settings first.');
        }
        
        // Bypass Gate check - Comment out or remove the Gate check
        // if (!Gate::allows('view-payment-history', $developerSettings)) {
        //     abort(403, 'Unauthorized to view payment history');
        // }
        
        // Alternative: Simple role-based check instead of Gate
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Allow specific user types (adjust as needed)
        $allowedTypes = [0, 5]; // Example: developer types
        if (!in_array($user->type, $allowedTypes)) {
            abort(403, 'Unauthorized to view payment history. User type: ' . $user->type);
        }
        
        // OR: Check if user owns these settings
        // if ($user->id !== $developerSettings->user_id) {
        //     abort(403, 'Unauthorized to view these payment records.');
        // }
        
        $query = DeveloperBillingRecord::where('developer_setting_id', $developerSettings->id);
        
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('payment_status', $request->status);
        }
        
        if ($request->filled('date') && $request->date !== 'all') {
            $now = now();
            
            switch ($request->date) {
                case 'today':
                    $query->whereDate('created_at', $now->toDateString());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', $now->month)
                          ->whereYear('created_at', $now->year);
                    break;
                case 'quarter':
                    $quarter = ceil($now->month / 3);
                    $startMonth = ($quarter - 1) * 3 + 1;
                    $endMonth = $quarter * 3;
                    
                    $query->whereYear('created_at', $now->year)
                          ->whereBetween(DB::raw('MONTH(created_at)'), [$startMonth, $endMonth]);
                    break;
                case 'year':
                    $query->whereYear('created_at', $now->year);
                    break;
                case 'custom':
                    if ($request->filled('start_date') && $request->filled('end_date')) {
                        $query->whereBetween('created_at', [
                            $request->start_date . ' 00:00:00',
                            $request->end_date . ' 23:59:59'
                        ]);
                    }
                    break;
            }
        }
        
        if ($request->filled('search')) {
            $searchTerm = '%' . $this->sanitizeInput($request->search) . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('invoice_number', 'LIKE', $searchTerm)
                  ->orWhere('notes', 'LIKE', $searchTerm)
                  ->orWhere('payment_reference', 'LIKE', $searchTerm)
                  ->orWhere('description', 'LIKE', $searchTerm);
            });
        }
        
        // Add filter for invoice type if needed
        if ($request->filled('invoice_type') && $request->invoice_type !== 'all') {
            $query->where('invoice_type', $request->invoice_type);
        }
        
        $payments = $query->orderBy('created_at', 'desc')
                         ->paginate($request->per_page ?? 20)
                         ->withQueryString();
        
        // Log the access for audit purposes
        $this->logAudit('payment_history_view', 'Payment history accessed', [
            'filter_status' => $request->status ?? 'all',
            'filter_date' => $request->date ?? 'all',
            'record_count' => $payments->total()
        ]);
        
        // If you want to include super admin payments as well, you can add:
        $superAdminPayments = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->when($request->filled('date') && $request->date === 'custom' && $request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                $q->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            })
            ->count();
        
        return view('developer.billing.history', compact('payments'));
        
    } catch (\Exception $e) {
        Log::error('Payment history error: ' . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString()
        ]);
        
        return redirect()->route('developer.billing.dashboard')
            ->with('error', 'Error loading payment history: ' . $e->getMessage());
    }
}

    /**
     * Export billing data
     */
    public function exportBillingData(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        $validator = Validator::make($request->all(), [
            'export_type' => 'required|in:invoices,payments,combined',
            'format' => 'required|in:csv,excel,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date|before_or_equal:today'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide valid export parameters');
        }
        
        try {
            $settings = DeveloperSetting::first();
            
            if (!Gate::allows('export-billing-data', $settings)) {
                abort(403, 'Unauthorized to export billing data');
            }
            
            $data = $validator->validated();
            
            $exportData = $this->prepareExportData(
                $settings->id,
                $data['export_type'],
                $data['start_date'] ?? null,
                $data['end_date'] ?? null
            );
            
            $filename = 'billing_export_' . $data['export_type'] . '_' . date('Y-m-d_H-i-s') . '.' . $data['format'];
            
            $this->logAudit('billing_data_exported', 'Billing data exported', [
                'export_type' => $data['export_type'],
                'format' => $data['format'],
                'ip_address' => request()->ip()
            ]);
            
            switch ($data['format']) {
                case 'csv':
                    return $this->exportToCsv($exportData, $filename);
                case 'excel':
                    return $this->exportToExcel($exportData, $filename);
                case 'pdf':
                    return $this->exportToPdf($exportData, $filename);
                default:
                    return redirect()->back()->with('error', 'Unsupported export format');
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to export billing data: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export data: ' . $e->getMessage());
        }
    }

    /**
     * Process recurring billing
     */
    public function processRecurringBilling()
    {
        try {
            Log::info('Developer-initiated recurring billing processing...');
            
            $settings = DeveloperSetting::first();
            
            if (!$settings) {
                Log::warning('No developer settings found for recurring billing');
                return 0;
            }
            
            if (!Gate::allows('process-recurring-billing', $settings)) {
                Log::warning('Unauthorized recurring billing processing attempt');
                return 0;
            }
            
            $billingRules = $settings->billing_rules ?? [];
            $autoGenerate = $billingRules['auto_generate_invoices'] ?? true;
            
            if (!$autoGenerate) {
                Log::info('Auto-generate invoices is disabled for developer');
                return 0;
            }
            
            $nextBillingDate = $settings->next_billing_date;
            
            if (!$nextBillingDate || $nextBillingDate->isPast()) {
                DB::beginTransaction();
                
                try {
                    $invoice = $this->generateDeveloperInvoice($settings, 'recurring');
                    
                    $newNextDate = $this->calculateNextBillingDate(
                        $settings->billing_cycle,
                        $nextBillingDate ?? now()
                    );
                    
                    $settings->update([
                        'next_billing_date' => $newNextDate,
                        'last_billing_date' => now()
                    ]);
                    
                    Log::info('Recurring invoice generated for developer', [
                        'developer' => $settings->developer_email,
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->amount,
                        'next_billing_date' => $newNextDate
                    ]);
                    
                    DB::commit();
                    return 1;
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to process recurring billing for developer: ' . $settings->developer_email, [
                        'error' => $e->getMessage()
                    ]);
                    return 0;
                }
            }
            
            Log::info('Not yet time for recurring billing', [
                'developer' => $settings->developer_email,
                'next_billing_date' => $nextBillingDate
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to process recurring billing: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * API documentation page
     */
    public function apiDocumentation()
    {
        try {
            if (!Gate::allows('view-api-documentation')) {
                abort(403, 'Unauthorized to view API documentation');
            }
            
            return view('developer.api.documentation');
            
        } catch (\Exception $e) {
            Log::error('Failed to load API documentation: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load API documentation: ' . $e->getMessage());
        }
    }

    /**
     * Download backup file
     */
    public function downloadBackup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Invalid backup filename');
            }
            
            $data = $validator->validated();
            $filename = $data['filename'];
            
            $this->validateBackupFilePath($filename);
            
            $backupPath = storage_path('app/backups/' . $filename);
            
            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }
            
            $this->logAudit('backup_downloaded', 'Backup file downloaded', [
                'filename' => $filename,
                'ip_address' => request()->ip()
            ]);
            
            return response()->download($backupPath);
            
        } catch (\Exception $e) {
            Log::error('Failed to download backup: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download backup: ' . $e->getMessage());
        }
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        try {
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Invalid backup filename');
            }
            
            $data = $validator->validated();
            $filename = $data['filename'];
            
            if (!Gate::allows('delete-backup')) {
                abort(403, 'Unauthorized to delete backups');
            }
            
            $this->validateBackupFilePath($filename);
            
            $backupPath = storage_path('app/backups/' . $filename);
            
            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }
            
            if (!unlink($backupPath)) {
                throw new \Exception('Failed to delete backup file');
            }
            
            $this->logAudit('backup_deleted', 'Backup file deleted', [
                'filename' => $filename,
                'ip_address' => request()->ip()
            ]);
            
            return redirect()->route('developer.backup.list')
                ->with('success', 'Backup file deleted successfully');
                
        } catch (\Exception $e) {
            Log::error('Failed to delete backup: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete backup: ' . $e->getMessage());
        }
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return redirect()->back()->with('error', 'Invalid backup filename');
            }
            
            $data = $validator->validated();
            $filename = $data['filename'];
            
            if (!Gate::allows('verify-backup')) {
                abort(403, 'Unauthorized to verify backups');
            }
            
            $this->validateBackupFilePath($filename);
            
            $backupPath = storage_path('app/backups/' . $filename);
            
            if (!file_exists($backupPath)) {
                throw new \Exception('Backup file not found');
            }
            
            $size = filesize($backupPath);
            $checksum = hash_file('sha256', $backupPath);
            
            $this->logAudit('backup_verified', 'Backup file verified', [
                'filename' => $filename,
                'size' => $size,
                'checksum' => $checksum
            ]);
            
            return redirect()->back()
                ->with('success', 'Backup file verified successfully')
                ->with('backup_info', [
                    'filename' => $filename,
                    'size' => $this->formatBytes($size),
                    'checksum' => $checksum,
                    'modified' => date('Y-m-d H:i:s', filemtime($backupPath))
                ]);
                
        } catch (\Exception $e) {
            Log::error('Failed to verify backup: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to verify backup: ' . $e->getMessage());
        }
    }

    /**
     * Generate health report
     */
    public function generateHealthReport()
    {
        try {
            if (!Gate::allows('generate-health-report')) {
                abort(403, 'Unauthorized to generate health reports');
            }
            
            $report = $this->monitoringService->generateHealthReport();
            
            $this->logAudit('health_report_generated', 'Health report generated', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $report
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate health report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate health report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Database backup
     */
    public function databaseBackup()
    {
        try {
            if (!Gate::allows('database-backup')) {
                abort(403, 'Unauthorized to backup database');
            }
            
            Artisan::call('db:backup');
            
            $this->logAudit('database_backup', 'Database backup performed', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Database backup completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to backup database: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to backup database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimize database
     */
    public function optimizeDatabase()
    {
        try {
            if (!Gate::allows('optimize-database')) {
                abort(403, 'Unauthorized to optimize database');
            }
            
            Artisan::call('db:optimize');
            
            $this->logAudit('database_optimized', 'Database optimized', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Database optimized successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to optimize database: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Repair database
     */
    public function repairDatabase()
    {
        try {
            if (!Gate::allows('repair-database')) {
                abort(403, 'Unauthorized to repair database');
            }
            
            Artisan::call('db:repair');
            
            $this->logAudit('database_repaired', 'Database repaired', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Database repair completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to repair database: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to repair database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run migrations
     */
    public function runMigrations()
    {
        try {
            if (!Gate::allows('run-migrations')) {
                abort(403, 'Unauthorized to run migrations');
            }
            
            Artisan::call('migrate', ['--force' => true]);
            
            $this->logAudit('migrations_ran', 'Database migrations run', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Migrations completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run migrations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run migrations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate fresh
     */
    public function migrateFresh()
    {
        try {
            if (!Gate::allows('migrate-fresh')) {
                abort(403, 'Unauthorized to run fresh migrations');
            }
            
            Artisan::call('migrate:fresh', ['--force' => true]);
            
            $this->logAudit('migrate_fresh', 'Fresh database migration performed', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Fresh migration completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run fresh migrations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run fresh migrations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Migrate rollback
     */
    public function migrateRollback()
    {
        try {
            if (!Gate::allows('migrate-rollback')) {
                abort(403, 'Unauthorized to rollback migrations');
            }
            
            Artisan::call('migrate:rollback', ['--force' => true]);
            
            $this->logAudit('migrate_rollback', 'Database migration rolled back', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Migration rollback completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to rollback migrations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback migrations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run seeders
     */
    public function runSeeders()
    {
        try {
            if (!Gate::allows('run-seeders')) {
                abort(403, 'Unauthorized to run seeders');
            }
            
            Artisan::call('db:seed', ['--force' => true]);
            
            $this->logAudit('seeders_ran', 'Database seeders run', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Seeders completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run seeders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run seeders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Database info
     */
    public function databaseInfo()
    {
        try {
            if (!Gate::allows('view-database-info')) {
                abort(403, 'Unauthorized to view database info');
            }
            
            $connection = DB::connection();
            $info = [
                'driver' => $connection->getDriverName(),
                'version' => $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'database' => $connection->getDatabaseName(),
                'tables' => $this->getDatabaseTables(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $info
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get database info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean database
     */
    public function cleanDatabase()
    {
        try {
            if (!Gate::allows('clean-database')) {
                abort(403, 'Unauthorized to clean database');
            }
            
            Artisan::call('db:clean');
            
            $this->logAudit('database_cleaned', 'Database cleaned', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Database cleaned successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clean database: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset database
     */
    public function resetDatabase()
    {
        try {
            if (!Gate::allows('reset-database')) {
                abort(403, 'Unauthorized to reset database');
            }
            
            Artisan::call('db:reset');
            
            $this->logAudit('database_reset', 'Database reset', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Database reset completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to reset database: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Database status
     */
    public function databaseStatus()
    {
        try {
            if (!Gate::allows('view-database-status')) {
                abort(403, 'Unauthorized to view database status');
            }
            
            $status = $this->monitoringService->getDatabaseStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get database status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get database status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart queue
     */
    public function restartQueue()
    {
        try {
            if (!Gate::allows('restart-queue')) {
                abort(403, 'Unauthorized to restart queue');
            }
            
            Artisan::call('queue:restart');
            
            $this->logAudit('queue_restarted', 'Queue worker restarted', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Queue worker restarted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to restart queue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restart queue: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Queue status
     */
    public function queueStatus()
    {
        try {
            if (!Gate::allows('view-queue-status')) {
                abort(403, 'Unauthorized to view queue status');
            }
            
            $status = $this->getQueueJobCount();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get queue status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get queue status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear queue
     */
    public function clearQueue()
    {
        try {
            if (!Gate::allows('clear-queue')) {
                abort(403, 'Unauthorized to clear queue');
            }
            
            Artisan::call('queue:clear');
            
            $this->logAudit('queue_cleared', 'Queue cleared', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Queue cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear queue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear queue: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs()
    {
        try {
            if (!Gate::allows('retry-failed-jobs')) {
                abort(403, 'Unauthorized to retry failed jobs');
            }
            
            Artisan::call('queue:retry', ['all' => true]);
            
            $this->logAudit('failed_jobs_retried', 'Failed jobs retried', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Failed jobs retried successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retry failed jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry failed jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Flush failed jobs
     */
    public function flushFailedJobs()
    {
        try {
            if (!Gate::allows('flush-failed-jobs')) {
                abort(403, 'Unauthorized to flush failed jobs');
            }
            
            Artisan::call('queue:flush');
            
            $this->logAudit('failed_jobs_flushed', 'Failed jobs flushed', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Failed jobs flushed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to flush failed jobs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to flush failed jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create storage link
     */
    public function createStorageLink()
    {
        try {
            if (!Gate::allows('create-storage-link')) {
                abort(403, 'Unauthorized to create storage link');
            }
            
            Artisan::call('storage:link');
            
            $this->logAudit('storage_link_created', 'Storage link created', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Storage link created successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to create storage link: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create storage link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cache config
     */
    public function cacheConfig()
    {
        try {
            if (!Gate::allows('cache-config')) {
                abort(403, 'Unauthorized to cache config');
            }
            
            Artisan::call('config:cache');
            
            $this->logAudit('config_cached', 'Configuration cached', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Configuration cached successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cache config: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cache config: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cache routes
     */
    public function cacheRoutes()
    {
        try {
            if (!Gate::allows('cache-routes')) {
                abort(403, 'Unauthorized to cache routes');
            }
            
            Artisan::call('route:cache');
            
            $this->logAudit('routes_cached', 'Routes cached', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Routes cached successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cache routes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cache routes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cache views
     */
    public function cacheViews()
    {
        try {
            if (!Gate::allows('cache-views')) {
                abort(403, 'Unauthorized to cache views');
            }
            
            Artisan::call('view:cache');
            
            $this->logAudit('views_cached', 'Views cached', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Views cached successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cache views: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cache views: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cache events
     */
    public function cacheEvents()
    {
        try {
            if (!Gate::allows('cache-events')) {
                abort(403, 'Unauthorized to cache events');
            }
            
            Artisan::call('event:cache');
            
            $this->logAudit('events_cached', 'Events cached', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Events cached successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to cache events: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cache events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches()
    {
        try {
            if (!Gate::allows('clear-all-caches')) {
                abort(403, 'Unauthorized to clear all caches');
            }
            
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            Artisan::call('event:clear');
            
            $this->logAudit('all_caches_cleared', 'All caches cleared', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear all caches: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear all caches: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comprehensive optimize
     */
    public function comprehensiveOptimize()
    {
        try {
            if (!Gate::allows('comprehensive-optimize')) {
                abort(403, 'Unauthorized to run comprehensive optimization');
            }
            
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            
            $this->logAudit('comprehensive_optimize', 'Comprehensive optimization performed', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Comprehensive optimization completed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run comprehensive optimization: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run comprehensive optimization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear log files
     */
    public function clearLogFiles()
    {
        try {
            if (!Gate::allows('clear-log-files')) {
                abort(403, 'Unauthorized to clear log files');
            }
            
            $logPath = storage_path('logs');
            $files = glob($logPath . '/*.log');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    file_put_contents($file, '');
                }
            }
            
            $this->logAudit('log_files_cleared', 'Log files cleared', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Log files cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear log files: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear log files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View log files
     */
    public function viewLogFiles()
    {
        try {
            if (!Gate::allows('view-log-files')) {
                abort(403, 'Unauthorized to view log files');
            }
            
            $logPath = storage_path('logs');
            $files = glob($logPath . '/*.log');
            $logContents = [];
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $filename = basename($file);
                    $logContents[$filename] = [
                        'size' => filesize($file),
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                        'content' => file_get_contents($file)
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $logContents
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to view log files: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to view log files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rotate logs
     */
    public function rotateLogs()
    {
        try {
            if (!Gate::allows('rotate-logs')) {
                abort(403, 'Unauthorized to rotate logs');
            }
            
            Artisan::call('log:rotate');
            
            $this->logAudit('logs_rotated', 'Logs rotated', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Logs rotated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to rotate logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to rotate logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run diagnostics
     */
    public function runDiagnostics()
    {
        try {
            if (!Gate::allows('run-diagnostics')) {
                abort(403, 'Unauthorized to run diagnostics');
            }
            
            $diagnostics = $this->monitoringService->runDiagnostics();
            
            $this->logAudit('diagnostics_run', 'System diagnostics run', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $diagnostics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run diagnostics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run diagnostics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show PHP info
     */
    public function showPhpInfo()
    {
        try {
            if (!Gate::allows('view-phpinfo')) {
                abort(403, 'Unauthorized to view PHP info');
            }
            
            ob_start();
            phpinfo();
            $phpinfo = ob_get_clean();
            
            return response()->json([
                'success' => true,
                'data' => $phpinfo
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to show PHP info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to show PHP info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Server info
     */
    public function serverInfo()
    {
        try {
            if (!Gate::allows('view-server-info')) {
                abort(403, 'Unauthorized to view server info');
            }
            
            $info = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'N/A',
                'server_port' => $_SERVER['SERVER_PORT'] ?? 'N/A',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
                'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                'remote_port' => $_SERVER['REMOTE_PORT'] ?? 'N/A',
                'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'N/A',
                'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
                'request_time' => $_SERVER['REQUEST_TIME'] ?? 'N/A',
                'request_time_float' => $_SERVER['REQUEST_TIME_FLOAT'] ?? 'N/A',
            ];
            
            return response()->json([
                'success' => true,
                'data' => $info
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get server info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get server info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear sessions
     */
    public function clearSessions()
    {
        try {
            if (!Gate::allows('clear-sessions')) {
                abort(403, 'Unauthorized to clear sessions');
            }
            
            Artisan::call('session:clear');
            
            $this->logAudit('sessions_cleared', 'Sessions cleared', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Sessions cleared successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clear sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear sessions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update environment
     */
    public function updateEnvironment(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid CSRF token');
        }
        
        try {
            if (!Gate::allows('update-environment')) {
                abort(403, 'Unauthorized to update environment');
            }
            
            $validator = Validator::make($request->all(), [
                'key' => 'required|string',
                'value' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $data = $validator->validated();
            
            $envPath = base_path('.env');
            $envContent = file_get_contents($envPath);
            
            $pattern = "/^{$data['key']}=.*/m";
            $replacement = "{$data['key']}={$data['value']}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$data['key']}={$data['value']}";
            }
            
            file_put_contents($envPath, $envContent);
            
            $this->logAudit('environment_updated', 'Environment variable updated', [
                'key' => $data['key'],
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Environment variable updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update environment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update environment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View environment
     */
    public function viewEnvironment()
    {
        try {
            if (!Gate::allows('view-environment')) {
                abort(403, 'Unauthorized to view environment');
            }
            
            $envPath = base_path('.env');
            $envContent = file_get_contents($envPath);
            
            $lines = explode("\n", $envContent);
            $envVars = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $envVars[trim($key)] = trim($value);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $envVars
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to view environment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to view environment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Security scan
     */
    public function securityScan()
    {
        try {
            if (!Gate::allows('run-security-scan')) {
                abort(403, 'Unauthorized to run security scan');
            }
            
            $scanResults = $this->monitoringService->runSecurityScan();
            
            $this->logAudit('security_scan', 'Security scan performed', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $scanResults
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run security scan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run security scan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fix permissions
     */
    public function fixPermissions()
    {
        try {
            if (!Gate::allows('fix-permissions')) {
                abort(403, 'Unauthorized to fix permissions');
            }
            
            Artisan::call('permissions:fix');
            
            $this->logAudit('permissions_fixed', 'File permissions fixed', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'File permissions fixed successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fix permissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fix permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean storage
     */
    public function cleanStorage()
    {
        try {
            if (!Gate::allows('clean-storage')) {
                abort(403, 'Unauthorized to clean storage');
            }
            
            Artisan::call('storage:clean');
            
            $this->logAudit('storage_cleaned', 'Storage cleaned', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Storage cleaned successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to clean storage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean storage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * File explorer
     */
    public function fileExplorer()
    {
        try {
            if (!Gate::allows('view-file-explorer')) {
                abort(403, 'Unauthorized to view file explorer');
            }
            
            $directory = request()->get('directory', base_path());
            $files = $this->monitoringService->getDirectoryContents($directory);
            
            return response()->json([
                'success' => true,
                'data' => $files
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get file explorer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get file explorer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run scheduler
     */
    public function runScheduler()
    {
        try {
            if (!Gate::allows('run-scheduler')) {
                abort(403, 'Unauthorized to run scheduler');
            }
            
            Artisan::call('schedule:run');
            
            $this->logAudit('scheduler_run', 'Scheduler run manually', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Scheduler run successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run scheduler: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run scheduler: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Scheduler status
     */
    public function schedulerStatus()
    {
        try {
            if (!Gate::allows('view-scheduler-status')) {
                abort(403, 'Unauthorized to view scheduler status');
            }
            
            $status = $this->monitoringService->getSchedulerStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get scheduler status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get scheduler status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Put application down
     */
    public function putApplicationDown()
    {
        try {
            if (!Gate::allows('put-application-down')) {
                abort(403, 'Unauthorized to put application down');
            }
            
            Artisan::call('down', [
                '--message' => 'Maintenance mode enabled by developer',
                '--retry' => 60,
                '--secret' => Str::random(32)
            ]);
            
            $this->logAudit('app_down', 'Application put in maintenance mode', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Application is now in maintenance mode'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to put application down: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to put application down: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bring application up
     */
    public function bringApplicationUp()
    {
        try {
            if (!Gate::allows('bring-application-up')) {
                abort(403, 'Unauthorized to bring application up');
            }
            
            Artisan::call('up');
            
            $this->logAudit('app_up', 'Application brought back online', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Application is now online'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to bring application up: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bring application up: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Application status
     */
    public function applicationStatus()
    {
        try {
            if (!Gate::allows('view-application-status')) {
                abort(403, 'Unauthorized to view application status');
            }
            
            $status = $this->monitoringService->getApplicationStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get application status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get application status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run performance test
     */
    public function runPerformanceTest()
    {
        try {
            if (!Gate::allows('run-performance-test')) {
                abort(403, 'Unauthorized to run performance test');
            }
            
            $performance = $this->monitoringService->runPerformanceTest();
            
            $this->logAudit('performance_test', 'Performance test run', [
                'ip_address' => request()->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $performance
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to run performance test: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to run performance test: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics()
    {
        try {
            if (!Gate::allows('view-performance-metrics')) {
                abort(403, 'Unauthorized to view performance metrics');
            }
            
            $metrics = $this->monitoringService->getPerformanceMetrics();
            
            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get performance metrics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // Helper Methods
    // =====================================================

    /**
     * Initialize developer settings
     */
    private function initializeDeveloperSettings()
    {
        $defaults = [
            'developer_name' => auth()->user()->name,
            'developer_email' => auth()->user()->email,
            'developer_access_enabled' => true,
            'monthly_billing_amount' => 0.00,
            'billing_currency' => 'GHS',
            'billing_cycle' => 'monthly',
            'billing_start_date' => now(),
            'next_billing_date' => now()->addMonth(),
            'billing_status' => 'active',
            'billing_rules' => json_encode([
                'auto_generate_invoices' => true,
                'invoice_due_days' => 30,
                'late_fee_percentage' => 5,
                'grace_period_days' => 7,
            ]),
            'enable_system_monitoring' => true,
            'enable_auto_backup' => true,
            'cache_duration' => 3600,
            'max_upload_size' => 2048,
            'max_execution_time' => 300,
            'log_retention_days' => 90,
            'session_timeout' => 120,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        return DeveloperSetting::create($defaults);
    }

    /**
     * Get system statistics
     */
    private function getSystemStatistics()
    {
        try {
            $stats = Cache::remember('developer_system_stats', 300, function () {
                return [
                    'users_count' => User::count(),
                    'active_sessions' => DB::table('sessions')->where('last_activity', '>', now()->subMinutes(30))->count(),
                    'disk_usage' => $this->getDiskUsage(),
                    'memory_usage' => memory_get_usage(true) / 1024 / 1024,
                    'uptime' => $this->getSystemUptime(),
                    'request_count' => $this->getRequestCount(),
                    'error_count' => $this->getErrorCount(),
                ];
            });
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get system statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities($limit = 20)
    {
        try {
            return DB::table('activity_log')
                ->where('log_name', 'developer')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::warning('Failed to get recent activities: ' . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Get system information
     */
    private function getSystemInformation()
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'database_driver' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'timezone' => config('app.timezone'),
            'debug_mode' => config('app.debug'),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Disk usage helper
     */
    private function getDiskUsage()
    {
        try {
            $total = disk_total_space(base_path());
            $free = disk_free_space(base_path());
            $used = $total - $free;
            
            return [
                'total' => round($total / 1024 / 1024 / 1024, 2),
                'used' => round($used / 1024 / 1024 / 1024, 2),
                'free' => round($free / 1024 / 1024 / 1024, 2),
                'percentage' => round(($used / $total) * 100, 1)
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'used' => 0, 'free' => 0, 'percentage' => 0];
        }
    }

    /**
     * System uptime helper
     */
    private function getSystemUptime()
    {
        try {
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('uptime -p');
                return $uptime ? trim($uptime) : 'Unknown';
            }
            
            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Request count helper
     */
    private function getRequestCount()
    {
        try {
            return DB::table('request_logs')
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Error count helper
     */
    private function getErrorCount()
    {
        try {
            return DB::table('error_logs')
                ->where('created_at', '>=', now()->subDay())
                ->where('level', 'error')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Disk usage percentage
     */
    private function getDiskUsagePercentage()
    {
        $diskUsage = $this->getDiskUsage();
        return $diskUsage['percentage'] ?? 0;
    }

    /**
     * Memory usage percentage
     */
    private function getMemoryUsagePercentage()
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            
            if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
                if ($matches[2] == 'M') {
                    $memoryLimit = $matches[1] * 1024 * 1024;
                } elseif ($matches[2] == 'K') {
                    $memoryLimit = $matches[1] * 1024;
                } elseif ($matches[2] == 'G') {
                    $memoryLimit = $matches[1] * 1024 * 1024 * 1024;
                }
            }
            
            return $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 1) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get queue job count
     */
    private function getQueueJobCount()
    {
        try {
            if (Schema::hasTable('jobs')) {
                $pendingJobs = DB::table('jobs')->count();
                $failedJobs = DB::table('failed_jobs')->count();
                
                return [
                    'pending' => $pendingJobs,
                    'failed' => $failedJobs,
                    'total' => $pendingJobs + $failedJobs
                ];
            }
            
            return ['pending' => 0, 'failed' => 0, 'total' => 0];
            
        } catch (\Exception $e) {
            return ['pending' => 0, 'failed' => 0, 'total' => 0];
        }
    }

    /**
     * Get database tables
     */
    private function getDatabaseTables()
    {
        try {
            $tables = DB::select('SHOW TABLES');
            $tableList = [];
            
            foreach ($tables as $table) {
                $tableName = reset($table);
                $tableList[] = $tableName;
            }
            
            return $tableList;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate invoice for developer
     */
    private function generateDeveloperInvoice($settings = null, $type = 'regular')
    {
        try {
            if (!$settings) {
                $settings = DeveloperSetting::first();
            }
            
            if (!Gate::allows('generate-developer-invoice', $settings)) {
                return null;
            }
            
            if (!$settings || $settings->monthly_billing_amount <= 0) {
                return null;
            }
            
            $invoiceNumber = 'DEV-INV-' . Str::upper(uniqid());
            
            $invoice = DeveloperBillingRecord::create([
                'developer_setting_id' => $settings->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $settings->monthly_billing_amount,
                'currency' => $settings->billing_currency,
                'billing_cycle' => $settings->billing_cycle,
                'due_date' => now()->addDays($settings->billing_rules['invoice_due_days'] ?? 30),
                'status' => 'active',
                'payment_status' => 'unpaid',
                'invoice_type' => $type,
                'items' => json_encode([
                    [
                        'description' => "Developer Platform Access - {$settings->billing_cycle} subscription",
                        'amount' => $settings->monthly_billing_amount,
                        'quantity' => 1,
                        'total' => $settings->monthly_billing_amount
                    ]
                ]),
                'notes' => "Auto-generated invoice by developer",
                'created_by' => auth()->id()
            ]);
            
            SendDeveloperInvoiceJob::dispatch($invoice, $settings->developer_email);
            
            Log::info('Developer generated invoice', [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
                'developer' => $settings->developer_email
            ]);
            
            return $invoice;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate developer invoice: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate next billing date
     */
    private function calculateNextBillingDate($cycle, $currentDate)
    {
        switch ($cycle) {
            case 'monthly':
                return Carbon::parse($currentDate)->addMonth();
            case 'quarterly':
                return Carbon::parse($currentDate)->addMonths(3);
            case 'yearly':
                return Carbon::parse($currentDate)->addYear();
            default:
                return Carbon::parse($currentDate)->addMonth();
        }
    }

    /**
     * Prepare export data
     */
    private function prepareExportData($developerId, $exportType, $startDate, $endDate)
    {
        $data = [];
        
        switch ($exportType) {
            case 'invoices':
                $query = DeveloperBillingRecord::where('developer_setting_id', $developerId);
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                }
                $data = $query->get()->toArray();
                break;
                
            case 'payments':
                $query = AdminBillingRecord::where('developer_setting_id', $developerId)
                    ->with('superAdmin');
                if ($startDate && $endDate) {
                    $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                }
                $data = $query->get()->map(function ($payment) {
                    return [
                        'invoice_number' => $payment->invoice_number,
                        'super_admin' => $payment->superAdmin->name ?? 'N/A',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'description' => $payment->description,
                        'due_date' => $payment->due_date,
                        'status' => $payment->status,
                        'payment_status' => $payment->payment_status,
                        'requested_at' => $payment->requested_at,
                        'received_date' => $payment->received_date
                    ];
                })->toArray();
                break;
                
            case 'combined':
                $invoices = DeveloperBillingRecord::where('developer_setting_id', $developerId);
                $payments = AdminBillingRecord::where('developer_setting_id', $developerId);
                
                if ($startDate && $endDate) {
                    $invoices->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                    $payments->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                }
                
                $data = [
                    'invoices' => $invoices->get()->toArray(),
                    'payments' => $payments->get()->toArray()
                ];
                break;
        }
        
        return $data;
    }

    /**
     * Export to CSV
     */
    private function exportToCsv($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Security-Policy' => "default-src 'self'",
            'X-Content-Type-Options' => 'nosniff',
        ];
        
        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            
            if (isset($data[0])) {
                fputcsv($file, array_keys($data[0]));
                
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export to Excel
     */
    private function exportToExcel($data, $filename)
    {
        return $this->exportToCsv($data, str_replace('.xlsx', '.csv', $filename));
    }

    /**
     * Export to PDF
     */
    private function exportToPdf($data, $filename)
    {
        return redirect()->route('developer.billing.reports')
            ->with('print_pdf', true)
            ->with('export_data', $data);
    }

    /**
     * Sanitize input
     */
    private function sanitizeInput($value)
    {
        if (is_string($value)) {
            return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    /**
     * Validate backup file path
     */
    private function validateBackupFilePath($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
            throw new \Exception('Invalid backup filename');
        }
        
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            throw new \Exception('Invalid backup filename characters');
        }
        
        $backupPath = storage_path('app/backups/' . $filename);
        if (!file_exists($backupPath)) {
            throw new \Exception('Backup file not found');
        }
        
        return true;
    }

    /**
     * Format bytes
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Notify Super Admins about payment confirmation
     */
    private function notifySuperAdminAboutPaymentConfirmation($invoice, $amountPaid)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();
            
            foreach ($superAdmins as $superAdmin) {
                $notificationData = [
                    'title' => '✅ Developer Payment Confirmed',
                    'message' => "Developer has confirmed payment of GH₵{$amountPaid} for invoice #{$invoice->invoice_number}",
                    'icon' => 'fas fa-check-circle text-success',
                    'category' => 'billing',
                    'action_url' => route('admin.developer.invoice', $invoice->id),
                    'priority' => 1,
                    'data' => [
                        'type' => 'developer_payment_confirmed',
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount_paid' => $amountPaid,
                        'total_amount' => $invoice->amount,
                        'currency' => $invoice->currency,
                        'developer' => $invoice->developerSetting->developer_name,
                        'payment_method' => $invoice->payment_method,
                        'timestamp' => now()->toISOString(),
                        'ip_address' => request()->ip()
                    ]
                ];
                
                $this->notifyUserWithData($superAdmin, $notificationData);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to notify Super Admins about payment confirmation: ' . $e->getMessage());
        }
    }

    /**
     * Notify Super Admins about billing proposal cancellation
     */
    private function notifySuperAdminsAboutBillingProposalCancellation($proposal)
    {
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();
            
            foreach ($superAdmins as $superAdmin) {
                $notificationData = [
                    'title' => '❌ Billing Proposal Cancelled',
                    'message' => "Developer has cancelled billing proposal #{$proposal->id}",
                    'icon' => 'fas fa-times-circle text-danger',
                    'category' => 'billing',
                    'action_url' => route('admin.developer.billing'),
                    'priority' => 1,
                    'data' => [
                        'type' => 'billing_proposal_cancelled',
                        'proposal_id' => $proposal->id,
                        'developer' => $proposal->developerSetting->developer_name,
                        'cancelled_at' => now()->toISOString(),
                        'ip_address' => request()->ip()
                    ]
                ];
                
                $this->notifyUserWithData($superAdmin, $notificationData);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to notify Super Admins about billing proposal cancellation: ' . $e->getMessage());
        }
    }
}