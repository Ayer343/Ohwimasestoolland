<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\Billing\CreateAgreementRequest;
use App\Http\Requests\Developer\Billing\UpdateAgreementRequest;
use App\Http\Requests\Developer\Billing\TerminateAgreementRequest;
use App\Http\Requests\Developer\Billing\ConfirmPaymentRequest;
use App\Http\Requests\Developer\Billing\UpdateBillingSettingsRequest;
use App\Http\Requests\Developer\Billing\GenerateCustomInvoiceRequest;
use App\Http\Requests\Developer\Billing\SendAgreementRequest;
use App\Http\Requests\Developer\Billing\RecordPaymentRequest;
use App\Http\Requests\Developer\Billing\SendReminderRequest;
use App\Models\DeveloperSetting;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Models\BillingInvoice;
use App\Models\SuperAdminPaymentRecord;
use App\Models\SuperAdminPaymentRequest;
use App\Models\AgreementSignature;
use App\Models\InvoiceReminder;
use App\Services\DeveloperBillingService;
use App\Jobs\SendSuperAdminAgreementJob;
use App\Jobs\GenerateAgreementPdfJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Events\Billing\AgreementCreated;
use App\Events\Billing\AgreementUpdated;
use App\Events\Billing\AgreementTerminated;
use App\Events\Billing\PaymentConfirmed;
use App\Events\Billing\SignatureRevoked;
use App\Traits\AuditLogger;
use App\Traits\BillingHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;

/**
 * Developer Billing Controller
 *
 * Handles all billing operations for developers including agreement management,
 * payment processing, invoice generation, and reporting.
 *
 * @package App\Http\Controllers\Developer
 * @version 4.3 - Aligned with real billing_invoices schema + pagination-safe stats
 */
class DeveloperBillingController extends Controller
{
    use AuditLogger, BillingHelperTrait;

    /**
     * @var DeveloperBillingService
     */
    protected $billingService;

    /**
     * Cache duration for dashboard data (minutes)
     */
    protected const CACHE_DURATION = 5;

    /**
     * Constructor
     */
    public function __construct(DeveloperBillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /* ============================================================
     | DASHBOARD
     * ============================================================ */

    public function dashboard()
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $cacheKey = "developer_billing_dashboard_{$user->id}";

            $dashboardData = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_DURATION), function () use ($user) {
                $developerSettings = $this->getOrInitializeDeveloperSettings();

                // Initialize empty collections
                $activeAgreements = collect();
                $pendingAgreements = collect();
                $completedAgreements = collect();
                $pendingPayments = collect();
                $billingProposals = collect();
                $superAdminRequests = collect();
                $recentInvoices = collect();
                $awaitingSignature = collect();
                $recentlySigned = collect();

                if (Schema::hasTable('admin_billing_records')) {
                    $activeAgreements = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('status', 'active')
                        ->with(['superAdmin', 'payments' => function ($query) {
                            $query->where('status', 'confirmed')
                                  ->orderBy('payment_date', 'desc');
                        }])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $pendingAgreements = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('status', 'pending')
                        ->with(['superAdmin', 'signatures'])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    $completedAgreements = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('status', 'completed')
                        ->with('superAdmin')
                        ->orderBy('updated_at', 'desc')
                        ->limit(10)
                        ->get();

                    $awaitingSignature = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('status', 'pending')
                        ->whereNotNull('agreement_pdf_path')
                        ->whereNull('signed_agreement_pdf_path')
                        ->with(['superAdmin', 'signatures'])
                        ->orderBy('signing_invitation_sent_at', 'desc')
                        ->get()
                        ->filter(function ($agreement) {
                            $developerSigned = $agreement->signatures
                                ? $agreement->signatures->where('signature_type', 'developer')->isNotEmpty()
                                : false;
                            return !$developerSigned;
                        });

                    $recentlySigned = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('status', 'active')
                        ->whereNotNull('signed_agreement_pdf_path')
                        ->whereNotNull('signing_completed_at')
                        ->with('superAdmin')
                        ->orderBy('signing_completed_at', 'desc')
                        ->limit(5)
                        ->get();
                }

                if (Schema::hasTable('agreement_payments') && Schema::hasTable('admin_billing_records')) {
                    $pendingPayments = DB::table('agreement_payments')
                        ->join('admin_billing_records', 'agreement_payments.admin_billing_record_id', '=', 'admin_billing_records.id')
                        ->join('users', 'admin_billing_records.super_admin_id', '=', 'users.id')
                        ->where('admin_billing_records.developer_setting_id', $developerSettings->id)
                        ->where('agreement_payments.status', 'pending_confirmation')
                        ->select('agreement_payments.*', 'users.name as super_admin_name', 'admin_billing_records.agreement_number')
                        ->orderBy('agreement_payments.created_at', 'desc')
                        ->get();
                }

                $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                    ->where('status', User::STATUS_ACTIVE)
                    ->get(['id', 'name', 'email', 'phone']);

                $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                    ->where('is_primary_for_billing', true)
                    ->with('superAdmin')
                    ->first();

                $primarySuperAdmin = $primaryAgreement ? $primaryAgreement->superAdmin : null;
                $primaryBillingContact = $primaryAgreement ? [
                    'name'  => $primaryAgreement->billing_contact_name ?? $primarySuperAdmin->name ?? null,
                    'email' => $primaryAgreement->billing_contact_email ?? $primarySuperAdmin->email ?? null,
                    'phone' => $primaryAgreement->billing_contact_phone ?? $primarySuperAdmin->phone ?? null,
                ] : null;

                if (Schema::hasTable('billing_proposals')) {
                    $billingProposals = \App\Models\BillingProposal::where('developer_setting_id', $developerSettings->id)
                        ->whereIn('status', ['pending', 'under_review'])
                        ->orderBy('created_at', 'desc')
                        ->get();
                }

                if (Schema::hasTable('super_admin_payment_requests')) {
                    $superAdminRequests = SuperAdminPaymentRequest::where('developer_setting_id', $developerSettings->id)
                        ->with('superAdmin')
                        ->orderBy('due_date', 'asc')
                        ->get();
                }

                if (Schema::hasTable('billing_invoices')) {
                    try {
                        $invoiceQuery = BillingInvoice::where('developer_setting_id', $developerSettings->id);

                        if ($primaryAgreement) {
                            if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
                                $invoiceQuery->where('agreement_id', $primaryAgreement->id);
                            } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                                $invoiceQuery->where('admin_billing_record_id', $primaryAgreement->id);
                            }
                        }

                        $recentInvoices = $invoiceQuery->orderBy('created_at', 'desc')
                            ->limit(10)
                            ->get();
                    } catch (\Exception $e) {
                        Log::warning('Could not load recent invoices: ' . $e->getMessage());
                        $recentInvoices = collect();
                    }
                }

                $stats = [
                    'total_active_agreements'    => $activeAgreements->count(),
                    'total_pending_agreements'   => $pendingAgreements->count(),
                    'total_completed_agreements' => $completedAgreements->count(),
                    'total_amount_agreed'        => $activeAgreements->sum('amount'),
                    'total_amount_received'      => $activeAgreements->sum('amount_received'),
                    'total_pending_payments'     => $pendingPayments->count(),
                    'pending_payment_amount'     => $pendingPayments->sum('amount_paid'),
                    'pending_proposals'          => $billingProposals->count(),
                    'pending_sa_requests'        => $superAdminRequests->whereIn('status', ['pending', 'overdue'])->count(),
                    'awaiting_signature'         => $awaitingSignature->count(),
                    'recently_signed'            => $recentlySigned->count(),
                    'total_agreements'           => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->count(),
                    'has_primary_super_admin'    => $primarySuperAdmin ? true : false,
                    'primary_super_admin_name'   => $primarySuperAdmin ? $primarySuperAdmin->name : 'Not assigned',
                    'primary_billing_contact'    => $primaryBillingContact,
                ];

                $paymentMethods = $this->getPaymentMethodsConfiguration();

                return [
                    'activeAgreements'      => $activeAgreements,
                    'pendingAgreements'     => $pendingAgreements,
                    'completedAgreements'   => $completedAgreements,
                    'awaitingSignature'     => $awaitingSignature,
                    'recentlySigned'        => $recentlySigned,
                    'pendingPayments'       => $pendingPayments,
                    'superAdmins'           => $superAdmins,
                    'primarySuperAdmin'     => $primarySuperAdmin,
                    'primaryBillingContact' => $primaryBillingContact,
                    'billingProposals'      => $billingProposals,
                    'superAdminRequests'    => $superAdminRequests,
                    'recentInvoices'        => $recentInvoices,
                    'stats'                 => $stats,
                    'paymentMethods'        => $paymentMethods,
                    'developerSettings'     => $developerSettings,
                ];
            });

            $this->logAudit('developer_billing_dashboard', 'Developer billing dashboard accessed');

            return view('developer.billing.dashboard', $dashboardData);

        } catch (\Exception $e) {
            Log::error('Developer billing dashboard error', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return $this->renderErrorDashboard($e);
        }
    }

    /* ============================================================
     | AGREEMENTS
     * ============================================================ */

    public function agreementsList(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $query = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->with('superAdmin');

            $this->applyAgreementFilters($query, $request);

            $agreements = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20))
                ->withQueryString();

            $trashedCount = AdminBillingRecord::onlyTrashed()
                ->where('developer_setting_id', $developerSettings->id)
                ->count();

            $trashedByStatus = [
                'pending'    => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->where('status', 'pending')->count(),
                'terminated' => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->where('status', 'terminated')->count(),
                'cancelled'  => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->where('status', 'cancelled')->count(),
            ];

            $stats = [
                'total_agreements'       => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->count(),
                'active_agreements'      => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->where('status', 'active')->count(),
                'pending_agreements'     => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->where('status', 'pending')->count(),
                'terminated_agreements'  => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->where('status', 'terminated')->count(),
                'completed_agreements'   => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->where('status', 'completed')->count(),
                'total_amount_agreed'    => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->whereIn('status', ['active', 'pending'])->sum('amount'),
                'total_amount_received'  => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->whereIn('status', ['active', 'pending'])->sum('amount_received'),
                'has_primary'            => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->where('is_primary_for_billing', true)->exists(),
                'trashed_count'          => $trashedCount,
                'trashed_by_status'      => $trashedByStatus,
            ];

            $this->logAudit('agreements_list_viewed', 'Developer viewed agreements list', [
                'total_agreements'      => $agreements->total(),
                'filter_status'         => $request->get('status', 'all'),
                'filter_payment_status' => $request->get('payment_status', 'all'),
                'trashed_count'         => $trashedCount
            ]);

            return view('developer.billing.agreements-list', compact('agreements', 'stats', 'trashedCount', 'trashedByStatus'));

        } catch (\Exception $e) {
            Log::error('Failed to view agreements', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return view('developer.billing.agreements-list', [
                'agreements' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'stats' => [
                    'total_agreements'      => 0,
                    'active_agreements'     => 0,
                    'pending_agreements'    => 0,
                    'terminated_agreements' => 0,
                    'completed_agreements'  => 0,
                    'total_amount_agreed'   => 0,
                    'total_amount_received' => 0,
                    'has_primary'           => false,
                    'trashed_count'         => 0,
                    'trashed_by_status'     => ['pending' => 0, 'terminated' => 0, 'cancelled' => 0],
                ],
                'trashedCount'    => 0,
                'trashedByStatus' => ['pending' => 0, 'terminated' => 0, 'cancelled' => 0]
            ])->with('error', 'Error loading agreements: ' . $e->getMessage());
        }
    }

    public function viewAgreement($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with(['superAdmin', 'payments', 'developerSetting', 'signatures.user'])
                ->findOrFail($agreementId);

            $this->authorizeAgreementAccess($agreement);

            $payments = collect();
            if (Schema::hasTable('agreement_payments')) {
                $payments = DB::table('agreement_payments as p')
                    ->leftJoin('users as recorded_by', 'p.recorded_by', '=', 'recorded_by.id')
                    ->leftJoin('users as confirmed_by', 'p.confirmed_by', '=', 'confirmed_by.id')
                    ->where('p.admin_billing_record_id', $agreementId)
                    ->select('p.*',
                        'recorded_by.name as recorded_by_name',
                        'confirmed_by.name as confirmed_by_name'
                    )
                    ->orderBy('p.payment_date', 'desc')
                    ->get();
            }

            $superAdminPayments = collect();
            if (Schema::hasTable('super_admin_payment_records')) {
                $superAdminPayments = SuperAdminPaymentRecord::where('agreement_id', $agreementId)
                    ->with('superAdmin')
                    ->orderBy('payment_date', 'desc')
                    ->get();
            }

            $signatures = collect();
            $developerSignatureExists = false;
            $superAdminSignatureExists = false;

            if ($agreement->signatures && $agreement->signatures->count() > 0) {
                foreach ($agreement->signatures as $signature) {
                    $signatures->push([
                        'id'               => $signature->id,
                        'user_name'        => $signature->user->name ?? 'Unknown',
                        'user_type'        => $signature->signature_type === 'developer' ? 'Developer' : 'Super Admin',
                        'signature_name'   => $signature->signature_name,
                        'signature_date'   => $signature->signature_date ? $signature->signature_date->format('F j, Y') : 'N/A',
                        'signature_path'   => $signature->signature_path,
                        'ip_address'       => $signature->ip_address,
                        'user_agent'       => $signature->user_agent,
                        'signature_format' => $signature->signature_format ?? 'typed',
                        'digital_hash'     => $signature->digital_hash,
                    ]);

                    if ($signature->signature_type === 'developer') {
                        $developerSignatureExists = true;
                    }
                    if ($signature->signature_type === 'super_admin') {
                        $superAdminSignatureExists = true;
                    }
                }
            }

            $bothPartiesSigned = $developerSignatureExists && $superAdminSignatureExists;
            $allSigned = $bothPartiesSigned;

            $statusMessage = '';
            if ($bothPartiesSigned && $agreement->status !== 'active') {
                $statusMessage = 'Both parties have signed but the agreement status is pending. Please contact support to activate this agreement.';
            } elseif ($agreement->status == 'active') {
                $statusMessage = 'This agreement is active and binding. Both parties have signed.';
            } elseif ($agreement->status == 'pending') {
                if ($developerSignatureExists && !$superAdminSignatureExists) {
                    $statusMessage = 'You have signed. Waiting for Super Admin signature to activate.';
                } elseif (!$developerSignatureExists && $superAdminSignatureExists) {
                    $statusMessage = 'Super Admin has signed. Waiting for your signature to activate.';
                } else {
                    $statusMessage = 'This agreement is pending signatures from both parties.';
                }
            } elseif ($agreement->status == 'completed') {
                $statusMessage = 'This agreement has been completed.';
            } elseif ($agreement->status == 'terminated') {
                $statusMessage = 'This agreement has been terminated.';
            } else {
                $statusMessage = 'This agreement is ' . $agreement->status;
            }

            $signatureStatus = [
                'developer_signed'      => $developerSignatureExists,
                'super_admin_signed'    => $superAdminSignatureExists,
                'all_signed'            => $allSigned,
                'status_message'        => $statusMessage,
                'show_pending_warning'  => ($bothPartiesSigned && $agreement->status !== 'active'),
                'effective_status'      => ($bothPartiesSigned && $agreement->status !== 'active') ? 'pending' : $agreement->status,
            ];

            $paymentDetails = $this->getAgreementPaymentSummary($agreement);
            $paymentMethods = $this->getPaymentMethodsConfiguration();
            $agreement->payment_method_details = $this->getPaymentMethodDetails($agreement);

            $isPrimaryForBilling = $agreement->is_primary_for_billing ?? false;

            $this->logAudit('agreement_viewed', 'Billing agreement viewed', [
                'agreement_id'      => $agreementId,
                'agreement_number'  => $agreement->agreement_number,
                'is_primary'        => $isPrimaryForBilling,
                'developer_signed'  => $developerSignatureExists,
                'super_admin_signed'=> $superAdminSignatureExists,
            ]);

            return view('developer.billing.agreement-details', compact(
                'agreement',
                'payments',
                'superAdminPayments',
                'signatures',
                'signatureStatus',
                'paymentDetails',
                'paymentMethods',
                'isPrimaryForBilling'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to view agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error loading agreement details: ' . $e->getMessage());
        }
    }

    public function createAgreement(CreateAgreementRequest $request)
    {
        $key = 'create_agreement:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()
                ->with('error', "Too many attempts. Try again in {$seconds} seconds.");
        }

        RateLimiter::hit($key, 300);

        DB::beginTransaction();

        try {
            $user = auth()->user();
            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $validatedData = $request->validated();

            if (empty($validatedData['primary_super_admin_id'])) {
                throw new \Exception('Please select a Primary Super Admin for billing invoices.');
            }

            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            if ($superAdmins->isEmpty()) {
                throw new \Exception('No active super admins found in the system.');
            }

            $primarySuperAdmin = $superAdmins->firstWhere('id', $validatedData['primary_super_admin_id']);
            if (!$primarySuperAdmin) {
                throw new \Exception('Selected Primary Super Admin is not active or does not exist.');
            }

            AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->update(['is_primary_for_billing' => false]);

            $createdAgreements = [];
            $primaryAgreementId = null;

            foreach ($superAdmins as $superAdmin) {
                $dueDate = $this->calculateDueDateBasedOnFrequency($validatedData['start_date'], $validatedData['billing_frequency']);
                $agreementNumber = $this->generateAgreementNumber();
                $paymentDetails = $this->preparePaymentDetails($validatedData);

                $isPrimaryForBilling = ($superAdmin->id == $validatedData['primary_super_admin_id']);

                $agreement = AdminBillingRecord::create([
                    'developer_setting_id'    => $developerSettings->id,
                    'super_admin_id'          => $superAdmin->id,
                    'agreement_number'        => $agreementNumber,
                    'amount'                  => $validatedData['amount'],
                    'currency'                => $validatedData['currency'],
                    'billing_frequency'       => $validatedData['billing_frequency'],
                    'description'             => $this->sanitizeInput($validatedData['description']),
                    'due_date'                => $dueDate,
                    'start_date'              => $validatedData['start_date'],
                    'status'                  => 'pending',
                    'payment_status'          => 'unpaid',
                    'payment_method'          => $validatedData['payment_method'] ?? $developerSettings->payment_method,
                    'payment_mobile_number'   => $paymentDetails['mobile_number']  ?? $developerSettings->payment_mobile_number,
                    'payment_account_name'    => $paymentDetails['account_name']   ?? $developerSettings->payment_account_name,
                    'payment_account_number'  => $paymentDetails['account_number'] ?? $developerSettings->payment_account_number,
                    'payment_bank_name'       => $paymentDetails['bank_name']      ?? $developerSettings->payment_bank_name,
                    'payment_bank_branch'     => $paymentDetails['bank_branch']    ?? $developerSettings->payment_bank_branch,
                    'payment_mobile_network'  => $paymentDetails['mobile_network'] ?? null,
                    'category'                => 'other',
                    'notes'                   => $this->sanitizeInput($validatedData['notes'] ?? null),
                    'requested_by'            => $user->id,
                    'requested_at'            => now(),
                    'is_primary_for_billing'  => $isPrimaryForBilling,
                    'billing_contact_name'    => $isPrimaryForBilling ? ($validatedData['billing_contact_name']  ?? $primarySuperAdmin->name)  : null,
                    'billing_contact_email'   => $isPrimaryForBilling ? ($validatedData['billing_contact_email'] ?? $primarySuperAdmin->email) : null,
                    'billing_contact_phone'   => $isPrimaryForBilling ? ($validatedData['billing_contact_phone'] ?? $primarySuperAdmin->phone) : null,
                ]);

                if ($isPrimaryForBilling) {
                    $primaryAgreementId = $agreement->id;
                }

                if ($validatedData['generate_pdf'] ?? true) {
                    GenerateAgreementPdfJob::dispatch($agreement->id, $user->id);
                }

                $notificationType = ($validatedData['auto_send_for_signature'] ?? true)
                    ? 'signature_request'
                    : 'agreement_created';

                SendSuperAdminAgreementJob::dispatch($agreement, $notificationType);

                $createdAgreements[] = $agreement;
            }

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");
            RateLimiter::clear($key);

            $this->logAudit('agreement_created', 'Billing agreements created with primary super admin', [
                'agreement_count'         => count($createdAgreements),
                'super_admin_count'       => $superAdmins->count(),
                'primary_super_admin_id'  => $validatedData['primary_super_admin_id'],
                'primary_super_admin_name'=> $primarySuperAdmin->name,
                'amount'                  => $validatedData['amount'],
                'frequency'               => $validatedData['billing_frequency'],
                'payment_method'          => $validatedData['payment_method'] ?? 'bank_transfer'
            ]);

            $message = sprintf(
                'Billing agreements created successfully for %d super admin%s! ',
                count($createdAgreements),
                count($createdAgreements) > 1 ? 's' : ''
            );

            $message .= sprintf(
                'Primary Super Admin for billing: %s (%s). ',
                $primarySuperAdmin->name,
                $primarySuperAdmin->email
            );

            if ($validatedData['auto_send_for_signature'] ?? true) {
                $message .= 'Signing invitations sent to all super admins.';
            } else {
                $message .= ' Agreements sent to all super admins for review.';
            }

            if ($validatedData['generate_pdf'] ?? true) {
                $message .= ' PDFs will be generated shortly.';
            }

            return redirect()->route('developer.billing.dashboard')
                ->with('success', $message)
                ->with('agreement_count', count($createdAgreements))
                ->with('primary_agreement_id', $primaryAgreementId);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create billing agreements', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id(),
                'trace'   => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create agreements: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function updateAgreement(UpdateAgreementRequest $request, $agreementId)
    {
        $key = 'update_agreement:' . auth()->id() . ':' . $agreementId;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return redirect()->back()
                ->with('error', "Too many attempts. Try again in {$seconds} seconds.");
        }

        RateLimiter::hit($key, 300);

        DB::beginTransaction();

        try {
            $user = auth()->user();
            $agreement = AdminBillingRecord::findOrFail($agreementId);

            $this->authorizeAgreementUpdate($agreement);

            $validatedData = $request->validated();

            $paymentDetails = [];
            if (isset($validatedData['payment_method'])) {
                $paymentDetails = $this->preparePaymentDetails($validatedData);
            }

            $wasPrimary = $agreement->is_primary_for_billing ?? false;

            if ($agreement->status === 'active') {
                $newAgreement = $agreement->replicate();
                $newAgreement->agreement_number        = $this->generateAgreementNumber();
                $newAgreement->amount                  = $validatedData['amount'];
                $newAgreement->description             = $this->sanitizeInput($validatedData['description']);
                $newAgreement->notes                   = $this->sanitizeInput($validatedData['notes'] ?? null);
                $newAgreement->change_reason           = $this->sanitizeInput($validatedData['change_reason']);
                $newAgreement->effective_date          = $validatedData['effective_date'];
                $newAgreement->previous_agreement_id   = $agreement->id;
                $newAgreement->status                  = 'pending';
                $newAgreement->payment_status          = 'unpaid';
                $newAgreement->amount_received         = 0;
                $newAgreement->agreed_at               = null;
                $newAgreement->agreed_by               = null;
                $newAgreement->agreement_pdf_path      = null;
                $newAgreement->signed_agreement_pdf_path = null;
                $newAgreement->signing_invitation_sent_at = null;
                $newAgreement->signing_completed_at    = null;
                $newAgreement->is_primary_for_billing  = $wasPrimary;

                if (!empty($paymentDetails)) {
                    $newAgreement->payment_method         = $validatedData['payment_method'];
                    $newAgreement->payment_mobile_number  = $paymentDetails['mobile_number']  ?? null;
                    $newAgreement->payment_account_name   = $paymentDetails['account_name']   ?? null;
                    $newAgreement->payment_account_number = $paymentDetails['account_number'] ?? null;
                    $newAgreement->payment_bank_name      = $paymentDetails['bank_name']      ?? null;
                    $newAgreement->payment_bank_branch    = $paymentDetails['bank_branch']    ?? null;
                    $newAgreement->payment_mobile_network = $paymentDetails['mobile_network'] ?? null;
                }

                $newAgreement->save();
                $agreement->update(['status' => 'superseded']);
                $agreement = $newAgreement;
            } else {
                $updateData = [
                    'amount'                    => $validatedData['amount'],
                    'description'               => $this->sanitizeInput($validatedData['description']),
                    'notes'                     => $this->sanitizeInput($validatedData['notes'] ?? null),
                    'change_reason'             => $this->sanitizeInput($validatedData['change_reason']),
                    'effective_date'            => $validatedData['effective_date'],
                    'agreement_pdf_path'        => null,
                    'signed_agreement_pdf_path' => null,
                ];

                if (!empty($paymentDetails)) {
                    $updateData['payment_method']         = $validatedData['payment_method'];
                    $updateData['payment_mobile_number']  = $paymentDetails['mobile_number']  ?? null;
                    $updateData['payment_account_name']   = $paymentDetails['account_name']   ?? null;
                    $updateData['payment_account_number'] = $paymentDetails['account_number'] ?? null;
                    $updateData['payment_bank_name']      = $paymentDetails['bank_name']      ?? null;
                    $updateData['payment_bank_branch']    = $paymentDetails['bank_branch']    ?? null;
                    $updateData['payment_mobile_network'] = $paymentDetails['mobile_network'] ?? null;
                }

                $agreement->update($updateData);
            }

            if ($validatedData['regenerate_pdf'] ?? true) {
                GenerateAgreementPdfJob::dispatch($agreement->id, $user->id);
            }

            $notificationType = ($validatedData['resend_for_signature'] ?? false)
                ? 'signature_request'
                : 'agreement_updated';

            $customMessage = 'Agreement updated. ' . (($validatedData['resend_for_signature'] ?? false)
                ? 'Please review and sign again.'
                : 'Please review the changes.');

            SendSuperAdminAgreementJob::dispatch($agreement, $notificationType, $customMessage);
            event(new AgreementUpdated($agreement, $user, $validatedData['change_reason']));

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget("agreement_{$agreementId}_details");
            RateLimiter::clear($key);

            $this->logAudit('agreement_updated', 'Billing agreement updated', [
                'agreement_id'     => $agreement->id,
                'agreement_number' => $agreement->agreement_number,
                'updates'          => array_keys($validatedData)
            ]);

            $message = 'Billing agreement updated successfully!';
            if ($validatedData['resend_for_signature'] ?? false) {
                $message .= ' Signing invitation resent to super admin.';
            }

            return redirect()->route('developer.billing.view-agreement', $agreement->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage(),
                'user_id'      => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update agreement: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function terminateAgreement(TerminateAgreementRequest $request, $agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $agreement = AdminBillingRecord::findOrFail($agreementId);

            $this->authorizeAgreementTermination($agreement);

            $validatedData = $request->validated();

            $wasPrimary = $agreement->is_primary_for_billing ?? false;

            $agreement->update([
                'status'             => 'terminated',
                'termination_reason' => $this->sanitizeInput($validatedData['termination_reason']),
                'termination_date'   => $validatedData['effective_date'],
                'terminated_by'      => auth()->id(),
                'terminated_at'      => now()
            ]);

            if ($wasPrimary) {
                AdminBillingRecord::where('developer_setting_id', $agreement->developer_setting_id)
                    ->update(['is_primary_for_billing' => false]);
            }

            SendSuperAdminAgreementJob::dispatch($agreement, 'agreement_terminated');
            event(new AgreementTerminated($agreement, $user, $validatedData['termination_reason']));

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");

            $this->logAudit('agreement_terminated', 'Billing agreement terminated', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'was_primary'      => $wasPrimary,
                'reason'           => $validatedData['termination_reason']
            ]);

            $message = 'Billing agreement terminated successfully!';
            if ($wasPrimary) {
                $message .= ' This was the primary billing agreement. Please assign a new primary super admin.';
            }

            return redirect()->route('developer.billing.dashboard')
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to terminate agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to terminate agreement: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | INVOICES
     * ============================================================ */

    /**
     * Developer-facing invoice list (dashboard "Billing Invoices" page).
     *
     * Aligned with the real billing_invoices schema:
     *   - amount column:          amount
     *   - paid column:            paid_amount
     *   - invoice number column:  invoice_number
     *   - issue date column:      issue_date
     *   - due date column:        due_date
     */
    public function viewInvoices(Request $request)
{
    try {
        $user = auth()->user();
        $this->authorizeDeveloperAccess($user);

        $developerSettings = $this->getOrInitializeDeveloperSettings();

        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->where('is_primary_for_billing', true)
            ->first();

        // ---------- BASE QUERY ----------
        $baseQuery = BillingInvoice::where('developer_setting_id', $developerSettings->id);

        if ($primaryAgreement) {
            if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
                $baseQuery->where('agreement_id', $primaryAgreement->id);
            } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                $baseQuery->where('admin_billing_record_id', $primaryAgreement->id);
            }
        }

        if ($request->filled('status')) {
            $baseQuery->where('status', $request->status);
        }

        if ($request->filled('billing_month')) {
            $baseQuery->where('billing_month', $request->billing_month);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $baseQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from')) {
            $baseQuery->whereDate('issue_date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $baseQuery->whereDate('issue_date', '<=', $request->get('to'));
        }

        // ---------- PAGINATED RESULTS ----------
        $invoices = (clone $baseQuery)
            ->orderBy('issue_date', 'desc')
            ->paginate($request->get('per_page', 20))
            ->withQueryString();

        // ---------- STATS (aligned with developer.billing.invoices view) ----------
        $statsQuery = clone $baseQuery;

        $stats = [
            'total'           => (clone $statsQuery)->count(),
            'paid'            => (clone $statsQuery)->where('status', 'paid')->count(),
            'pending'         => (clone $statsQuery)->where('status', 'pending')->count(),
            'overdue'         => (clone $statsQuery)->where('status', 'overdue')->count(),
            'cancelled'       => (clone $statsQuery)->where('status', 'cancelled')->count(),

            'sum_paid'        => (float) (clone $statsQuery)
                                    ->where('status', 'paid')
                                    ->sum('amount'),

            'sum_collected'   => (float) (clone $statsQuery)->sum('paid_amount'),

            'sum_outstanding' => (float) (clone $statsQuery)
                                    ->whereIn('status', ['pending', 'overdue'])
                                    ->sum('amount'),
        ];

        $availableMonths = BillingInvoice::where('developer_setting_id', $developerSettings->id)
            ->whereNotNull('billing_month')
            ->distinct('billing_month')
            ->orderBy('billing_month', 'desc')
            ->pluck('billing_month');

        return view('developer.billing.invoices', compact(
            'invoices',
            'stats',
            'availableMonths',
            'primaryAgreement',
            'developerSettings'
        ));

    } catch (\Exception $e) {
        Log::error('Failed to view invoices', [
            'error'   => $e->getMessage(),
            'user_id' => auth()->id()
        ]);

        return redirect()->back()
            ->with('error', 'Error loading invoices: ' . $e->getMessage());
    }
}

    /**
     * Same as viewInvoices() — provided so the controller can back the
     * `developer.billing.invoices` route if you choose to route it here.
     */
    public function billingInvoices(Request $request)
    {
        return $this->viewInvoices($request);
    }

    public function generateMonthlyInvoice(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();
            $invoice = $this->generateMonthlyInvoiceInternal($developerSettings, $request->get('billing_month'));

            return redirect()->route('developer.billing.dashboard')
                ->with('success', 'Monthly invoice #' . $invoice->invoice_number . ' generated successfully!')
                ->with('invoice', $invoice);

        } catch (\Exception $e) {
            Log::error('Failed to generate monthly invoice', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to generate invoice: ' . $e->getMessage());
        }
    }

    public function generateCustomInvoice(GenerateCustomInvoiceRequest $request)
{
    DB::beginTransaction();

    try {
        $user = auth()->user();
        $this->authorizeDeveloperAccess($user);

        $developerSettings = $this->getOrInitializeDeveloperSettings();
        $validatedData = $request->validated();

        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->where('is_primary_for_billing', true)
            ->first();

        if (!$primaryAgreement) {
            throw new \Exception('No primary super admin assigned. Please set up billing first.');
        }

        // ✅ FIX: use the model's safe accessor. The billing_rules column is
        // cast to array, so json_decode() on it throws TypeError on PHP 8.
        $billingRules   = $developerSettings->getBillingRulesArray();
        $invoiceDueDays = $billingRules['invoice_due_days'] ?? 30;

        $invoiceData = [
            'developer_setting_id'    => $developerSettings->id,
            'super_admin_id'          => $primaryAgreement->super_admin_id,
            'invoice_number'          => 'INV-' . strtoupper(uniqid()),
            'amount'                  => $validatedData['amount'],
            'currency'                => $developerSettings->billing_currency,
            'description'             => $this->sanitizeInput($validatedData['description']),
            'invoice_type'            => $validatedData['invoice_type'],
            'issue_date'              => now(),
            'due_date'                => Carbon::parse($validatedData['due_date']),
            'payment_due_days'        => $invoiceDueDays,
            'status'                  => 'pending',
            'is_recurring'            => false,
            'is_custom'               => true,
            'created_by'              => $user->id,
            'billing_cycle_reference' => null,
            'billing_month'           => now()->format('Y-m'),
        ];

        if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
            $invoiceData['agreement_id'] = $primaryAgreement->id;
        } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
            $invoiceData['admin_billing_record_id'] = $primaryAgreement->id;
        }

        $invoice = BillingInvoice::create($invoiceData);

        DB::commit();

        $this->logAudit('custom_invoice_generated', 'Custom invoice generated', [
            'invoice_number' => $invoice->invoice_number,
            'amount'         => $invoice->amount,
            'type'           => $invoice->invoice_type
        ]);

        return redirect()->route('developer.settings.index', ['section' => 'billing'])
            ->with('success', "Custom invoice {$invoice->invoice_number} generated successfully!")
            ->with('invoice_generated', true)
            ->with('invoice_number', $invoice->invoice_number);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to generate custom invoice', [
            'error'   => $e->getMessage(),
            'user_id' => auth()->id()
        ]);

        return redirect()->back()
            ->with('error', 'Failed to generate invoice: ' . $e->getMessage())
            ->withInput();
    }
}

    public function downloadAgreementInvoice($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $invoice = BillingInvoice::where('developer_setting_id', $agreement->developer_setting_id)
                ->where('billing_month', Carbon::now()->format('Y-m'))
                ->first();

            if (!$invoice) {
                throw new \Exception('No invoice found for this agreement');
            }

            $pdf = PDF::loadHTML('<h1>Invoice ' . $invoice->invoice_number . '</h1><p>Amount: ' . $invoice->amount . '</p>');
            $filename = "invoice-{$invoice->invoice_number}.pdf";

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Failed to download invoice', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to download invoice: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | MONTHLY INVOICE GENERATION (internal)
     * ============================================================ */

    private function generateMonthlyInvoiceInternal($developerSettings, $billingMonth = null)
    {
        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->where('is_primary_for_billing', true)
            ->where('status', 'active')
            ->first();

        if (!$primaryAgreement) {
            throw new \Exception('No primary super admin assigned for billing. Please update your billing settings.');
        }

        $billingMonth = $billingMonth ?? Carbon::now()->format('Y-m');
        $targetDate = Carbon::createFromFormat('Y-m', $billingMonth);

        $existingInvoice = null;
        if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
            $existingInvoice = BillingInvoice::where('developer_setting_id', $developerSettings->id)
                ->where('billing_month', $billingMonth)
                ->where('agreement_id', $primaryAgreement->id)
                ->first();
        } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
            $existingInvoice = BillingInvoice::where('developer_setting_id', $developerSettings->id)
                ->where('billing_month', $billingMonth)
                ->where('admin_billing_record_id', $primaryAgreement->id)
                ->first();
        } else {
            $existingInvoice = BillingInvoice::where('developer_setting_id', $developerSettings->id)
                ->where('billing_month', $billingMonth)
                ->first();
        }

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $totalPaidThisMonth = 0;
        if (Schema::hasTable('super_admin_payment_records')) {
            $totalPaidThisMonth = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                ->where('billing_month', $billingMonth)
                ->sum('amount_paid');
        }

        $amountDue = max(0, $primaryAgreement->amount - $totalPaidThisMonth);

        $invoiceNumber = 'INV-' . $targetDate->format('Ym') . '-' . str_pad(
            BillingInvoice::where('developer_setting_id', $developerSettings->id)
                ->whereYear('created_at', $targetDate->year)
                ->whereMonth('created_at', $targetDate->month)
                ->count() + 1,
            4, '0', STR_PAD_LEFT
        );

        $invoiceData = [
            'developer_setting_id' => $developerSettings->id,
            'super_admin_id'       => $primaryAgreement->super_admin_id,
            'invoice_number'       => $invoiceNumber,
            'amount'               => $amountDue,
            'original_amount'      => $primaryAgreement->amount,
            'amount_paid_already'  => $totalPaidThisMonth,
            'currency'             => $primaryAgreement->currency,
            'description'          => "Monthly system fee for {$billingMonth} - Primary Contact: {$primaryAgreement->billing_contact_name}",
            'issue_date'           => $targetDate->copy()->startOfMonth(),
            'due_date'             => $targetDate->copy()->startOfMonth()->addDays(30),
            'status'               => $amountDue > 0 ? 'pending' : 'paid',
            'is_auto_generated'    => true,
            'is_recurring'         => true,
            'billing_month'        => $billingMonth,
            'created_by'           => auth()->id(),
        ];

        if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
            $invoiceData['agreement_id'] = $primaryAgreement->id;
        } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
            $invoiceData['admin_billing_record_id'] = $primaryAgreement->id;
        }

        $invoice = BillingInvoice::create($invoiceData);

        if ($amountDue == 0) {
            $invoice->update([
                'status'      => 'paid',
                'paid_at'     => now(),
                'paid_amount' => 0,
            ]);
        }

        if ($primaryAgreement->billing_contact_email) {
            try {
                SendInvoiceEmailJob::dispatch($invoice, $primaryAgreement->billing_contact_email);
            } catch (\Exception $e) {
                Log::warning('Failed to send invoice email: ' . $e->getMessage());
            }
        }

        try {
            $this->scheduleInvoiceReminders($invoice, $developerSettings, $primaryAgreement);
        } catch (\Throwable $e) {
            Log::error('Failed to schedule invoice reminders', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $invoice;
    }

    /* ============================================================
     | BILLING SETTINGS / PRIMARY SA
     * ============================================================ */

    public function updateBilling(UpdateBillingSettingsRequest $request)
{
    $key = 'update_billing:' . auth()->id();

    if (RateLimiter::tooManyAttempts($key, 5)) {
        $seconds = RateLimiter::availableIn($key);
        return redirect()->back()
            ->with('error', "Too many attempts. Try again in {$seconds} seconds.");
    }

    RateLimiter::hit($key, 300);

    DB::beginTransaction();

    try {
        $user = auth()->user();
        $this->authorizeDeveloperAccess($user);

        $developerSettings = $this->getOrInitializeDeveloperSettings();
        $validatedData = $request->validated();

        $billingStartDate = Carbon::parse($validatedData['billing_start_date']);
        $nextBillingDate = $this->calculateNextBillingDate($billingStartDate, $validatedData['billing_cycle']);

        $billingRules = [
            'auto_generate_invoices' => $validatedData['auto_generate_invoices'] ?? true,
            'invoice_due_days'       => $validatedData['invoice_due_days'] ?? 30,
            'send_payment_reminders' => $validatedData['send_payment_reminders'] ?? true,
            'reminder_days_before'   => $validatedData['reminder_days_before'] ?? [7, 3, 1],
        ];

        $paymentDetails = $this->preparePaymentDetails($validatedData);

        // ✅ FIX: pass the array directly. The 'array' cast on
        // DeveloperSetting::$casts json_encodes on save — wrapping in
        // json_encode() here double-encodes the value.
        $developerSettings->update([
            'monthly_billing_amount' => $validatedData['monthly_billing_amount'],
            'billing_currency'       => $validatedData['billing_currency'],
            'billing_cycle'          => $validatedData['billing_cycle'],
            'billing_start_date'     => $billingStartDate,
            'next_billing_date'      => $nextBillingDate,
            'billing_status'         => 'active',
            'payment_method'         => $validatedData['payment_method'],
            'payment_mobile_number'  => $paymentDetails['mobile_number']  ?? null,
            'payment_account_name'   => $paymentDetails['account_name']   ?? null,
            'payment_account_number' => $paymentDetails['account_number'] ?? null,
            'payment_bank_name'      => $paymentDetails['bank_name']      ?? null,
            'payment_bank_branch'    => $paymentDetails['bank_branch']    ?? null,
            'payment_mobile_network' => $paymentDetails['mobile_network'] ?? null,
            'billing_rules'          => $billingRules,
            'updated_at'             => now(),
        ]);

        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->where('is_primary_for_billing', true)
            ->first();

        if ($primaryAgreement) {
            $primaryAgreement->update([
                'amount'            => $validatedData['monthly_billing_amount'],
                'currency'          => $validatedData['billing_currency'],
                'billing_frequency' => $validatedData['billing_cycle'],
            ]);
        }

        $invoice = $this->generateMonthlyInvoiceInternal($developerSettings);

        DB::commit();

        Cache::forget("developer_billing_dashboard_{$user->id}");
        Cache::forget("developer_settings_" . auth()->id());

        RateLimiter::clear($key);

        $this->logAudit('billing_updated', 'Billing settings updated', [
            'monthly_amount' => $validatedData['monthly_billing_amount'],
            'currency'       => $validatedData['billing_currency'],
            'cycle'          => $validatedData['billing_cycle'],
            'payment_method' => $validatedData['payment_method']
        ]);

        $message = 'Billing settings updated successfully!';
        if ($invoice) {
            $message .= " Invoice #{$invoice->invoice_number} has been generated.";
        }

        return redirect()->route('developer.settings.index', ['section' => 'billing'])
            ->with('success', $message)
            ->with('billing_updated', true)
            ->with('invoice_number', $invoice->invoice_number ?? null);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Failed to update billing settings', [
            'error'   => $e->getMessage(),
            'user_id' => auth()->id()
        ]);

        return redirect()->back()
            ->with('error', 'Failed to update billing settings: ' . $e->getMessage())
            ->withInput();
    }
}

    public function changePrimarySuperAdmin(Request $request)
    {
        $request->validate([
            'new_primary_super_admin_id' => 'required|exists:users,id',
            'billing_contact_name'       => 'nullable|string|max:255',
            'billing_contact_email'      => 'nullable|email|max:255',
            'billing_contact_phone'      => 'nullable|string|max:20',
            'reason'                     => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $newPrimarySuperAdmin = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->findOrFail($request->new_primary_super_admin_id);

            AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->update(['is_primary_for_billing' => false]);

            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->where('super_admin_id', $newPrimarySuperAdmin->id)
                ->first();

            if (!$primaryAgreement) {
                $primaryAgreement = $this->createAgreementForSuperAdmin($developerSettings, $newPrimarySuperAdmin);
            }

            $primaryAgreement->update([
                'is_primary_for_billing' => true,
                'billing_contact_name'   => $request->get('billing_contact_name', $newPrimarySuperAdmin->name),
                'billing_contact_email'  => $request->get('billing_contact_email', $newPrimarySuperAdmin->email),
                'billing_contact_phone'  => $request->get('billing_contact_phone', $newPrimarySuperAdmin->phone),
            ]);

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget("developer_settings_" . auth()->id());

            $this->logAudit('primary_super_admin_changed', 'Primary super admin for billing changed', [
                'new_primary_id'   => $newPrimarySuperAdmin->id,
                'new_primary_name' => $newPrimarySuperAdmin->name,
                'reason'           => $request->reason,
            ]);

            return redirect()->route('developer.settings.index', ['section' => 'billing'])
                ->with('success', "Primary Super Admin changed to {$newPrimarySuperAdmin->name} ({$newPrimarySuperAdmin->email})");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to change primary super admin', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to change primary super admin: ' . $e->getMessage());
        }
    }

    public function getPrimarySuperAdminInfo()
    {
        try {
            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->where('is_primary_for_billing', true)
                ->with('superAdmin')
                ->first();

            if ($primaryAgreement && $primaryAgreement->superAdmin) {
                return response()->json([
                    'success' => true,
                    'primary_super_admin' => [
                        'id'    => $primaryAgreement->superAdmin->id,
                        'name'  => $primaryAgreement->billing_contact_name  ?? $primaryAgreement->superAdmin->name,
                        'email' => $primaryAgreement->billing_contact_email ?? $primaryAgreement->superAdmin->email,
                        'phone' => $primaryAgreement->billing_contact_phone ?? $primaryAgreement->superAdmin->phone,
                    ],
                    'agreement_id'     => $primaryAgreement->id,
                    'agreement_number' => $primaryAgreement->agreement_number,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No primary super admin assigned'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /* ============================================================
     | PAYMENTS
     * ============================================================ */

    public function confirmPayment(ConfirmPaymentRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $validatedData = $request->validated();

            $originalPayment = DB::table('agreement_payments')
                ->where('id', $validatedData['payment_id'])
                ->first();

            if (!$originalPayment) {
                throw new \Exception('Payment record not found');
            }

            $agreement = AdminBillingRecord::find($originalPayment->admin_billing_record_id);

            if (!$agreement) {
                throw new \Exception('Agreement not found');
            }

            DB::table('agreement_payments')
                ->where('id', $validatedData['payment_id'])
                ->update([
                    'status'                => 'confirmed',
                    'confirmed_by'          => $user->id,
                    'confirmed_at'          => now(),
                    'amount_paid'           => $validatedData['amount_paid'],
                    'payment_date'          => $validatedData['payment_date'],
                    'payment_method'        => $validatedData['payment_method'],
                    'transaction_reference' => $validatedData['transaction_reference'] ?? null,
                    'notes'                 => $validatedData['notes'] ?? null,
                    'updated_at'            => now(),
                ]);

            $newAmountReceived = $agreement->amount_received + $validatedData['amount_paid'];
            $agreement->update([
                'amount_received' => $newAmountReceived,
                'payment_status'  => $newAmountReceived >= $agreement->amount ? 'paid' : 'partial'
            ]);

            $allSuperAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get();

            $currentBillingMonth = Carbon::now()->format('Y-m');
            $paymentRecordsCreated = 0;

            foreach ($allSuperAdmins as $superAdmin) {
                $superAdminAgreement = AdminBillingRecord::where('developer_setting_id', $agreement->developer_setting_id)
                    ->where('super_admin_id', $superAdmin->id)
                    ->first();

                if ($superAdminAgreement) {
                    SuperAdminPaymentRecord::updateOrCreate(
                        [
                            'agreement_id'   => $superAdminAgreement->id,
                            'super_admin_id' => $superAdmin->id,
                            'billing_month'  => $currentBillingMonth,
                        ],
                        [
                            'amount_paid'           => DB::raw("amount_paid + {$validatedData['amount_paid']}"),
                            'last_payment_date'     => $validatedData['payment_date'],
                            'payment_method'        => $validatedData['payment_method'],
                            'transaction_reference' => $validatedData['transaction_reference'] ?? null,
                            'status'                => 'confirmed',
                            'confirmed_by'          => $user->id,
                            'confirmed_at'          => now(),
                            'updated_at'            => now(),
                        ]
                    );
                    $paymentRecordsCreated++;

                    $newSaAmountReceived = $superAdminAgreement->amount_received + $validatedData['amount_paid'];
                    $superAdminAgreement->update([
                        'amount_received' => $newSaAmountReceived,
                        'payment_status'  => $newSaAmountReceived >= $superAdminAgreement->amount ? 'paid' : 'partial'
                    ]);
                }
            }

            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $agreement->developer_setting_id)
                ->where('is_primary_for_billing', true)
                ->first();

            if ($primaryAgreement) {
                $invoiceQuery = BillingInvoice::where('developer_setting_id', $agreement->developer_setting_id)
                    ->where('billing_month', $currentBillingMonth);

                if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
                    $invoiceQuery->where('agreement_id', $primaryAgreement->id);
                } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                    $invoiceQuery->where('admin_billing_record_id', $primaryAgreement->id);
                }

                $invoices = $invoiceQuery->get();

                foreach ($invoices as $invoice) {
                    $totalPaidThisMonth = SuperAdminPaymentRecord::where('developer_setting_id', $agreement->developer_setting_id)
                        ->where('billing_month', $currentBillingMonth)
                        ->sum('amount_paid');

                    if ($totalPaidThisMonth >= $primaryAgreement->amount) {
                        $invoice->update([
                            'status'      => 'paid',
                            'paid_at'     => now(),
                            'paid_amount' => $totalPaidThisMonth,
                        ]);
                    } elseif ($totalPaidThisMonth > 0) {
                        $invoice->update([
                            'status'      => 'partial',
                            'paid_amount' => $totalPaidThisMonth,
                        ]);
                    }
                }
            }

            event(new PaymentConfirmed($originalPayment, $agreement, $user));

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");

            foreach ($allSuperAdmins as $superAdmin) {
                Cache::forget("superadmin_billing_dashboard_{$superAdmin->id}");
            }

            $this->logAudit('payment_confirmed', 'Payment confirmed and recorded for all super admins', [
                'payment_id'                 => $validatedData['payment_id'],
                'amount'                     => $validatedData['amount_paid'],
                'original_super_admin_id'    => $agreement->super_admin_id,
                'total_super_admins_updated' => $paymentRecordsCreated,
                'billing_month'              => $currentBillingMonth,
            ]);

            $message = sprintf(
                'Payment of %s %s confirmed successfully! Payment has been recorded for all %d super admin(s).',
                $agreement->currency,
                number_format($validatedData['amount_paid'], 2),
                $paymentRecordsCreated
            );

            return redirect()->route('developer.billing.dashboard')
                ->with('success', $message)
                ->with('payment_amount', $validatedData['amount_paid'])
                ->with('super_admins_updated', $paymentRecordsCreated);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to confirm payment', [
                'error'      => $e->getMessage(),
                'user_id'    => auth()->id(),
                'payment_id' => $request->payment_id ?? null
            ]);

            return redirect()->back()
                ->with('error', 'Failed to confirm payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function recordPayment(RecordPaymentRequest $request, $agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $validatedData = $request->validated();

            $paymentId = DB::table('agreement_payments')->insertGetId([
                'admin_billing_record_id' => $agreementId,
                'amount_paid'             => $validatedData['amount_paid'],
                'payment_date'            => $validatedData['payment_date'],
                'payment_method'          => $validatedData['payment_method'],
                'transaction_reference'   => $validatedData['transaction_reference'] ?? null,
                'notes'                   => $validatedData['notes'] ?? null,
                'status'                  => 'pending_confirmation',
                'recorded_by'             => $user->id,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            $this->logAudit('payment_recorded', 'Payment recorded awaiting confirmation', [
                'agreement_id' => $agreementId,
                'payment_id'   => $paymentId,
                'amount'       => $validatedData['amount_paid']
            ]);

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");

            return redirect()->route('developer.billing.view-agreement', $agreementId)
                ->with('success', 'Payment recorded and pending confirmation.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to record payment', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    public function showRecordPaymentForm($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $paymentMethods = $this->getPaymentMethodsConfiguration();

            return view('developer.billing.record-payment', compact('agreement', 'paymentMethods'));

        } catch (\Exception $e) {
            Log::error('Failed to load record payment form', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error loading form: ' . $e->getMessage());
        }
    }

    public function viewPaymentDetails($paymentId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $payment = DB::table('agreement_payments')
                ->where('id', $paymentId)
                ->first();

            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $agreement = AdminBillingRecord::findOrFail($payment->admin_billing_record_id);
            $this->authorizeAgreementAccess($agreement);

            $recordedBy = $payment->recorded_by ? User::find($payment->recorded_by) : null;
            $confirmedBy = $payment->confirmed_by ? User::find($payment->confirmed_by) : null;

            return view('developer.billing.payment-details', compact('payment', 'agreement', 'recordedBy', 'confirmedBy'));

        } catch (\Exception $e) {
            Log::error('Failed to view payment details', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error loading payment details: ' . $e->getMessage());
        }
    }

    public function paymentHistory(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $query = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                ->with('superAdmin', 'agreement')
                ->orderBy('payment_date', 'desc');

            if ($request->filled('super_admin_id')) {
                $query->where('super_admin_id', $request->super_admin_id);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('payment_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('payment_date', '<=', $request->end_date);
            }

            $payments = $query->paginate($request->get('per_page', 20))->withQueryString();

            // Separate clone for stats so pagination doesn't leak into sums
            $statsQuery = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id);

            if ($request->filled('super_admin_id')) {
                $statsQuery->where('super_admin_id', $request->super_admin_id);
            }
            if ($request->filled('start_date')) {
                $statsQuery->whereDate('payment_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $statsQuery->whereDate('payment_date', '<=', $request->end_date);
            }

            $stats = [
                'total_paid'     => (float) (clone $statsQuery)->sum('amount_paid'),
                'total_payments' => (clone $statsQuery)->count(),
            ];

            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'email']);

            return view('developer.billing.payment-history', compact('payments', 'stats', 'superAdmins'));

        } catch (\Exception $e) {
            Log::error('Failed to load payment history', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return view('developer.billing.payment-history', [
                'payments'    => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'stats'       => ['total_paid' => 0, 'total_payments' => 0],
                'superAdmins' => collect()
            ])->with('error', 'Error loading payment history: ' . $e->getMessage());
        }
    }

    public function superAdminPayments(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $query = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                ->with('superAdmin', 'agreement')
                ->orderBy('created_at', 'desc');

            if ($request->filled('super_admin_id')) {
                $query->where('super_admin_id', $request->super_admin_id);
            }

            if ($request->filled('billing_month')) {
                $query->where('billing_month', $request->billing_month);
            }

            $paymentRecords = $query->paginate($request->get('per_page', 20))->withQueryString();

            $statsQuery = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id);
            if ($request->filled('super_admin_id')) {
                $statsQuery->where('super_admin_id', $request->super_admin_id);
            }
            if ($request->filled('billing_month')) {
                $statsQuery->where('billing_month', $request->billing_month);
            }

            $stats = [
                'total_paid'    => (float) (clone $statsQuery)->sum('amount_paid'),
                'total_records' => (clone $statsQuery)->count(),
                'unique_months' => (clone $statsQuery)->distinct('billing_month')->count('billing_month'),
            ];

            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'email']);

            $availableMonths = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                ->distinct('billing_month')
                ->orderBy('billing_month', 'desc')
                ->pluck('billing_month');

            return view('developer.billing.superadmin-payments', compact('paymentRecords', 'stats', 'superAdmins', 'availableMonths'));

        } catch (\Exception $e) {
            Log::error('Failed to load super admin payments', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            $paymentRecords = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $stats = ['total_paid' => 0, 'total_records' => 0, 'unique_months' => 0];
            $superAdmins = collect();
            $availableMonths = collect();

            return view('developer.billing.superadmin-payments', compact('paymentRecords', 'stats', 'superAdmins', 'availableMonths'))
                ->with('error', 'Error loading payment records: ' . $e->getMessage());
        }
    }

    public function viewSuperAdminPayments(Request $request)
    {
        return $this->superAdminPayments($request);
    }

    /* ============================================================
     | SUPER ADMIN PAYMENT REQUESTS
     * ============================================================ */

    public function createSuperAdminPaymentRequest(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $validatedData = $request->validate([
                'super_admin_id' => 'required|exists:users,id',
                'amount'         => 'required|numeric|min:0.01',
                'due_date'       => 'required|date|after:today',
                'description'    => 'required|string|max:500',
                'payment_method' => 'nullable|string',
            ]);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $paymentRequest = SuperAdminPaymentRequest::create([
                'developer_setting_id' => $developerSettings->id,
                'super_admin_id'       => $validatedData['super_admin_id'],
                'amount'               => $validatedData['amount'],
                'due_date'             => $validatedData['due_date'],
                'description'          => $validatedData['description'],
                'payment_method'       => $validatedData['payment_method'] ?? null,
                'status'               => 'pending',
                'created_by'           => $user->id,
                'created_at'           => now(),
            ]);

            DB::commit();

            $this->logAudit('payment_request_created', 'Payment request created for super admin', [
                'request_id'     => $paymentRequest->id,
                'super_admin_id' => $validatedData['super_admin_id'],
                'amount'         => $validatedData['amount']
            ]);

            return redirect()->route('developer.billing.super-admin-payments')
                ->with('success', 'Payment request created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create payment request', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create payment request: ' . $e->getMessage());
        }
    }

    public function showCreateSuperAdminRequestForm()
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'email']);

            return view('developer.billing.create-payment-request', compact('superAdmins'));

        } catch (\Exception $e) {
            Log::error('Failed to load create payment request form', [
                'error' => $e->getMessage()
            ]);

            return redirect()->route('developer.billing.super-admin-payments')
                ->with('error', 'Error loading form: ' . $e->getMessage());
        }
    }

    public function createSuperAdminRequest(Request $request)
    {
        return $this->createSuperAdminPaymentRequest($request);
    }

    public function markSuperAdminPaymentReceived(Request $request, $requestId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $paymentRequest = SuperAdminPaymentRequest::findOrFail($requestId);

            $paymentRequest->update([
                'status'                => 'paid',
                'paid_at'               => now(),
                'paid_by'               => $user->id,
                'transaction_reference' => $request->get('transaction_reference'),
                'notes'                 => $request->get('notes'),
            ]);

            DB::commit();

            $this->logAudit('payment_request_marked_paid', 'Payment request marked as paid', [
                'request_id' => $requestId,
                'amount'     => $paymentRequest->amount
            ]);

            return redirect()->route('developer.billing.super-admin-payments')
                ->with('success', 'Payment marked as received.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to mark payment as received', [
                'request_id' => $requestId,
                'error'      => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to mark payment: ' . $e->getMessage());
        }
    }

    public function markSaPaymentReceived(Request $request, $requestId)
    {
        return $this->markSuperAdminPaymentReceived($request, $requestId);
    }

    public function sendSuperAdminPaymentReminder($requestId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $paymentRequest = SuperAdminPaymentRequest::with('superAdmin')->findOrFail($requestId);

            $this->logAudit('payment_reminder_sent', 'Payment reminder sent to super admin', [
                'request_id'        => $requestId,
                'super_admin_email' => $paymentRequest->superAdmin->email
            ]);

            return redirect()->back()
                ->with('success', 'Reminder sent to ' . $paymentRequest->superAdmin->email);

        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder', [
                'request_id' => $requestId,
                'error'      => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }
    }

    public function sendSuperAdminReminder($requestId)
    {
        return $this->sendSuperAdminPaymentReminder($requestId);
    }

    public function cancelSuperAdminPaymentRequest($requestId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $paymentRequest = SuperAdminPaymentRequest::findOrFail($requestId);

            if ($paymentRequest->status !== 'pending') {
                throw new \Exception('Only pending requests can be cancelled.');
            }

            $paymentRequest->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);

            DB::commit();

            $this->logAudit('payment_request_cancelled', 'Payment request cancelled', [
                'request_id' => $requestId
            ]);

            return redirect()->route('developer.billing.super-admin-payments')
                ->with('success', 'Payment request cancelled.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to cancel payment request', [
                'request_id' => $requestId,
                'error'      => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to cancel request: ' . $e->getMessage());
        }
    }

    public function cancelSuperAdminRequest($requestId)
    {
        return $this->cancelSuperAdminPaymentRequest($requestId);
    }

    /* ============================================================
     | SIGNATURES
     * ============================================================ */

    public function signAgreement(Request $request, $agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $validator = Validator::make($request->all(), [
                'signature'      => 'required|string',
                'signature_name' => 'required|string|min:2|max:255',
                'signature_type' => 'required|in:typed,draw,upload',
                'confirm_terms'  => 'required|accepted',
                'ip_address'     => 'nullable|ip',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Please provide your signature and accept the terms.');
            }

            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting'])
                ->findOrFail($agreementId);

            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'pending') {
                return redirect()->route('developer.billing.view-agreement', $agreementId)
                    ->with('error', 'This agreement cannot be signed as it is not in pending status.');
            }

            $existingSignature = AgreementSignature::where('agreement_id', $agreementId)
                ->where('user_id', $user->id)
                ->where('signature_type', 'developer')
                ->first();

            if ($existingSignature) {
                return redirect()->route('developer.billing.view-agreement', $agreementId)
                    ->with('warning', 'You have already signed this agreement.');
            }

            DB::beginTransaction();

            $signatureValue  = $request->signature;
            $signaturePath   = null;
            $signatureFormat = $request->signature_type;

            if ($request->signature_type === 'draw' || $request->signature_type === 'upload') {
                $signaturePath   = $this->saveBase64Signature($signatureValue, $agreementId, $user->id);
                $signatureValue  = $request->signature_name;
                $signatureFormat = $request->signature_type === 'draw' ? 'drawn' : 'uploaded';
            } else {
                $signatureValue  = $request->signature;
                $signatureFormat = 'typed';
            }

            $signature = AgreementSignature::create([
                'agreement_id'     => $agreementId,
                'user_id'          => $user->id,
                'signature_type'   => 'developer',
                'signature_name'   => $signatureValue,
                'signature_date'   => now(),
                'ip_address'       => $request->ip_address ?? $request->ip(),
                'user_agent'       => $request->userAgent(),
                'status'           => 'verified',
                'signature_format' => $signatureFormat,
                'signature_path'   => $signaturePath,
                'digital_hash'     => hash('sha256', $user->id . $agreementId . $signatureValue . now()),
            ]);

            $superAdminSignature = AgreementSignature::where('agreement_id', $agreementId)
                ->where('signature_type', 'super_admin')
                ->first();

            $message = '';
            $invoice = null;

            if ($superAdminSignature) {
                $agreement->update([
                    'status'                => 'active',
                    'agreed_at'             => now(),
                    'agreed_by'             => $user->id,
                    'signing_completed_at'  => now(),
                ]);

                $this->generateFinalSignedAgreementPdf($agreementId);

                try {
                    $developerSettings = $this->getOrInitializeDeveloperSettings();
                    $isPrimary = $agreement->is_primary_for_billing ?? false;

                    if ($isPrimary) {
                        $invoice = $this->generateMonthlyInvoiceInternal($developerSettings);
                        $message = 'Agreement signed successfully! The agreement is now active and an invoice has been generated.';

                        if ($agreement->billing_contact_email ?? $agreement->superAdmin->email) {
                            $email = $agreement->billing_contact_email ?? $agreement->superAdmin->email;
                            SendInvoiceEmailJob::dispatch($invoice, $email);
                        }
                    } else {
                        $message = 'Agreement signed successfully! The agreement is now active.';
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to generate invoice after agreement signing', [
                        'agreement_id' => $agreementId,
                        'error'        => $e->getMessage(),
                    ]);
                    $message = 'Agreement signed successfully! The agreement is now active. (Note: Invoice generation encountered an issue.)';
                }
            } else {
                $agreement->update(['status' => 'pending']);
                $message = 'Your signature has been recorded. Waiting for super admin signature to activate the agreement.';
            }

            DB::commit();

            if ($superAdminSignature) {
                SendSuperAdminAgreementJob::dispatch($agreement, 'agreement_activated');
            }

            $this->logAudit('agreement_signed', 'Developer signed agreement', [
                'agreement_id'      => $agreementId,
                'agreement_number'  => $agreement->agreement_number,
                'signature_id'      => $signature->id,
                'signature_format'  => $signatureFormat,
                'is_primary'        => $agreement->is_primary_for_billing ?? false,
                'invoice_generated' => $invoice ? $invoice->invoice_number : null
            ]);

            return redirect()->route('developer.billing.view-agreement', $agreementId)
                ->with('success', $message)
                ->with('invoice_generated', $invoice ? true : false)
                ->with('invoice_number', $invoice ? $invoice->invoice_number : null);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to sign agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to sign agreement: ' . $e->getMessage());
        }
    }

    private function saveBase64Signature($base64Data, $agreementId, $userId)
    {
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $imageType = $matches[1];
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            } else {
                $imageType = 'png';
            }

            $imageData = base64_decode($base64Data);

            if ($imageData === false) {
                Log::error('Failed to decode base64 signature');
                return null;
            }

            $directory = "signatures/developer/{$userId}";
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory, 0755, true);
            }

            $filename = "signature_agreement_{$agreementId}_" . time() . ".{$imageType}";
            $path = "{$directory}/{$filename}";

            Storage::put($path, $imageData);

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to save signature image', [
                'error'        => $e->getMessage(),
                'agreement_id' => $agreementId
            ]);
            return null;
        }
    }

    public function submitSignature(Request $request, $agreementId)
    {
        return $this->signAgreement($request, $agreementId);
    }

    public function revokeSignature(Request $request, $agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);

            if ($agreement->status !== 'active') {
                throw new \Exception('Cannot revoke signatures for non-active agreement');
            }

            if (!$this->canRevokeSignatures($agreement)) {
                throw new \Exception('Signatures cannot be revoked at this time');
            }

            AgreementSignature::where('agreement_id', $agreementId)->delete();

            $agreement->update([
                'status'                    => 'pending',
                'agreed_at'                 => null,
                'agreed_by'                 => null,
                'signed_agreement_pdf_path' => null,
                'signing_completed_at'      => null,
            ]);

            event(new SignatureRevoked($agreement, $user));

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");

            $this->logAudit('signatures_revoked', 'Agreement signatures revoked', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'is_primary'       => $agreement->is_primary_for_billing
            ]);

            return redirect()->route('developer.billing.view-agreement', $agreementId)
                ->with('success', 'Signatures revoked successfully. Agreement is now pending new signatures.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to revoke signatures', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to revoke signatures: ' . $e->getMessage());
        }
    }

    public function viewSignatureAudit($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with(['signatures.user', 'superAdmin', 'developerSetting'])
                ->findOrFail($agreementId);

            $this->authorizeAgreementAccess($agreement);

            $signatures = $agreement->signatures->map(function ($signature) {
                return [
                    'id'             => $signature->id,
                    'user_name'      => $signature->user->name,
                    'user_email'     => $signature->user->email,
                    'user_type'      => $signature->user->type === User::TYPE_DEVELOPER ? 'Developer' : 'Super Admin',
                    'signature_type' => $signature->signature_type,
                    'signature_name' => $signature->signature_name,
                    'signature_date' => $signature->signature_date->format('Y-m-d H:i:s'),
                    'ip_address'     => $signature->ip_address,
                    'user_agent'     => $signature->user_agent,
                    'created_at'     => $signature->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $signatureStatus = $this->checkSignaturesComplete($agreementId);

            return view('developer.billing.signature-audit', compact('agreement', 'signatures', 'signatureStatus'));

        } catch (\Exception $e) {
            Log::error('Failed to view signature audit', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error loading signature audit: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | RECURRING BILLING
     * ============================================================ */

    public function processRecurringBilling(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $today = Carbon::today();
            $nextBillingDate = Carbon::parse($developerSettings->next_billing_date);

            if ($today->lt($nextBillingDate) && !$request->get('force', false)) {
                return redirect()->back()
                    ->with('info', 'Not yet time for recurring billing. Next billing date: ' . $nextBillingDate->format('F j, Y'));
            }

            DB::beginTransaction();

            $invoice = $this->generateMonthlyInvoiceInternal($developerSettings);

            $nextBillingDate = $this->calculateNextBillingDate(
                Carbon::parse($developerSettings->billing_start_date),
                $developerSettings->billing_cycle,
                $developerSettings->next_billing_date
            );

            $developerSettings->update([
                'next_billing_date' => $nextBillingDate,
                'updated_at'        => now()
            ]);

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");

            $this->logAudit('recurring_billing_processed', 'Recurring billing processed', [
                'invoice_number'    => $invoice->invoice_number,
                'amount'            => $invoice->amount,
                'next_billing_date' => $nextBillingDate
            ]);

            return redirect()->back()
                ->with('success', 'Recurring billing processed successfully! Invoice #' . $invoice->invoice_number . ' generated.')
                ->with('invoice_number', $invoice->invoice_number);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to process recurring billing', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to process recurring billing: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | REPORTS / EXPORTS / STATISTICS
     * ============================================================ */

    public function billingReports(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $dateRange = $this->validateDateRange($request);
            $startDate = $dateRange['start_date'];
            $endDate = $dateRange['end_date'];

            $query = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            $agreements = $query->with(['superAdmin', 'payments' => function ($q) {
                $q->where('status', 'confirmed');
            }])->get();

            $payments = collect();
            if (Schema::hasTable('agreement_payments')) {
                $payments = DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.developer_setting_id', $developerSettings->id)
                    ->where('p.status', 'confirmed')
                    ->whereBetween('p.payment_date', [$startDate, $endDate])
                    ->select('p.*', 'a.agreement_number')
                    ->orderBy('p.payment_date', 'desc')
                    ->get();
            }

            $superAdminPaymentRecords = collect();
            if (Schema::hasTable('super_admin_payment_records')) {
                $superAdminPaymentRecords = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->with('superAdmin')
                    ->get();
            }

            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->where('is_primary_for_billing', true)
                ->first();

            $stats = [
                'total_agreements'          => $agreements->count(),
                'active_agreements'         => $agreements->where('status', 'active')->count(),
                'completed_agreements'      => $agreements->where('status', 'completed')->count(),
                'total_amount_agreed'       => $agreements->sum('amount'),
                'total_amount_received'     => $agreements->sum('amount_received'),
                'total_payments'            => $payments->count(),
                'total_payment_amount'      => $payments->sum('amount_paid'),
                'total_sa_payment_records'  => $superAdminPaymentRecords->count(),
                'total_sa_payment_amount'   => $superAdminPaymentRecords->sum('amount_paid'),
                'pending_payments'          => $agreements->where('status', 'active')->sum('amount')
                    - $agreements->where('status', 'active')->sum('amount_received'),
                'has_primary'               => $primaryAgreement ? true : false,
                'primary_super_admin'       => $primaryAgreement ? $primaryAgreement->billing_contact_name ?? $primaryAgreement->superAdmin->name ?? 'N/A' : 'Not assigned',
            ];

            $monthlyBreakdown = $this->getMonthlyBreakdown($developerSettings->id, $startDate, $endDate);
            $paymentMethodBreakdown = $this->getPaymentMethodBreakdown($developerSettings->id, $startDate, $endDate);

            return view('developer.billing.reports', compact(
                'stats',
                'agreements',
                'payments',
                'superAdminPaymentRecords',
                'monthlyBreakdown',
                'paymentMethodBreakdown',
                'startDate',
                'endDate',
                'primaryAgreement'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to generate billing reports', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Error generating reports: ' . $e->getMessage());
        }
    }

    public function exportBillingData(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $exportType = $request->get('export_type', 'invoices');
            $startDate = $request->get('start_date', now()->subMonth()->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $fileName = 'billing-export-' . $exportType . '-' . date('Y-m-d-H-i-s') . '.csv';

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            switch ($exportType) {
                case 'invoices':
                    $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('is_primary_for_billing', true)
                        ->first();

                    $query = BillingInvoice::where('developer_setting_id', $developerSettings->id)
                        ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

                    if ($primaryAgreement) {
                        if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
                            $query->where('agreement_id', $primaryAgreement->id);
                        } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                            $query->where('admin_billing_record_id', $primaryAgreement->id);
                        }
                    }

                    $data = $query->get();

                    $columns = [
                        'Invoice Number', 'Amount', 'Paid Amount', 'Currency', 'Type', 'Status',
                        'Issue Date', 'Due Date', 'Description', 'Billing Month'
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            fputcsv($file, [
                                $item->invoice_number,
                                number_format((float) $item->amount, 2),
                                number_format((float) ($item->paid_amount ?? 0), 2),
                                $item->currency,
                                ucfirst($item->invoice_type ?? 'N/A'),
                                ucfirst($item->status),
                                $item->issue_date ? Carbon::parse($item->issue_date)->format('Y-m-d') : 'N/A',
                                $item->due_date ? Carbon::parse($item->due_date)->format('Y-m-d') : 'N/A',
                                $item->description,
                                $item->billing_month ?? 'N/A',
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                case 'agreements':
                    $data = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                        ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                        ->with('superAdmin')
                        ->get();

                    $columns = [
                        'Agreement Number', 'Super Admin', 'Is Primary', 'Billing Contact', 'Amount', 'Currency', 'Frequency',
                        'Status', 'Payment Status', 'Start Date', 'Due Date', 'Amount Received',
                        'Payment Method', 'Payment Details', 'Description'
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            $paymentDetails = '';
                            switch ($item->payment_method) {
                                case 'bank_transfer':
                                    $paymentDetails = sprintf('Bank: %s, Account: %s, Name: %s',
                                        $item->payment_bank_name ?? 'N/A',
                                        $item->payment_account_number ?? 'N/A',
                                        $item->payment_account_name ?? 'N/A');
                                    break;
                                case 'mobile_money':
                                    $paymentDetails = sprintf('Network: %s, Number: %s, Name: %s',
                                        ucfirst($item->payment_mobile_network ?? 'N/A'),
                                        $item->payment_mobile_number ?? 'N/A',
                                        $item->payment_account_name ?? 'N/A');
                                    break;
                                default:
                                    $paymentDetails = ucfirst(str_replace('_', ' ', $item->payment_method ?? 'N/A'));
                                    break;
                            }

                            $billingContact = '';
                            if ($item->is_primary_for_billing) {
                                $billingContact = sprintf('%s (%s)',
                                    $item->billing_contact_name ?? 'N/A',
                                    $item->billing_contact_email ?? 'N/A');
                            }

                            fputcsv($file, [
                                $item->agreement_number,
                                $item->superAdmin->name ?? 'N/A',
                                $item->is_primary_for_billing ? 'Yes' : 'No',
                                $billingContact,
                                number_format($item->amount, 2),
                                $item->currency,
                                ucfirst($item->billing_frequency),
                                ucfirst($item->status),
                                ucfirst($item->payment_status),
                                $item->start_date ? Carbon::parse($item->start_date)->format('Y-m-d') : 'N/A',
                                $item->due_date ? Carbon::parse($item->due_date)->format('Y-m-d') : 'N/A',
                                number_format($item->amount_received, 2),
                                ucfirst(str_replace('_', ' ', $item->payment_method ?? 'N/A')),
                                $paymentDetails,
                                $item->description
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                case 'payments':
                    $data = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                        ->whereBetween('payment_date', [$startDate, $endDate])
                        ->with('superAdmin', 'agreement')
                        ->get();

                    $columns = [
                        'Payment Date', 'Billing Month', 'Super Admin', 'Agreement Number', 'Is Primary Agreement',
                        'Amount Paid', 'Payment Method', 'Transaction Reference', 'Status',
                        'Confirmed By', 'Confirmed At'
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            $isPrimary = $item->agreement && $item->agreement->is_primary_for_billing ? 'Yes' : 'No';

                            fputcsv($file, [
                                $item->payment_date,
                                $item->billing_month,
                                $item->superAdmin->name ?? 'N/A',
                                $item->agreement->agreement_number ?? 'N/A',
                                $isPrimary,
                                number_format($item->amount_paid, 2),
                                ucfirst(str_replace('_', ' ', $item->payment_method)),
                                $item->transaction_reference ?? 'N/A',
                                ucfirst($item->status),
                                $item->confirmed_by_name ?? 'N/A',
                                $item->confirmed_at ?? 'N/A'
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                default:
                    return redirect()->back()->with('error', 'Invalid export type');
            }

            $this->logAudit('billing_data_exported', 'Billing data exported', [
                'export_type' => $exportType,
                'date_range'  => "{$startDate} to {$endDate}"
            ]);

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Failed to export billing data', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Error exporting data: ' . $e->getMessage());
        }
    }

    public function exportHistory(Request $request) { return $this->exportBillingData($request); }
    public function exportReports(Request $request) { return $this->exportBillingData($request); }

    public function viewStatistics(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();
            $year = $request->get('year', now()->year);

            $monthlyData = [];
            for ($month = 1; $month <= 12; $month++) {
                $invoiced = BillingInvoice::where('developer_setting_id', $developerSettings->id)
                    ->whereYear('issue_date', $year)
                    ->whereMonth('issue_date', $month)
                    ->sum('amount');

                $paid = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                    ->whereYear('payment_date', $year)
                    ->whereMonth('payment_date', $month)
                    ->sum('amount_paid');

                $monthlyData[] = [
                    'month'    => Carbon::create($year, $month, 1)->format('F'),
                    'invoiced' => (float) $invoiced,
                    'paid'     => (float) $paid,
                ];
            }

            $totalInvoiced = (float) BillingInvoice::where('developer_setting_id', $developerSettings->id)->sum('amount');
            $totalPaid = (float) SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)->sum('amount_paid');

            $stats = [
                'total_agreements'  => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->count(),
                'active_agreements' => AdminBillingRecord::where('developer_setting_id', $developerSettings->id)->where('status', 'active')->count(),
                'total_invoiced'    => $totalInvoiced,
                'total_paid'        => $totalPaid,
                'collection_rate'   => $totalInvoiced > 0 ? round(($totalPaid / $totalInvoiced) * 100, 2) : 0,
            ];

            return view('developer.billing.statistics', compact('monthlyData', 'stats', 'year'));

        } catch (\Exception $e) {
            Log::error('Failed to load statistics', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->with('error', 'Error loading statistics: ' . $e->getMessage());
        }
    }

    /**
 * Export the billing report as a PDF.
 *
 * Mirrors exportBillingData()'s query shape, but renders a printable
 * blade partial and streams it via DomPDF.
 *
 * Query parameters accepted:
 *   - report_type : summary | detailed | monthly   (default: summary)
 *   - start_date  : YYYY-MM-DD                     (default: start of month)
 *   - end_date    : YYYY-MM-DD                     (default: today)
 */
public function exportBillingDataPdf(Request $request)
{
    try {
        $user = auth()->user();
        $this->authorizeDeveloperAccess($user);

        $developerSettings = $this->getOrInitializeDeveloperSettings();

        $reportType = $request->get('report_type', 'summary');
        $startDate  = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate    = $request->get('end_date', now()->toDateString());

        // ---------- AGREEMENTS ----------
        $agreements = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with(['superAdmin', 'payments' => fn ($q) => $q->where('status', 'confirmed')])
            ->get();

        // ---------- PAYMENTS ----------
        $payments = collect();
        if (Schema::hasTable('agreement_payments')) {
            $payments = DB::table('agreement_payments as p')
                ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                ->leftJoin('users as u', 'a.super_admin_id', '=', 'u.id')
                ->where('a.developer_setting_id', $developerSettings->id)
                ->where('p.status', 'confirmed')
                ->whereBetween('p.payment_date', [$startDate, $endDate])
                ->select('p.*', 'a.agreement_number', 'a.currency', 'u.name as super_admin_name')
                ->orderBy('p.payment_date', 'desc')
                ->get();
        }

        // ---------- SUPER ADMIN PAYMENT RECORDS ----------
        $superAdminPaymentRecords = collect();
        if (Schema::hasTable('super_admin_payment_records')) {
            $superAdminPaymentRecords = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->with('superAdmin')
                ->get();
        }

        // ---------- PRIMARY AGREEMENT ----------
        $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
            ->where('is_primary_for_billing', true)
            ->with('superAdmin')
            ->first();

        // ---------- STATS ----------
        $stats = [
            'total_agreements'         => $agreements->count(),
            'active_agreements'        => $agreements->where('status', 'active')->count(),
            'completed_agreements'     => $agreements->where('status', 'completed')->count(),
            'total_amount_agreed'      => $agreements->sum('amount'),
            'total_amount_received'    => $agreements->sum('amount_received'),
            'total_payments'           => $payments->count(),
            'total_payment_amount'     => $payments->sum('amount_paid'),
            'pending_payments'         => $agreements->where('status', 'active')->sum('amount')
                - $agreements->where('status', 'active')->sum('amount_received'),
            'has_primary'              => $primaryAgreement ? true : false,
            'primary_super_admin'      => $primaryAgreement
                ? ($primaryAgreement->billing_contact_name
                    ?? $primaryAgreement->superAdmin->name
                    ?? 'Not assigned')
                : 'Not assigned',
            'collection_rate'          => $agreements->sum('amount') > 0
                ? round(($agreements->sum('amount_received') / $agreements->sum('amount')) * 100, 2)
                : 0,
        ];

        // ---------- BREAKDOWNS ----------
        $monthlyBreakdown       = collect($this->getMonthlyBreakdown($developerSettings->id, $startDate, $endDate));
        $paymentMethodBreakdown = $this->getPaymentMethodBreakdown($developerSettings->id, $startDate, $endDate);

        // ---------- RENDER PDF ----------
        $html = view('developer.billing.exports.report-pdf', [
            'settings'                => $developerSettings,
            'stats'                   => $stats,
            'agreements'              => $agreements,
            'payments'                => $payments,
            'superAdminPaymentRecords'=> $superAdminPaymentRecords,
            'monthlyBreakdown'        => $monthlyBreakdown,
            'paymentMethodBreakdown'  => $paymentMethodBreakdown,
            'primaryAgreement'        => $primaryAgreement,
            'startDate'               => $startDate,
            'endDate'                 => $endDate,
            'reportType'              => $reportType,
            'generatedAt'             => now(),
            'generatedBy'             => $user,
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $filename = 'billing-report-' . $startDate . '-to-' . $endDate . '.pdf';

        $this->logAudit('billing_report_pdf_exported', 'Billing report exported as PDF', [
            'user_id'      => $user->id,
            'report_type'  => $reportType,
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'agreements'   => $agreements->count(),
            'payments'     => $payments->count(),
        ]);

        return $pdf->download($filename);

    } catch (\Exception $e) {
        Log::error('Failed to export billing report as PDF', [
            'error'   => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->back()
            ->with('error', 'Failed to export PDF: ' . $e->getMessage());
    }
}

    /* ============================================================
     | NOTIFICATIONS / AJAX HELPERS
     * ============================================================ */

    public function getAgreementDetails($agreementId)
    {
        try {
            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting'])->findOrFail($agreementId);
            $paymentMethodDetails = $this->getPaymentMethodDetails($agreement);

            return response()->json([
                'success'   => true,
                'agreement' => [
                    'id'                   => $agreement->id,
                    'agreement_number'     => $agreement->agreement_number,
                    'amount'               => $agreement->amount,
                    'currency'             => $agreement->currency,
                    'description'          => $agreement->description,
                    'status'               => $agreement->status,
                    'payment_status'       => $agreement->payment_status,
                    'notes'                => $agreement->notes,
                    'payment_method'       => $agreement->payment_method,
                    'payment_method_label' => $this->getPaymentMethodLabel($agreement->payment_method ?? 'bank_transfer'),
                    'payment_details'      => $paymentMethodDetails['display_text'],
                    'is_primary'           => $agreement->is_primary_for_billing ?? false,
                    'super_admin'          => $agreement->superAdmin ? [
                        'name'  => $agreement->superAdmin->name,
                        'email' => $agreement->superAdmin->email,
                    ] : null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Agreement not found'], 404);
        }
    }

    public function getPaymentDetails($paymentId)
    {
        try {
            $payment = DB::table('agreement_payments')->where('id', $paymentId)->first();

            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
            }

            return response()->json([
                'success' => true,
                'payment' => [
                    'id'                    => $payment->id,
                    'amount_paid'           => $payment->amount_paid,
                    'payment_date'          => $payment->payment_date,
                    'payment_method'        => $payment->payment_method,
                    'status'                => $payment->status,
                    'transaction_reference' => $payment->transaction_reference,
                    'notes'                 => $payment->notes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error loading payment details'], 500);
        }
    }

    public function getPaymentDetailsApi($paymentId) { return $this->getPaymentDetails($paymentId); }

    public function getUnreadNotificationsCount()
    {
        try {
            $count = auth()->user()->unreadNotifications()->count();
            return response()->json(['unread_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['unread_count' => 0]);
        }
    }

    public function getRecentNotifications(Request $request)
    {
        try {
            $limit = $request->get('limit', 8);
            $notifications = auth()->user()
                ->notifications()
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($notification) {
                    $data = $notification->data;
                    return [
                        'id'         => $notification->id,
                        'title'      => $data['title'] ?? 'Notification',
                        'message'    => $data['message'] ?? '',
                        'icon'       => $data['icon'] ?? 'fas fa-bell',
                        'category'   => $data['category'] ?? 'general',
                        'priority'   => $data['priority'] ?? 1,
                        'action_url' => $data['action_url'] ?? null,
                        'is_unread'  => is_null($notification->read_at),
                        'time_ago'   => $notification->created_at->diffForHumans(),
                        'created_at' => $notification->created_at->toDateTimeString(),
                    ];
                });

            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /* ============================================================
     | PDF / DOWNLOAD
     * ============================================================ */

    public function generateAgreementPdf(Request $request, $agreementId)
    {
        if (ob_get_level()) { ob_end_clean(); }

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting'])->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $agreementData = $this->prepareAgreementData($agreement);
            $pdfPath = $this->generateSimpleAgreementPdf($agreementData, $agreement->id);

            $agreement->update([
                'agreement_pdf_path'      => $pdfPath,
                'agreement_generated_at'  => now(),
                'agreement_generated_by'  => $user->id,
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'PDF generated successfully!', 'path' => $pdfPath]);
            }

            return redirect()->back()->with('success', 'PDF generated successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to generate agreement PDF', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function downloadAgreement($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if (!$agreement->agreement_pdf_path || !Storage::exists($agreement->agreement_pdf_path)) {
                $agreementData = $this->prepareAgreementData($agreement);
                $pdfPath = $this->generateSimpleAgreementPdf($agreementData, $agreement->id);
                $agreement->update(['agreement_pdf_path' => $pdfPath]);
            }

            return $this->downloadPdfResponse($agreement->agreement_pdf_path, "Agreement-{$agreement->agreement_number}.pdf");

        } catch (\Exception $e) {
            Log::error('Failed to download agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to download agreement: ' . $e->getMessage());
        }
    }

    public function downloadSignedAgreement($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'active') {
                throw new \Exception('Agreement is not active yet');
            }

            $needsRegen = true;
if ($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path)) {
    $needsRegen = !$this->isValidPdf(Storage::path($agreement->signed_agreement_pdf_path));
}

if ($needsRegen) {
    if ($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path)) {
        Storage::delete($agreement->signed_agreement_pdf_path);
    }

    $this->generateFinalSignedAgreementPdf($agreementId);
    $agreement->refresh();
}

if (!$agreement->signed_agreement_pdf_path || !Storage::exists($agreement->signed_agreement_pdf_path)) {
    throw new \Exception('Signed agreement PDF not found. Please regenerate.');
}

            $this->logAudit('signed_agreement_downloaded', 'Signed agreement downloaded', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'is_primary'       => $agreement->is_primary_for_billing
            ]);

            return $this->downloadPdfResponse(
                $agreement->signed_agreement_pdf_path,
                "Signed-Agreement-{$agreement->agreement_number}.pdf"
            );

        } catch (\Exception $e) {
            Log::error('Failed to download signed agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to download signed agreement: ' . $e->getMessage());
        }
    }

    public function generateFinalAgreementPdf($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'active') {
                throw new \Exception('Can only generate final PDF for active agreements.');
            }

            $pdfPath = $this->generateFinalSignedAgreementPdf($agreementId);

            if (!$pdfPath) {
                throw new \Exception('Failed to generate final agreement PDF.');
            }

            $this->logAudit('final_agreement_pdf_generated', 'Final signed agreement PDF generated', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number
            ]);

            return $this->downloadPdfResponse($pdfPath, "Final-Agreement-{$agreement->agreement_number}.pdf");

        } catch (\Exception $e) {
            Log::error('Failed to generate final agreement PDF', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to generate final PDF: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | TRASH / SOFT DELETE
     * ============================================================ */

    public function deleteAgreement($agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::withTrashed()->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->trashed()) {
                throw new \Exception('Agreement is already deleted.');
            }

            $canDelete = in_array($agreement->status, ['pending', 'terminated', 'cancelled']) && $agreement->amount_received == 0;

            if (!$canDelete) {
                throw new \Exception('Cannot delete agreement. Only pending or terminated agreements with no payments can be deleted.');
            }

            if (Schema::hasTable('agreement_payments')) {
                DB::table('agreement_payments')->where('admin_billing_record_id', $agreementId)->delete();
            }

            if (Schema::hasTable('agreement_signatures')) {
                DB::table('agreement_signatures')->where('agreement_id', $agreementId)->delete();
            }

            if ($agreement->agreement_pdf_path && Storage::exists($agreement->agreement_pdf_path)) {
                Storage::delete($agreement->agreement_pdf_path);
            }

            if ($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path)) {
                Storage::delete($agreement->signed_agreement_pdf_path);
            }

            $agreement->delete();

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget("agreement_{$agreementId}_details");

            $this->logAudit('agreement_deleted', 'Billing agreement soft deleted', [
                'agreement_id'         => $agreementId,
                'agreement_number'     => $agreement->agreement_number,
                'was_primary'          => $agreement->is_primary_for_billing,
                'status_before_delete' => $agreement->status
            ]);

            return redirect()->route('developer.billing.agreements-list')
                ->with('success', 'Agreement deleted successfully. You can restore it from the trash if needed.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage(),
                'user_id'      => auth()->id()
            ]);

            return redirect()->back()->with('error', 'Failed to delete agreement: ' . $e->getMessage());
        }
    }

    public function forceDeleteAgreement($agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::withTrashed()->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->amount_received > 0) {
                throw new \Exception('Cannot permanently delete agreement with payment history. Consider keeping it for audit purposes.');
            }

            if (Schema::hasTable('agreement_payments')) {
                DB::table('agreement_payments')->where('admin_billing_record_id', $agreementId)->delete();
            }

            if (Schema::hasTable('agreement_signatures')) {
                DB::table('agreement_signatures')->where('agreement_id', $agreementId)->delete();
            }

            if ($agreement->agreement_pdf_path && Storage::exists($agreement->agreement_pdf_path)) {
                Storage::delete($agreement->agreement_pdf_path);
            }

            if ($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path)) {
                Storage::delete($agreement->signed_agreement_pdf_path);
            }

            $agreement->forceDelete();

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget("agreement_{$agreementId}_details");

            $this->logAudit('agreement_force_deleted', 'Billing agreement permanently deleted', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'was_primary'      => $agreement->is_primary_for_billing
            ]);

            return redirect()->route('developer.billing.agreements-trashed')
                ->with('success', 'Agreement permanently deleted.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to force delete agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage(),
                'user_id'      => auth()->id()
            ]);

            return redirect()->back()->with('error', 'Failed to permanently delete agreement: ' . $e->getMessage());
        }
    }

    public function restoreAgreement($agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::withTrashed()->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if (!$agreement->trashed()) {
                throw new \Exception('Agreement is not deleted.');
            }

            $agreement->restore();

            $this->logAudit('agreement_restored', 'Billing agreement restored from trash', [
                'agreement_id'    => $agreementId,
                'agreement_number'=> $agreement->agreement_number,
                'was_primary'     => $agreement->is_primary_for_billing,
                'original_status' => $agreement->status
            ]);

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");
            Cache::forget("agreement_{$agreementId}_details");

            return redirect()->route('developer.billing.agreements-trashed')
                ->with('success', 'Agreement restored successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to restore agreement', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage(),
                'user_id'      => auth()->id()
            ]);

            return redirect()->back()->with('error', 'Failed to restore agreement: ' . $e->getMessage());
        }
    }

    public function trashedAgreements(Request $request)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $developerSettings = $this->getOrInitializeDeveloperSettings();

            $query = AdminBillingRecord::onlyTrashed()
                ->where('developer_setting_id', $developerSettings->id)
                ->with('superAdmin');

            if ($request->filled('search')) {
                $searchTerm = '%' . $this->sanitizeInput($request->search) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('agreement_number', 'LIKE', $searchTerm)
                      ->orWhere('description', 'LIKE', $searchTerm)
                      ->orWhereHas('superAdmin', function ($q) use ($searchTerm) {
                          $q->where('name', 'LIKE', $searchTerm)
                            ->orWhere('email', 'LIKE', $searchTerm);
                      });
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $agreements = $query->orderBy('deleted_at', 'desc')
                ->paginate($request->get('per_page', 20))
                ->withQueryString();

            $stats = [
                'total_deleted'    => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->count(),
                'total_restorable' => $agreements->count(),
                'deleted_by_status' => [
                    'pending'    => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->where('status', 'pending')->count(),
                    'terminated' => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->where('status', 'terminated')->count(),
                    'cancelled'  => AdminBillingRecord::onlyTrashed()->where('developer_setting_id', $developerSettings->id)->where('status', 'cancelled')->count(),
                ]
            ];

            return view('developer.billing.trashed-agreements', compact('agreements', 'stats'));

        } catch (\Exception $e) {
            Log::error('Failed to load trashed agreements', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return redirect()->route('developer.billing.agreements-list')
                ->with('error', 'Error loading trashed agreements: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | LEGACY / COMPAT
     * ============================================================ */

    public function developerDashboard() { return $this->dashboard(); }
    public function viewAgreements(Request $request) { return $this->agreementsList($request); }
    public function updateBillingSettings(UpdateBillingSettingsRequest $request) { return $this->updateBilling($request); }
    public function updatePaymentSettings(UpdateBillingSettingsRequest $request) { return $this->updateBilling($request); }

    public function cancelBillingProposal($proposalId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $proposal = \App\Models\BillingProposal::findOrFail($proposalId);
            $developerSettings = $this->getOrInitializeDeveloperSettings();

            if ($proposal->developer_setting_id !== $developerSettings->id) {
                return redirect()->back()->with('error', 'Unauthorized to cancel this proposal');
            }

            if (!in_array($proposal->status, ['pending', 'under_review'])) {
                return redirect()->back()->with('error', 'Can only cancel pending proposals');
            }

            $proposal->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);

            $this->logAudit('billing_proposal_cancelled', 'Legacy billing proposal cancelled', [
                'proposal_id' => $proposalId
            ]);

            return redirect()->route('developer.settings.index', ['section' => 'billing'])
                ->with('success', 'Billing proposal cancelled successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to cancel billing proposal', [
                'proposal_id' => $proposalId,
                'error'       => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to cancel proposal: ' . $e->getMessage());
        }
    }

    public function viewAgreementForSigning($agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting', 'signatures'])
                ->findOrFail($agreementId);

            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'pending') {
                return redirect()->route('developer.billing.view-agreement', $agreementId)
                    ->with('warning', 'This agreement cannot be signed as it is not in pending status.');
            }

            if (!$agreement->agreement_pdf_path || !Storage::exists($agreement->agreement_pdf_path)) {
                return redirect()->route('developer.billing.view-agreement', $agreementId)
                    ->with('error', 'Agreement PDF not found. Please generate the agreement first.');
            }

            $pdfUrl = Storage::url($agreement->agreement_pdf_path);
            $existingSignature = $agreement->signatures()
                ->where('user_id', $user->id)
                ->where('signature_type', 'developer')
                ->first();

            $paymentMethodDetails = $this->getPaymentMethodDetails($agreement);

            $agreementData = [
                'agreement_number'  => $agreement->agreement_number,
                'developer_signed'  => $existingSignature !== null,
                'has_signed'        => $existingSignature !== null,
                'is_primary'        => $agreement->is_primary_for_billing ?? false,
                'signature_status'  => [
                    'all_signed'         => false,
                    'developer_signed'   => $existingSignature !== null,
                    'super_admin_signed' => $agreement->signatures()->where('signature_type', 'super_admin')->exists()
                ],
                'agreement_terms'   => [
                    'currency'          => $agreement->currency,
                    'amount'            => number_format($agreement->amount, 2),
                    'billing_frequency' => ucfirst($agreement->billing_frequency),
                    'payment_details'   => $paymentMethodDetails['display_text'] ?? ucfirst(str_replace('_', ' ', $agreement->payment_method ?? 'Bank Transfer'))
                ],
                'effective_date'    => Carbon::parse($agreement->start_date)->format('F j, Y'),
                'developer_info'    => [
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'phone'   => $user->phone,
                    'company' => $agreement->developerSetting->developer_name ?? 'Development Company'
                ],
                'super_admin_info'  => [
                    'name'  => $agreement->superAdmin->name ?? 'N/A',
                    'email' => $agreement->superAdmin->email ?? 'N/A',
                    'phone' => $agreement->superAdmin->phone ?? 'N/A'
                ]
            ];

            $signatureData = [
                'agreement'          => $agreement,
                'pdf_url'            => $pdfUrl,
                'existingSignature'  => $existingSignature,
                'existingSignatures' => $agreement->signatures()->with('user')->get()->map(function ($sig) {
                    return [
                        'id'             => $sig->id,
                        'user_name'      => $sig->user->name ?? 'Unknown',
                        'user_type'      => $sig->signature_type === 'developer' ? 'Developer' : 'Super Admin',
                        'signature_name' => $sig->signature_name,
                        'signature_date' => $sig->signature_date->format('F j, Y g:i A'),
                        'ip_address'     => $sig->ip_address,
                    ];
                }),
                'agreementData'      => $agreementData,
                'developer_name'     => $user->name,
                'developer_email'    => $user->email,
                'pageTitle'          => 'Sign Agreement - ' . $agreement->agreement_number,
                'hasSigned'          => $existingSignature !== null,
                'signature_id'       => $existingSignature ? $existingSignature->id : null,
            ];

            $this->logAudit('view_agreement_for_signing', 'Developer viewed agreement for signing', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'is_primary'       => $agreement->is_primary_for_billing
            ]);

            return view('developer.billing.sign-agreement', $signatureData);

        } catch (\Exception $e) {
            Log::error('Failed to view agreement for signing', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString()
            ]);

            return redirect()->route('developer.billing.dashboard')
                ->with('error', 'Error loading agreement for signing: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | HELPERS
     * ============================================================ */

    protected function preparePaymentDetails(array $data): array
    {
        $details = [];

        switch ($data['payment_method'] ?? 'bank_transfer') {
            case 'bank_transfer':
                $details = [
                    'account_name'   => $data['payment_account_name']   ?? null,
                    'account_number' => $data['payment_account_number'] ?? null,
                    'bank_name'      => $data['payment_bank_name']      ?? null,
                    'bank_branch'    => $data['payment_bank_branch']    ?? null,
                ];
                break;

            case 'mobile_money':
                $details = [
                    'mobile_number'  => $data['payment_mobile_number']  ?? null,
                    'mobile_network' => $data['mobile_money_network']   ?? null,
                    'account_name'   => $data['payment_account_name_mm'] ?? $data['payment_account_name'] ?? null,
                ];
                break;

            case 'cash':
            case 'check':
            default:
                $details = [
                    'account_name' => $data['payment_account_name'] ?? null,
                ];
                break;
        }

        return $details;
    }

    protected function getPaymentMethodDetails($agreement): array
    {
        $details = [
            'method'       => $agreement->payment_method ?? 'bank_transfer',
            'method_label' => $this->getPaymentMethodLabel($agreement->payment_method ?? 'bank_transfer'),
            'display_text' => '',
        ];

        switch ($agreement->payment_method) {
            case 'bank_transfer':
                $details['display_text'] = sprintf(
                    '%s - %s (%s)',
                    $agreement->payment_bank_name ?? 'Bank',
                    $agreement->payment_account_number ?? 'N/A',
                    $agreement->payment_account_name ?? 'N/A'
                );
                $details['fields'] = [
                    'account_name'   => $agreement->payment_account_name,
                    'account_number' => $agreement->payment_account_number,
                    'bank_name'      => $agreement->payment_bank_name,
                    'bank_branch'    => $agreement->payment_bank_branch,
                ];
                break;

            case 'mobile_money':
                $details['display_text'] = sprintf(
                    '%s - %s (%s)',
                    ucfirst($agreement->payment_mobile_network ?? 'Mobile Money'),
                    $agreement->payment_mobile_number ?? 'N/A',
                    $agreement->payment_account_name ?? 'N/A'
                );
                $details['fields'] = [
                    'mobile_number'  => $agreement->payment_mobile_number,
                    'mobile_network' => $agreement->payment_mobile_network,
                    'account_name'   => $agreement->payment_account_name,
                ];
                break;

            case 'cash':
                $details['display_text'] = 'Cash Payment';
                $details['fields'] = [];
                break;

            case 'check':
                $details['display_text'] = 'Check Payment';
                $details['fields'] = [
                    'payable_to' => $agreement->payment_account_name ?? 'Developer',
                ];
                break;

            default:
                $details['display_text'] = 'Standard Payment';
                $details['fields'] = [];
                break;
        }

        return $details;
    }

    protected function getPaymentMethodLabel($method): string
    {
        $methods = [
            'bank_transfer' => 'Bank Transfer',
            'mobile_money'  => 'Mobile Money',
            'cash'          => 'Cash',
            'check'         => 'Check',
        ];

        return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    protected function getOrInitializeDeveloperSettings()
    {
        $cacheKey = 'developer_settings_' . auth()->id();

        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            $settings = DeveloperSetting::first();

            if (!$settings) {
                $settings = $this->initializeDeveloperSettings();
            }

            return $settings;
        });
    }

    private function initializeDeveloperSettings()
{
    $defaultBillingRules = [
        'auto_generate_invoices' => true,
        'invoice_due_days'       => 30,
        'send_payment_reminders' => true,
        'reminder_days_before'   => [7, 3, 1],
    ];

    // ✅ FIX: pass the array directly — the model's 'array' cast encodes it.
    return DeveloperSetting::create([
        'payment_method'         => 'bank_transfer',
        'billing_currency'       => 'GHS',
        'monthly_billing_amount' => 0,
        'billing_cycle'          => 'monthly',
        'billing_start_date'     => now(),
        'next_billing_date'      => now()->addMonth(),
        'billing_status'         => 'active',
        'billing_rules'          => $defaultBillingRules,
        'created_at'             => now(),
        'updated_at'             => now()
    ]);
}

    protected function authorizeDeveloperAccess($user): void
    {
        if ($user->type !== User::TYPE_DEVELOPER) {
            abort(403, 'Unauthorized: Developer access required');
        }
    }

    protected function authorizeAgreementAccess($agreement): void
    {
        $developerSettings = $this->getOrInitializeDeveloperSettings();

        if ($agreement->developer_setting_id !== $developerSettings->id) {
            abort(403, 'Unauthorized: Agreement does not belong to this developer');
        }
    }

    protected function authorizeAgreementUpdate($agreement): void
    {
        if (!in_array($agreement->status, ['active', 'pending'])) {
            throw new \Exception('Can only update active or pending agreements');
        }
    }

    protected function authorizeAgreementTermination($agreement): void
    {
        if ($agreement->status !== 'active') {
            throw new \Exception('Can only terminate active agreements');
        }
    }

    protected function authorizeAgreementSigning($agreement): void
    {
        if ($agreement->status !== 'pending') {
            throw new \Exception('Agreement is not in pending status');
        }

        if ($agreement->signed_agreement_pdf_path) {
            throw new \Exception('Agreement has already been signed');
        }
    }

    protected function applyAgreementFilters($query, Request $request): void
    {
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('frequency') && $request->frequency !== 'all') {
            $query->where('billing_frequency', $request->frequency);
        }

        if ($request->filled('primary_only') && $request->primary_only === 'true') {
            $query->where('is_primary_for_billing', true);
        }

        if ($request->filled('date_range')) {
            $this->applyDateRangeFilter($query, $request);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . $this->sanitizeInput($request->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('agreement_number', 'LIKE', $searchTerm)
                  ->orWhere('description', 'LIKE', $searchTerm)
                  ->orWhereHas('superAdmin', function ($q) use ($searchTerm) {
                      $q->where('name', 'LIKE', $searchTerm)
                        ->orWhere('email', 'LIKE', $searchTerm);
                  });
            });
        }
    }

    protected function applyDateRangeFilter($query, Request $request): void
    {
        $now = now();

        switch ($request->date_range) {
            case 'today':
                $query->whereDate('created_at', $now->toDateString());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [$now->startOfWeek(), $now->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                break;
            case 'last_month':
                $lastMonth = $now->subMonth();
                $query->whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year);
                break;
            case 'this_year':
                $query->whereYear('created_at', $now->year);
                break;
            case 'custom':
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($request->start_date)->startOfDay(),
                        Carbon::parse($request->end_date)->endOfDay()
                    ]);
                }
                break;
        }
    }

    protected function validateDateRange(Request $request): array
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $validator = Validator::make(
            ['start_date' => $startDate, 'end_date' => $endDate],
            [
                'start_date' => 'required|date',
                'end_date'   => 'required|date|after_or_equal:start_date|before_or_equal:today'
            ]
        );

        if ($validator->fails()) {
            throw new \Exception('Invalid date range: ' . $validator->errors()->first());
        }

        return ['start_date' => $startDate, 'end_date' => $endDate];
    }

    protected function canRevokeSignatures($agreement): bool
    {
        if ($agreement->status === 'completed') {
            return false;
        }

        if ($agreement->signing_completed_at) {
            $daysSinceSigning = now()->diffInDays($agreement->signing_completed_at);
            if ($daysSinceSigning > 7) {
                return false;
            }
        }

        if ($agreement->amount_received > 0) {
            return false;
        }

        return true;
    }

    protected function renderErrorDashboard($e): \Illuminate\View\View
    {
        $paymentMethods = [];
        try {
            $paymentMethods = $this->getPaymentMethodsConfiguration();
        } catch (\Exception $ex) {
            $paymentMethods = [
                'bank_transfer' => ['name' => 'Bank Transfer', 'icon' => 'fa-university', 'fields' => [], 'description' => 'Bank transfer'],
                'mobile_money'  => ['name' => 'Mobile Money', 'icon' => 'fa-mobile-alt', 'fields' => [], 'description' => 'Mobile money'],
                'cash'          => ['name' => 'Cash', 'icon' => 'fa-money-bill', 'fields' => [], 'description' => 'Cash payment'],
                'check'         => ['name' => 'Check', 'icon' => 'fa-file-invoice', 'fields' => [], 'description' => 'Check payment'],
            ];
        }

        $superAdmins = collect();
        try {
            $superAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'email', 'phone']);
        } catch (\Exception $ex) {}

        $developerSettings = null;
        try {
            $developerSettings = $this->getOrInitializeDeveloperSettings();
        } catch (\Exception $ex) {}

        return view('developer.billing.dashboard', [
            'activeAgreements'     => collect(),
            'pendingAgreements'    => collect(),
            'completedAgreements'  => collect(),
            'awaitingSignature'    => collect(),
            'recentlySigned'       => collect(),
            'pendingPayments'      => collect(),
            'superAdmins'          => $superAdmins,
            'primarySuperAdmin'    => null,
            'primaryBillingContact'=> null,
            'billingProposals'     => collect(),
            'superAdminRequests'   => collect(),
            'recentInvoices'       => collect(),
            'stats' => [
                'total_active_agreements'    => 0,
                'total_pending_agreements'   => 0,
                'total_completed_agreements' => 0,
                'total_amount_agreed'        => 0,
                'total_amount_received'      => 0,
                'total_pending_payments'     => 0,
                'pending_payment_amount'     => 0,
                'pending_proposals'          => 0,
                'pending_sa_requests'        => 0,
                'awaiting_signature'         => 0,
                'recently_signed'            => 0,
                'total_agreements'           => 0,
                'has_primary_super_admin'    => false,
                'primary_super_admin_name'   => 'Not assigned',
                'primary_billing_contact'    => null,
            ],
            'billingConfig'   => [],
            'paymentMethods'  => $paymentMethods,
            'developerSettings' => $developerSettings,
            'warning' => 'Some billing data could not be loaded. Error: ' . $e->getMessage()
        ]);
    }

    protected function downloadPdfResponse(string $path, string $filename)
    {
        $fullPath = storage_path('app/' . $path);

        if (!file_exists($fullPath)) {
            throw new \Exception('PDF file not found');
        }

        if (!str_ends_with($filename, '.pdf')) {
            $filename .= '.pdf';
        }

        return response()->download($fullPath, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'private, max-age=3600',
            'Pragma'              => 'public',
        ])->deleteFileAfterSend(false);
    }

    protected function getPaymentMethodsConfiguration(): array
    {
        return [
            'bank_transfer' => [
                'name'        => 'Bank Transfer',
                'icon'        => 'fa-university',
                'fields'      => ['account_name', 'account_number', 'bank_name', 'bank_branch'],
                'description' => 'Direct bank transfer to developer account'
            ],
            'mobile_money' => [
                'name'        => 'Mobile Money',
                'icon'        => 'fa-mobile-alt',
                'fields'      => ['mobile_number', 'mobile_network', 'account_name'],
                'description' => 'Mobile money payment via MTN, Vodafone, or AirtelTigo'
            ],
            'cash' => [
                'name'        => 'Cash',
                'icon'        => 'fa-money-bill',
                'fields'      => [],
                'description' => 'Cash payment in person'
            ],
            'check' => [
                'name'        => 'Check',
                'icon'        => 'fa-file-invoice',
                'fields'      => ['account_name'],
                'description' => 'Check payment made payable to developer'
            ],
        ];
    }

    private function getMonthlyBreakdown($developerId, $startDate, $endDate)
{
    $breakdown = collect();   // ✅ start as Collection, not []

    $current = Carbon::parse($startDate);
    $end = Carbon::parse($endDate);

    while ($current <= $end) {
        $monthStart = $current->copy()->startOfMonth();
        $monthEnd = $current->copy()->endOfMonth();

        $payments = 0;

        if (Schema::hasTable('super_admin_payment_records')) {
            $payments = SuperAdminPaymentRecord::where('developer_setting_id', $developerId)
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount_paid');
        }

        $breakdown->push([              // ✅ push to Collection
            'month'          => $current->format('F Y'),
            'total_payments' => (float) $payments
        ]);

        $current->addMonth();
    }

    return $breakdown;
}

    private function getPaymentMethodBreakdown($developerId, $startDate, $endDate)
    {
        $breakdown = collect();

        if (Schema::hasTable('super_admin_payment_records')) {
            $breakdown = SuperAdminPaymentRecord::where('developer_setting_id', $developerId)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_paid) as total_amount'))
                ->groupBy('payment_method')
                ->get()
                ->map(function ($item) {
                    $methods = $this->getPaymentMethodsConfiguration();
                    $methodName = $methods[$item->payment_method]['name'] ?? ucfirst($item->payment_method);

                    return [
                        'payment_method' => $methodName,
                        'count'          => $item->count,
                        'total_amount'   => $item->total_amount
                    ];
                });
        }

        return $breakdown;
    }

    private function generateFinalSignedAgreementPdf($agreementId)
    {
        try {
            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting', 'signatures.user'])
                ->findOrFail($agreementId);

            $signatures = $agreement->signatures->map(function ($signature) {
    return [
        'id'               => $signature->id,
        'user_id'          => $signature->user_id,
        'user_name'        => $signature->user->name ?? 'Unknown',
        'user_email'       => $signature->user->email ?? null,
        'user_type'        => $signature->signature_type === 'developer' ? 'Developer' : 'Super Admin',
        'signature_type'   => $signature->signature_type,
        'signature_name'   => $signature->signature_name,
        'signature_data'   => $signature->signature_data,           // ← ADDED
        'signature_date'   => $signature->signature_date
            ? $signature->signature_date->format('F j, Y')
            : null,
        'signature_path'   => $signature->signature_path,
        'signature_format' => $signature->signature_format
            ?? $signature->signature_type
            ?? 'typed',
        'digital_hash'     => $signature->digital_hash,
        'status'           => $signature->status,
        'ip_address'       => $signature->ip_address,
    ];
});

            $pdfData = [
                'agreement'           => $agreement,
                'signatures'          => $signatures,
                'signed_date'         => now()->format('F j, Y g:i A'),
                'agreement_number'    => $agreement->agreement_number,
                'amount'              => floatval(preg_replace('/[^0-9.-]/', '', $agreement->amount)),
                'currency'            => $agreement->currency,
                'description'         => $agreement->description,
                'start_date'          => $agreement->start_date,
                'billing_frequency'   => $agreement->billing_frequency,
                'is_primary'          => $agreement->is_primary_for_billing ?? false,
                'agreement_terms'     => [
                    'amount'            => floatval(preg_replace('/[^0-9.-]/', '', $agreement->amount)),
                    'currency'          => $agreement->currency,
                    'description'       => $agreement->description,
                    'billing_frequency' => $agreement->billing_frequency,
                    'payment_details'   => $this->getPaymentMethodDetails($agreement)['display_text'] ?? $agreement->payment_method ?? 'Bank Transfer',
                ],
                'agreement_date'      => $agreement->created_at->format('F j, Y'),
                'signing_completed_at'=> $agreement->signing_completed_at?->format('F j, Y g:i A') ?? now()->format('F j, Y g:i A'),
                'generated_at'        => now()->format('F j, Y g:i A'),
            ];

            $path = $this->generateSimpleSignedAgreementPdf($pdfData, $agreementId);
            $agreement->update(['signed_agreement_pdf_path' => $path]);

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to generate final signed agreement PDF: ' . $e->getMessage());
            return null;
        }
    }

    private function generateSimpleAgreementPdf($agreementData, $agreementId)
    {
        try {
            $directory = "agreements/unsigned";

            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory, 0755, true);
            }

            $html = $this->createAgreementHtml($agreementData);
            $pdf = PDF::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');

            $filename = "agreement_{$agreementId}_" . time() . '.pdf';
            $path = "{$directory}/{$filename}";

            Storage::put($path, $pdf->output());

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to generate simple agreement PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateSimpleSignedAgreementPdf($pdfData, $agreementId)
{
    try {
        $directory = "agreements/signed";

        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory, 0755, true);
        }

        if (isset($pdfData['agreement_terms']['amount'])) {
            $amount = $pdfData['agreement_terms']['amount'];
            if (is_string($amount)) {
                $pdfData['agreement_terms']['amount'] = floatval(preg_replace('/[^0-9.-]/', '', $amount));
            }
        }

        $html = $this->createSignedAgreementHtml($pdfData);
        $pdf = PDF::loadHTML($html);                 // ← already correct
        $pdf->setPaper('A4', 'portrait');

        $filename = "signed_agreement_{$agreementId}_" . time() . '.pdf';
        $path = "{$directory}/{$filename}";

        Storage::put($path, $pdf->output());         // ← already correct

        return $path;

    } catch (\Exception $e) {
        Log::error('Failed to generate simple signed agreement PDF: ' . $e->getMessage());
        throw $e;
    }
}

    private function createAgreementHtml($agreementData)
    {
        $paymentDetails = $agreementData['payment_method_details']['display_text']
            ?? ($agreementData['agreement_terms']['payment_details'] ?? 'Bank Transfer');

        $amount = $agreementData['agreement_terms']['amount'] ?? 0;
        if (is_string($amount)) {
            $amount = floatval(preg_replace('/[^0-9.-]/', '', $amount));
        }
        $formattedAmount = number_format($amount, 2);
        $currency = $agreementData['agreement_terms']['currency'] ?? 'GHS';

        $primaryBadge = '';
        if (isset($agreementData['is_primary']) && $agreementData['is_primary']) {
            $primaryBadge = '<div style="background-color: #27ae60; color: white; padding: 5px 10px; border-radius: 5px; display: inline-block; margin-top: 10px;">PRIMARY BILLING CONTACT</div>';
        }

        $billingContactInfo = '';
        if (isset($agreementData['billing_contact']) && $agreementData['billing_contact']['name']) {
            $billingContactInfo = '<div class="info-row">
                    <span class="info-label">Billing Contact:</span>
                    <span>' . htmlspecialchars($agreementData['billing_contact']['name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Billing Email:</span>
                    <span>' . htmlspecialchars($agreementData['billing_contact']['email'] ?? 'N/A') . '</span>
                </div>';
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Agreement ' . htmlspecialchars($agreementData['agreement_number'] ?? 'N/A') . '</title>
            <style>
                body { font-family: "DejaVu Sans", sans-serif; margin: 40px; line-height: 1.6; color: #333; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
                .header h1 { margin: 0; color: #2c3e50; }
                .section { margin-bottom: 25px; }
                .section-title { font-weight: bold; font-size: 18px; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px; color: #2c3e50; }
                .signature-area { margin-top: 60px; border-top: 1px solid #ccc; padding-top: 20px; }
                .signature-line { display: inline-block; width: 250px; border-bottom: 1px solid #000; margin: 10px 20px; }
                .payment-details { background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin-top: 10px; border-left: 4px solid #3498db; }
                .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
                .info-row { margin-bottom: 10px; }
                .info-label { font-weight: bold; width: 150px; display: inline-block; }
                .company-name { font-size: 20px; font-weight: bold; color: #3498db; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">' . htmlspecialchars(config('app.name', 'Billing System')) . '</div>
                <h1>BILLING AGREEMENT</h1>
                <h2>Agreement Number: ' . htmlspecialchars($agreementData['agreement_number'] ?? 'N/A') . '</h2>
                <p>Date: ' . ($agreementData['agreement_date'] ?? date('F j, Y')) . '</p>
                ' . $primaryBadge . '
            </div>

            <div class="section">
                <div class="section-title">1. PARTIES</div>
                <div class="info-row">
                    <span class="info-label">Developer:</span>
                    <span>' . htmlspecialchars($agreementData['developer_info']['name'] ?? 'N/A') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span>' . htmlspecialchars($agreementData['developer_info']['email'] ?? 'N/A') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span>' . htmlspecialchars($agreementData['developer_info']['phone'] ?? 'N/A') . '</span>
                </div>
                <div style="margin-top: 15px;"></div>
                <div class="info-row">
                    <span class="info-label">Super Admin:</span>
                    <span>' . htmlspecialchars($agreementData['super_admin_info']['name'] ?? 'N/A') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span>' . htmlspecialchars($agreementData['super_admin_info']['email'] ?? 'N/A') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span>' . htmlspecialchars($agreementData['super_admin_info']['phone'] ?? 'N/A') . '</span>
                </div>
                ' . $billingContactInfo . '
            </div>

            <div class="section">
                <div class="section-title">2. AGREEMENT TERMS</div>
                <div class="info-row">
                    <span class="info-label">Amount:</span>
                    <span><strong>' . $currency . ' ' . $formattedAmount . '</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Billing Frequency:</span>
                    <span>' . ucfirst($agreementData['agreement_terms']['billing_frequency'] ?? 'monthly') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Start Date:</span>
                    <span>' . ($agreementData['agreement_terms']['start_date'] ?? 'N/A') . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Description:</span>
                    <div style="margin-top: 5px;">' . nl2br(htmlspecialchars($agreementData['agreement_terms']['description'] ?? 'N/A')) . '</div>
                </div>

                <div class="payment-details">
                    <strong>Payment Method:</strong> ' . htmlspecialchars($paymentDetails) . '
                </div>
            </div>

            <div class="section">
                <div class="section-title">3. LEGAL TERMS</div>';

        foreach (($agreementData['legal_terms'] ?? [
            'Governing Law'      => 'This agreement shall be governed by and construed in accordance with the laws of Ghana.',
            'Dispute Resolution' => 'Any dispute arising from this agreement shall be resolved through arbitration.',
            'Termination'        => 'Either party may terminate this agreement with 30 days written notice.',
        ]) as $title => $content) {
            $html .= '<div class="info-row">
                        <strong>' . htmlspecialchars($title) . ':</strong>
                        <div style="margin-top: 5px; margin-bottom: 15px;">' . htmlspecialchars($content) . '</div>
                    </div>';
        }

        $html .= '</div>

            <div class="signature-area">
                <table style="width: 100%; margin-top: 30px;">
                    <tr>
                        <td style="width: 45%; text-align: center;">
                            <div><strong>Developer Signature</strong></div>
                            <div class="signature-line"></div>
                            <div>Date: _________________</div>
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 45%; text-align: center;">
                            <div><strong>Super Admin Signature</strong></div>
                            <div class="signature-line"></div>
                            <div>Date: _________________</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                <p>This is a legally binding electronic agreement.</p>
                <p>Generated on: ' . ($agreementData['generated_at'] ?? date('F j, Y g:i A')) . '</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function createSignedAgreementHtml($pdfData)
    {
        $amount = $pdfData['agreement_terms']['amount'] ?? 0;
        if (is_string($amount)) {
            $amount = floatval(preg_replace('/[^0-9.-]/', '', $amount));
        }
        $formattedAmount = number_format($amount, 2);
        $currency = $pdfData['agreement_terms']['currency'] ?? 'GHS';
        $paymentDetails = $pdfData['agreement_terms']['payment_details'] ?? 'Bank Transfer';

        $primaryText = '';
        if (isset($pdfData['is_primary']) && $pdfData['is_primary']) {
            $primaryText = '<div class="signed-stamp" style="margin-top: 10px;">✓ PRIMARY BILLING CONTACT</div>';
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Signed Agreement ' . htmlspecialchars($pdfData['agreement_number'] ?? 'N/A') . '</title>
            <style>
                body { font-family: "DejaVu Sans", sans-serif; margin: 40px; line-height: 1.6; color: #333; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }
                .header h1 { margin: 0; color: #2c3e50; }
                .signed-stamp { color: #27ae60; font-weight: bold; font-size: 18px; margin-top: 10px; padding: 5px; background-color: #e8f8f5; display: inline-block; border-radius: 5px; }
                .section { margin-bottom: 25px; }
                .section-title { font-weight: bold; font-size: 18px; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px; color: #2c3e50; }
                .signature-area { margin-top: 30px; }
                .signature-box { display: inline-block; width: 45%; margin: 10px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; vertical-align: top; background-color: #f9f9f9; }
                .payment-details { background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin-top: 10px; border-left: 4px solid #27ae60; }
                .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 20px; }
                .info-row { margin-bottom: 10px; }
                .info-label { font-weight: bold; width: 150px; display: inline-block; }
                .verification-hash { font-family: monospace; font-size: 9px; background-color: #f5f5f5; padding: 8px; word-break: break-all; border-radius: 4px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>EXECUTED BILLING AGREEMENT</h1>
                <h2>Agreement Number: ' . htmlspecialchars($pdfData['agreement_number'] ?? 'N/A') . '</h2>
                <p>Date: ' . ($pdfData['agreement_date'] ?? date('F j, Y')) . '</p>
                <div class="signed-stamp">✓ DIGITALLY SIGNED AND EXECUTED</div>
                ' . $primaryText . '
            </div>';

        $html .= '<div class="section">
            <div class="section-title">Digital Verification</div>
            <div class="verification-hash">
                <strong>Verification Hash:</strong> ' . ($pdfData['digital_verification_hash'] ?? 'N/A') . '<br>
                <strong>Signing Completed:</strong> ' . ($pdfData['signing_completed_at'] ?? 'N/A') . '
            </div>
            <p><em>This document has been digitally signed by all parties and is legally binding.</em></p>
        </div>';

        $html .= '<div class="section">
            <div class="section-title">Digital Signatures</div>
            <div class="signature-area">';

        if (isset($pdfData['signatures']) && is_array($pdfData['signatures'])) {
            foreach ($pdfData['signatures'] as $signature) {
                $html .= '<div class="signature-box">
                    <div style="border-bottom: 1px solid #ddd; margin-bottom: 10px; padding-bottom: 5px;">
                        <strong>' . htmlspecialchars($signature['user_type'] ?? 'Unknown') . '</strong>
                    </div>
                    <div><strong>Name:</strong> ' . htmlspecialchars($signature['user_name'] ?? 'Unknown') . '</div>
                    <div><strong>Signed Name:</strong> ' . htmlspecialchars($signature['signature_name'] ?? 'N/A') . '</div>
                    <div><strong>Date Signed:</strong> ' . ($signature['signature_date'] ?? 'N/A') . '</div>
                </div>';
            }
        }

        $html .= '</div></div>';

        $description = $pdfData['agreement_terms']['description'] ?? 'No description provided';
        $billingFrequency = ucfirst($pdfData['agreement_terms']['billing_frequency'] ?? 'monthly');

        $html .= '<div class="section">
            <div class="section-title">Agreement Terms (Original)</div>
            <div class="info-row">
                <span class="info-label">Amount:</span>
                <span><strong>' . $currency . ' ' . $formattedAmount . '</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Description:</span>
                <span>' . nl2br(htmlspecialchars($description)) . '</span>
            </div>
            <div class="info-row">
                <span class="info-label">Billing Frequency:</span>
                <span>' . htmlspecialchars($billingFrequency) . '</span>
            </div>

            <div class="payment-details">
                <strong>Payment Method:</strong> ' . htmlspecialchars($paymentDetails) . '
            </div>

            <div class="info-row" style="margin-top: 15px;">
                <span class="info-label">Signed on:</span>
                <span>' . ($pdfData['signing_completed_at'] ?? date('F j, Y g:i A')) . '</span>
            </div>
        </div>

        <div class="footer">
            <p>This is a legally binding electronic agreement. All signatures have been digitally verified.</p>
            <p>Generated on: ' . ($pdfData['generated_at'] ?? date('F j, Y g:i A')) . '</p>
        </div>
        </body>
        </html>';

        return $html;
    }

    protected function checkSignaturesComplete($agreementId)
    {
        $developerSigned = AgreementSignature::where('agreement_id', $agreementId)
            ->where('signature_type', 'developer')
            ->exists();

        $superAdminSigned = AgreementSignature::where('agreement_id', $agreementId)
            ->where('signature_type', 'super_admin')
            ->exists();

        return [
            'developer_signed'   => $developerSigned,
            'super_admin_signed' => $superAdminSigned,
            'complete'           => ($developerSigned && $superAdminSigned),
        ];
    }

    protected function prepareAgreementData($agreement)
    {
        return [
            'agreement_number' => $agreement->agreement_number,
            'agreement_date'   => $agreement->created_at->format('F j, Y'),
            'generated_at'     => now()->format('F j, Y g:i A'),
            'developer_info'   => [
                'name'  => auth()->user()->name ?? 'Developer',
                'email' => auth()->user()->email ?? '',
                'phone' => auth()->user()->phone ?? '',
            ],
            'super_admin_info' => [
                'name'  => $agreement->superAdmin->name ?? 'N/A',
                'email' => $agreement->superAdmin->email ?? 'N/A',
                'phone' => $agreement->superAdmin->phone ?? 'N/A',
            ],
            'agreement_terms' => [
                'amount'            => $agreement->amount,
                'currency'          => $agreement->currency,
                'description'       => $agreement->description,
                'billing_frequency' => $agreement->billing_frequency,
                'start_date'        => $agreement->start_date ? Carbon::parse($agreement->start_date)->format('F j, Y') : 'N/A',
            ],
            'legal_terms' => [
                'Governing Law'      => 'This agreement shall be governed by and construed in accordance with the laws of Ghana.',
                'Dispute Resolution' => 'Any dispute arising from this agreement shall be resolved through arbitration.',
                'Termination'        => 'Either party may terminate this agreement with 30 days written notice.',
            ],
        ];
    }

    protected function generateAgreementNumber()
    {
        return 'AGR-' . date('Y') . date('m') . '-' . strtoupper(substr(uniqid(), -6));
    }

    protected function calculateDueDateBasedOnFrequency($startDate, $frequency)
    {
        $start = Carbon::parse($startDate);

        switch ($frequency) {
            case 'weekly':    return $start->addDays(7);
            case 'monthly':   return $start->addMonth();
            case 'quarterly': return $start->addMonths(3);
            case 'yearly':    return $start->addYear();
            default:          return $start->addMonth();
        }
    }

    protected function calculateNextBillingDate($startDate, $cycle, $currentNextDate = null)
    {
        $base = $currentNextDate ?? $startDate;

        switch ($cycle) {
            case 'weekly':    return $base->copy()->addWeek();
            case 'monthly':   return $base->copy()->addMonth();
            case 'quarterly': return $base->copy()->addMonths(3);
            case 'yearly':    return $base->copy()->addYear();
            default:          return $base->copy()->addMonth();
        }
    }

    protected function sanitizeInput($input)
    {
        if ($input === null) {
            return null;
        }
        return strip_tags(trim($input));
    }

    protected function getAgreementPaymentSummary($agreement)
    {
        return [
            'total_received'     => $agreement->amount_received,
            'remaining_balance'  => max(0, $agreement->amount - $agreement->amount_received),
            'payment_percentage' => $agreement->amount > 0 ? round(($agreement->amount_received / $agreement->amount) * 100, 2) : 0,
        ];
    }

    /**
     * Schedule InvoiceReminder rows for every configured reminder interval.
     */
    private function scheduleInvoiceReminders(
    BillingInvoice $invoice,
    DeveloperSetting $developerSettings,
    AdminBillingRecord $primaryAgreement
): void {
    if (!Schema::hasTable('invoice_reminders')) {
        Log::warning('invoice_reminders table missing — skipping reminder scheduling.');
        return;
    }

    // ✅ FIX: use the model's safe accessor. Handles the array cast
    // transparently and fills DEFAULT_BILLING_RULES for any missing keys.
    $rules = $developerSettings->getBillingRulesArray();

    if (!($rules['send_payment_reminders'] ?? true)) {
        return;
    }

    $reminderDays = $rules['reminder_days_before'] ?? [7, 3, 1];
    if (!is_array($reminderDays) || empty($reminderDays)) {
        $reminderDays = [7, 3, 1];
    }

    $dueDate = Carbon::parse($invoice->due_date ?? now()->addDays(30));
    $today   = Carbon::today();

    // ✅ FIX: schema-aware column name (channels vs channel).
    $channelColumn = Schema::hasColumn('invoice_reminders', 'channels')
        ? 'channels'
        : 'channel';

    foreach ($reminderDays as $days) {
        $days = (int) $days;
        if ($days <= 0) {
            continue;
        }

        $scheduledFor = $dueDate->copy()->subDays($days);

        if ($scheduledFor->lt($today)) {
            $scheduledFor = $today->copy()->addDay();
        }

        InvoiceReminder::updateOrCreate(
            [
                'invoice_id'      => $invoice->id,
                'days_before_due' => $days,
            ],
            [
                'developer_setting_id' => $developerSettings->id,
                'super_admin_id'       => $primaryAgreement->super_admin_id,
                'scheduled_for'        => $scheduledFor->toDateString(),
                'status'               => 'pending',
                $channelColumn         => 'email',
                'sent_at'              => null,
                'failure_reason'       => null,
            ]
        );
    }
}

    /* ============================================================
     | SIGNATURE CERT / DOWNLOADS / MISC
     * ============================================================ */

    public function downloadSignatureCertificate($signatureId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $signature = AgreementSignature::with('user', 'agreement')->findOrFail($signatureId);

            $certificateData = [
                'signature'         => $signature,
                'user'              => $signature->user,
                'agreement'         => $signature->agreement,
                'verification_hash' => hash('sha256', $signature->signature_name . $signature->signature_date . $signature->ip_address),
                'generated_at'      => now()->format('F j, Y g:i A'),
            ];

            $pdf = PDF::loadView('developer.billing.signature-certificate', $certificateData);
            $filename = "signature-certificate-{$signatureId}.pdf";

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Failed to download signature certificate', [
                'signature_id' => $signatureId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to download certificate: ' . $e->getMessage());
        }
    }

    public function verifySignature($signatureId)
    {
        try {
            $signature = AgreementSignature::with(['user', 'agreement'])->findOrFail($signatureId);

            $verificationData = [
                'signature_name'   => $signature->signature_name,
                'signature_date'   => $signature->signature_date->format('F j, Y g:i A'),
                'user_name'        => $signature->user->name,
                'user_type'        => $signature->signature_type,
                'agreement_number' => $signature->agreement->agreement_number,
                'digital_hash'     => hash('sha256', $signature->signature_name . $signature->signature_date . $signature->ip_address),
                'verified_at'      => now()->format('F j, Y g:i A'),
            ];

            return response()->json([
                'success'           => true,
                'verification'      => $verificationData,
                'verification_hash' => hash('sha256', json_encode($verificationData))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Signature not found or invalid'
            ], 404);
        }
    }

    public function downloadSignature($signatureId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $signature = AgreementSignature::findOrFail($signatureId);

            if ($signature->user_id !== $user->id && $signature->signature_type !== 'developer') {
                abort(403, 'Unauthorized to download this signature');
            }

            if ($signature->signature_format === 'typed') {
                return $this->generateTypedSignatureImage($signature);
            }

            if ($signature->signature_path && Storage::exists($signature->signature_path)) {
                return response()->download(storage_path('app/' . $signature->signature_path));
            }

            return redirect()->back()->with('error', 'Signature file not found');

        } catch (\Exception $e) {
            Log::error('Failed to download signature', [
                'signature_id' => $signatureId,
                'error'        => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Failed to download signature');
        }
    }

    private function generateTypedSignatureImage($signature)
    {
        $width = 400;
        $height = 100;
        $img = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        $textColor = imagecolorallocate($img, 50, 50, 50);

        $fontPaths = [
            public_path('fonts/DancingScript.ttf'),
            public_path('fonts/cursive.ttf'),
            public_path('fonts/GreatVibes.ttf'),
            public_path('fonts/arial.ttf'),
        ];

        $fontPath = null;
        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }

        if ($fontPath && function_exists('imagettftext')) {
            $fontSize = 28;
            $textBox = imagettfbbox($fontSize, 0, $fontPath, $signature->signature_name);
            $textWidth = abs($textBox[4] - $textBox[0]);
            $x = ($width - $textWidth) / 2;
            $y = ($height + $fontSize) / 2;

            imagettftext($img, $fontSize, 0, $x, $y, $textColor, $fontPath, $signature->signature_name);
        } else {
            $fontSize = 5;
            $textWidth = imagefontwidth($fontSize) * strlen($signature->signature_name);
            $x = ($width - $textWidth) / 2;
            $y = ($height - imagefontheight($fontSize)) / 2;

            imagestring($img, $fontSize, $x, $y, $signature->signature_name, $textColor);
        }

        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        return response($imageData)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="signature_' . $signature->id . '.png"');
    }

    public function checkSignatureStatus($agreementId)
    {
        try {
            $agreement = AdminBillingRecord::findOrFail($agreementId);

            $developerSigned = AgreementSignature::where('agreement_id', $agreementId)
                ->where('signature_type', 'developer')
                ->exists();

            $superAdminSigned = AgreementSignature::where('agreement_id', $agreementId)
                ->where('signature_type', 'super_admin')
                ->exists();

            return response()->json([
                'success'            => true,
                'developer_signed'   => $developerSigned,
                'super_admin_signed' => $superAdminSigned,
                'fully_signed'       => ($developerSigned && $superAdminSigned),
                'status'             => $agreement->status,
                'is_primary'         => $agreement->is_primary_for_billing ?? false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking signature status'
            ], 500);
        }
    }

    public function getSigningStatus($agreementId)
    {
        return $this->checkSignatureStatus($agreementId);
    }

    public function sendTestAgreementEmail(Request $request, $agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with('superAdmin')->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $emailType = $request->get('email_type', 'signature_request');

            SendSuperAdminAgreementJob::dispatch($agreement, $emailType, 'TEST EMAIL - Please ignore.');

            $this->logAudit('test_email_sent', 'Test agreement email sent', [
                'agreement_id' => $agreementId,
                'email_type'   => $emailType
            ]);

            return redirect()->back()
                ->with('success', 'Test email sent to ' . $agreement->superAdmin->email);

        } catch (\Exception $e) {
            Log::error('Failed to send test email', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send test email: ' . $e->getMessage());
        }
    }

    public function sendAgreementForSigning(SendAgreementRequest $request, $agreementId)
    {
        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with(['superAdmin'])->findOrFail($agreementId);
            $this->authorizeAgreementSigning($agreement);

            $validatedData = $request->validated();

            if (!$agreement->agreement_pdf_path) {
                GenerateAgreementPdfJob::dispatchSync($agreementId, $user->id);
                $agreement->refresh();
            }

            SendSuperAdminAgreementJob::dispatch(
                $agreement,
                'signature_request',
                $validatedData['message'] ?? ''
            );

            $agreement->update([
                'signing_invitation_sent_at' => now(),
                'signing_invitation_sent_by' => $user->id,
            ]);

            $this->logAudit('agreement_signing_sent', 'Signing invitation sent', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'is_primary'       => $agreement->is_primary_for_billing,
                'sent_to'          => $agreement->superAdmin->email
            ]);

            return redirect()->route('developer.billing.view-agreement', $agreementId)
                ->with('success', 'Signing invitation sent successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to send agreement for signing', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send agreement: ' . $e->getMessage());
        }
    }

    public function sendInvitation(Request $request, $agreementId)
    {
        return $this->sendAgreementForSigning($request, $agreementId);
    }

    public function sendReminder(SendReminderRequest $request, $agreementId)
    {
        if (ob_get_level()) { ob_end_clean(); }

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with('superAdmin')->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $validatedData = $request->validated();

            $this->logAudit('payment_reminder_sent', 'Payment reminder sent to super admin', [
                'agreement_id'      => $agreementId,
                'super_admin_email' => $agreement->superAdmin->email,
                'reminder_type'     => $validatedData['reminder_type']
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Reminder sent successfully to ' . $agreement->superAdmin->email
                ]);
            }

            return redirect()->back()->with('success', 'Reminder sent successfully to ' . $agreement->superAdmin->email);

        } catch (\Exception $e) {
            Log::error('Failed to send reminder', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to send reminder: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }
    }

    public function sendInvoice(Request $request, $agreementId)
    {
        if (ob_get_level()) { ob_end_clean(); }

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::with('superAdmin')->findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            $invoice = BillingInvoice::where('developer_setting_id', $agreement->developer_setting_id)
                ->where('billing_month', Carbon::now()->format('Y-m'))
                ->first();

            if (!$invoice) {
                $developerSettings = $this->getOrInitializeDeveloperSettings();
                $invoice = $this->generateMonthlyInvoiceInternal($developerSettings);
            }

            $email = $agreement->billing_contact_email ?? $agreement->superAdmin->email;

            if (!$email) {
                throw new \Exception('No email address found for super admin');
            }

            SendInvoiceEmailJob::dispatch($invoice, $email);

            $invoice->update([
                'sent_at' => now(),
                'sent_to' => $email,
            ]);

            $this->logAudit('invoice_sent', 'Invoice sent to super admin', [
                'agreement_id'   => $agreementId,
                'invoice_number' => $invoice->invoice_number,
                'sent_to'        => $email
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Invoice sent successfully to ' . $email]);
            }

            return redirect()->back()->with('success', 'Invoice sent successfully to ' . $email);

        } catch (\Exception $e) {
            Log::error('Failed to send invoice', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to send invoice: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    public function setAgreementAsPrimary(Request $request, $agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'active') {
                throw new \Exception('Only active agreements can be set as primary.');
            }

            AdminBillingRecord::where('developer_setting_id', $agreement->developer_setting_id)
                ->update(['is_primary_for_billing' => false]);

            $agreement->update([
                'is_primary_for_billing' => true,
                'billing_contact_name'   => $request->get('billing_contact_name', $agreement->superAdmin->name),
                'billing_contact_email'  => $request->get('billing_contact_email', $agreement->superAdmin->email),
                'billing_contact_phone'  => $request->get('billing_contact_phone', $agreement->superAdmin->phone),
            ]);

            DB::commit();

            Cache::forget("developer_billing_dashboard_{$user->id}");

            $this->logAudit('agreement_set_primary', 'Agreement set as primary for billing', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number
            ]);

            return redirect()->route('developer.billing.view-agreement', $agreementId)
                ->with('success', 'Agreement set as primary for billing.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to set agreement as primary', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to set agreement as primary: ' . $e->getMessage());
        }
    }

    public function agreeToBillingTerms(Request $request, $agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'pending') {
                throw new \Exception('Cannot agree to terms for non-pending agreement.');
            }

            $agreement->update([
                'agreed_at' => now(),
                'agreed_by' => $user->id,
                'status'    => 'active'
            ]);

            DB::commit();

            $this->logAudit('terms_agreed', 'Developer agreed to billing terms', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number
            ]);

            return redirect()->route('developer.billing.view-agreement', $agreementId)
                ->with('success', 'You have agreed to the billing terms.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to agree to terms', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to agree to terms: ' . $e->getMessage());
        }
    }

    public function rejectBillingTerms(Request $request, $agreementId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $this->authorizeDeveloperAccess($user);

            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $this->authorizeAgreementAccess($agreement);

            if ($agreement->status !== 'pending') {
                throw new \Exception('Cannot reject terms for non-pending agreement.');
            }

            $reason = $request->get('reason', 'No reason provided');

            $agreement->update([
                'status'             => 'rejected',
                'termination_reason' => $reason,
                'terminated_at'      => now(),
                'terminated_by'      => $user->id,
            ]);

            DB::commit();

            $this->logAudit('terms_rejected', 'Developer rejected billing terms', [
                'agreement_id'     => $agreementId,
                'agreement_number' => $agreement->agreement_number,
                'reason'           => $reason
            ]);

            return redirect()->route('developer.billing.dashboard')
                ->with('warning', 'You have rejected the billing terms. Please contact support if this was a mistake.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to reject terms', [
                'agreement_id' => $agreementId,
                'error'        => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to reject terms: ' . $e->getMessage());
        }
    }

    /**
     * Create agreement for a single super admin (helper method)
     */
    private function createAgreementForSuperAdmin($developerSettings, $superAdmin)
    {
        return AdminBillingRecord::create([
            'developer_setting_id'   => $developerSettings->id,
            'super_admin_id'         => $superAdmin->id,
            'agreement_number'       => $this->generateAgreementNumber(),
            'amount'                 => $developerSettings->monthly_billing_amount,
            'currency'               => $developerSettings->billing_currency,
            'billing_frequency'      => $developerSettings->billing_cycle,
            'description'            => 'Billing agreement for system services',
            'start_date'             => now(),
            'status'                 => 'pending',
            'payment_status'         => 'unpaid',
            'payment_method'         => $developerSettings->payment_method,
            'payment_account_name'   => $developerSettings->payment_account_name,
            'payment_account_number' => $developerSettings->payment_account_number,
            'payment_bank_name'      => $developerSettings->payment_bank_name,
            'created_by'             => auth()->id(),
            'is_primary_for_billing' => false,
        ]);
    }

    public function billingHistory(Request $request)
    {
        return $this->viewInvoices($request);
    }
}