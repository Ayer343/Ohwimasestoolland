<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\DeveloperSetting;
use App\Models\AdminBillingRecord;
use App\Models\User;
use App\Models\BillingInvoice;
use App\Models\AgreementSignature;
use App\Models\AgreementPayment;
use App\Models\SuperAdminPaymentRecord;
use App\Models\InvoiceReminder;
use App\Services\DeveloperBillingService;
use App\Services\PdfService;
use App\Services\PaymentService;
use App\Traits\AuditLogger;
use App\Traits\BillingHelperTrait;
use App\Jobs\SendInvoiceEmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SuperAdminBillingController extends Controller
{
    use AuditLogger, BillingHelperTrait;

    protected $billingService;
    protected $pdfService;
    protected $paymentService;
    protected $maxSignatureAttempts = 5;
    protected $signatureTimeoutMinutes = 30;

    public function __construct(
        DeveloperBillingService $billingService = null,
        PdfService $pdfService = null,
        PaymentService $paymentService = null
    ) {
        $this->billingService  = $billingService;
        $this->pdfService      = $pdfService;
        $this->paymentService  = $paymentService;
    }

    /* ============================================================
     | DASHBOARD
     * ============================================================ */

    public function dashboard()
    {
        try {
            $user = auth()->user();

            if ($user->type !== User::TYPE_SUPER_ADMIN) {
                abort(403, 'Unauthorized access to super admin billing');
            }

            $developerSettings = DeveloperSetting::first();

            if (!$developerSettings) {
                return view('superadmin.billing.dashboard', [
                    'agreements'            => collect(),
                    'pendingPayments'       => collect(),
                    'recentInvoices'        => collect(),
                    'awaitingSignature'     => collect(),
                    'isPrimaryForBilling'   => false,
                    'primaryBillingInfo'    => null,
                    'stats'                 => $this->emptyStats(),
                    'developerSettings'     => null,
                    'onlinePaymentProviders'=> $this->getOnlinePaymentProviders(),
                ])->with('info', 'System billing not yet configured by developer.');
            }

            // ---------- AGREEMENTS ----------
            $agreements = AdminBillingRecord::where('super_admin_id', $user->id)
                ->with([
                    'developerSetting',
                    'payments' => function ($query) {
                        $query->where('status', 'confirmed')->orderBy('payment_date', 'desc');
                    },
                    'signatures',
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // ---------- PRIMARY FLAG ----------
            $isPrimaryForBilling = AdminBillingRecord::where('super_admin_id', $user->id)
                ->where('is_primary_for_billing', true)
                ->exists();

            $primaryBillingInfo = null;
            if ($isPrimaryForBilling) {
                $primaryAgreement = AdminBillingRecord::where('super_admin_id', $user->id)
                    ->where('is_primary_for_billing', true)
                    ->first();

                if ($primaryAgreement) {
                    $primaryBillingInfo = [
                        'contact_name'     => $primaryAgreement->billing_contact_name ?? $user->name,
                        'contact_email'    => $primaryAgreement->billing_contact_email ?? $user->email,
                        'contact_phone'    => $primaryAgreement->billing_contact_phone ?? $user->phone,
                        'agreement_number' => $primaryAgreement->agreement_number,
                        'agreement_id'     => $primaryAgreement->id,
                    ];
                }
            }

            // ---------- PENDING PAYMENTS ----------
            $pendingPayments = collect();
            if (Schema::hasTable('agreement_payments')) {
                $pendingPayments = DB::table('agreement_payments')
                    ->join('admin_billing_records', 'agreement_payments.admin_billing_record_id', '=', 'admin_billing_records.id')
                    ->where('admin_billing_records.super_admin_id', $user->id)
                    ->where('agreement_payments.status', 'pending_confirmation')
                    ->select('agreement_payments.*', 'admin_billing_records.agreement_number')
                    ->orderBy('agreement_payments.created_at', 'desc')
                    ->get();
            }

            // ---------- RECENT INVOICES ----------
            $recentInvoices = collect();
            try {
                $developerSettingIds = AdminBillingRecord::where('super_admin_id', $user->id)
                    ->pluck('developer_setting_id')
                    ->unique()
                    ->filter()
                    ->values()
                    ->toArray();

                if (!empty($developerSettingIds)) {
                    $recentInvoices = BillingInvoice::whereIn('developer_setting_id', $developerSettingIds)
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->map(function ($invoice) use ($isPrimaryForBilling) {
                            $invoice->is_primary_invoice = $isPrimaryForBilling
                                && ($invoice->agreement_id || $invoice->admin_billing_record_id);
                            return $invoice;
                        });
                }
            } catch (\Exception $e) {
                Log::warning('Could not load invoices in dashboard: ' . $e->getMessage());
            }

            // ---------- AWAITING SIGNATURE ----------
            $awaitingSignature = AdminBillingRecord::where('super_admin_id', $user->id)
                ->where('status', 'pending')
                ->whereNotNull('agreement_pdf_path')
                ->whereNull('signed_agreement_pdf_path')
                ->with('developerSetting')
                ->orderBy('signing_invitation_sent_at', 'desc')
                ->get();

            // ---------- SHARED PAYMENT TOTALS ----------
            $sharedTotalPaid = 0;
            $sharedTotalDue  = 0;
            if ($isPrimaryForBilling && Schema::hasTable('super_admin_payment_records')) {
                try {
                    $sharedTotalPaid = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                        ->where('billing_month', Carbon::now()->format('Y-m'))
                        ->sum('amount_paid');
                } catch (\Exception $e) {
                    Log::warning('Could not calculate shared total paid: ' . $e->getMessage());
                }

                $sharedTotalDue = $agreements->where('status', 'active')
                    ->where('is_primary_for_billing', true)
                    ->sum('amount');
            }

            $stats = [
                'total_agreements'       => $agreements->count(),
                'active_agreements'      => $agreements->where('status', 'active')->count(),
                'pending_agreements'     => $agreements->where('status', 'pending')->count(),
                'completed_agreements'   => $agreements->where('status', 'completed')->count(),
                'terminated_agreements'  => $agreements->where('status', 'terminated')->count(),
                'total_amount_agreed'    => $agreements->where('status', 'active')->sum('amount'),
                'total_amount_paid'      => $agreements->where('status', 'active')->sum('amount_received'),
                'pending_payments'       => $pendingPayments->count(),
                'awaiting_signature'     => $awaitingSignature->count(),
                'signed_agreements'      => $agreements->where('status', 'active')
                    ->whereNotNull('signed_agreement_pdf_path')->count(),
                'is_primary'             => $isPrimaryForBilling,
                'shared_total_paid'      => $sharedTotalPaid,
                'shared_total_due'       => $sharedTotalDue,
                'shared_remaining'       => max(0, $sharedTotalDue - $sharedTotalPaid),
            ];

            $paymentMethods  = $this->getPaymentMethodsConfiguration();
            $onlineProviders = $this->getOnlinePaymentProviders();

            $this->logAudit('superadmin_billing_dashboard', 'Super Admin billing dashboard accessed', [
                'super_admin_id'         => $user->id,
                'agreement_count'        => $agreements->count(),
                'is_primary_for_billing' => $isPrimaryForBilling,
            ]);

            return view('superadmin.billing.dashboard', compact(
                'agreements',
                'pendingPayments',
                'recentInvoices',
                'awaitingSignature',
                'isPrimaryForBilling',
                'primaryBillingInfo',
                'stats',
                'paymentMethods',
                'developerSettings',
                'onlineProviders'
            ));

        } catch (\Exception $e) {
            Log::error('Super Admin billing dashboard error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace'     => $e->getTraceAsString(),
            ]);

            return view('superadmin.billing.dashboard', [
                'agreements'            => collect(),
                'pendingPayments'       => collect(),
                'recentInvoices'        => collect(),
                'awaitingSignature'     => collect(),
                'isPrimaryForBilling'   => false,
                'primaryBillingInfo'    => null,
                'stats'                 => $this->emptyStats(),
                'paymentMethods'        => $this->getPaymentMethodsConfiguration(),
                'developerSettings'     => null,
                'onlineProviders'       => $this->getOnlinePaymentProviders(),
            ])->with('warning', 'Some billing data could not be loaded.');
        }
    }

    /* ============================================================
     | AGREEMENTS
     * ============================================================ */

    public function agreementsList(Request $request)
    {
        $user = auth()->user();
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to view agreements');
        }

        try {
            $developerSettings = DeveloperSetting::first();

            if (!$developerSettings) {
                return redirect()->back()->with('error', 'System billing not configured');
            }

            $query = AdminBillingRecord::where('super_admin_id', $user->id)
                ->with('developerSetting');

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

            if ($request->filled('date_range') && $request->date_range !== 'all') {
                $now = now();
                switch ($request->date_range) {
                    case 'this_month':
                        $query->whereMonth('created_at', $now->month)
                              ->whereYear('created_at', $now->year);
                        break;
                    case 'last_month':
                        $lastMonth = $now->copy()->subMonth();
                        $query->whereMonth('created_at', $lastMonth->month)
                              ->whereYear('created_at', $lastMonth->year);
                        break;
                    case 'this_year':
                        $query->whereYear('created_at', $now->year);
                        break;
                    case 'custom':
                        if ($request->filled('start_date') && $request->filled('end_date')) {
                            $query->whereBetween('created_at', [
                                $request->start_date . ' 00:00:00',
                                $request->end_date . ' 23:59:59',
                            ]);
                        }
                        break;
                }
            }

            if ($request->filled('search')) {
                $searchTerm = '%' . $this->sanitizeInput($request->search) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('agreement_number', 'LIKE', $searchTerm)
                      ->orWhere('description', 'LIKE', $searchTerm)
                      ->orWhere('amount', 'LIKE', $searchTerm);
                });
            }

            $agreements = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20)
                ->withQueryString();

            foreach ($agreements as $agreement) {
                $agreement->is_primary_display = $agreement->is_primary_for_billing ?? false;
            }

            $stats = [
                'total_agreements'    => AdminBillingRecord::where('super_admin_id', $user->id)->count(),
                'active_agreements'   => AdminBillingRecord::where('super_admin_id', $user->id)->where('status', 'active')->count(),
                'pending_agreements'  => AdminBillingRecord::where('super_admin_id', $user->id)->where('status', 'pending')->count(),
                'total_amount_agreed' => AdminBillingRecord::where('super_admin_id', $user->id)->where('status', 'active')->sum('amount'),
                'total_amount_paid'   => AdminBillingRecord::where('super_admin_id', $user->id)->where('status', 'active')->sum('amount_received'),
                'is_primary'          => AdminBillingRecord::where('super_admin_id', $user->id)->where('is_primary_for_billing', true)->exists(),
            ];

            return view('superadmin.billing.agreements-list', compact('agreements', 'stats'));

        } catch (\Exception $e) {
            Log::error('Failed to view agreements: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading agreements: ' . $e->getMessage());
        }
    }

    public function viewAgreement($agreementId)
    {
        $user = auth()->user();

        try {
            $agreement = AdminBillingRecord::with(['developerSetting', 'payments', 'signatures.user'])
                ->findOrFail($agreementId);

            if ($agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to view this agreement');
            }

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

            $signatureStatus = $this->getSignatureStatus($agreement);
            $paymentDetails = $this->getPaymentDetails($agreement->id);

            $canSign = false;
            if ($agreement->status === 'pending') {
                $hasSigned = $agreement->signatures()
                    ->where('user_id', $user->id)
                    ->where('signature_type', 'super_admin')
                    ->exists();
                $canSign = !$hasSigned;
            }

            $isPrimaryForBilling = $agreement->is_primary_for_billing ?? false;

            $sharedPaymentInfo = null;
            if ($isPrimaryForBilling && Schema::hasTable('super_admin_payment_records')) {
                $currentMonth = Carbon::now()->format('Y-m');
                try {
                    $sharedPaymentInfo = [
                        'total_paid_this_month' => SuperAdminPaymentRecord::where('developer_setting_id', $agreement->developer_setting_id)
                            ->where('billing_month', $currentMonth)
                            ->sum('amount_paid'),
                        'billing_month' => $currentMonth,
                        'is_primary'    => true,
                    ];
                } catch (\Exception $e) {
                    Log::warning('Could not load shared payment info: ' . $e->getMessage());
                }
            }

            $paymentMethods  = $this->getPaymentMethodsConfiguration();
            $onlineProviders = $this->getOnlinePaymentProviders();

            $this->logAudit('agreement_viewed', 'Billing agreement viewed by super admin', [
                'agreement_id'    => $agreementId,
                'super_admin_id'  => $user->id,
                'is_primary'      => $isPrimaryForBilling,
            ]);

            return view('superadmin.billing.agreement-details', compact(
                'agreement',
                'payments',
                'signatures',
                'signatureStatus',
                'paymentDetails',
                'canSign',
                'paymentMethods',
                'isPrimaryForBilling',
                'sharedPaymentInfo',
                'onlineProviders'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to view agreement: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading agreement details: ' . $e->getMessage());
        }
    }

    public function viewAgreementForSigning($agreementId)
    {
        try {
            $agreement = AdminBillingRecord::with(['developerSetting', 'signatures.user'])
                ->findOrFail($agreementId);

            $user = auth()->user();

            if ($agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to sign this agreement');
            }

            if ($agreement->status !== 'pending') {
                return redirect()->back()->with('error', 'Agreement is not in pending status for signing');
            }

            $hasSigned = $agreement->signatures()
                ->where('user_id', $user->id)
                ->where('signature_type', 'super_admin')
                ->exists();

            $rateLimitKey = 'signature-view:' . $user->id . ':' . $agreementId;
            if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return redirect()->back()->with('error', 'Too many signature attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.');
            }
            RateLimiter::hit($rateLimitKey);

            $agreementData = $this->prepareAgreementData($agreement);
            $agreementData['is_primary_for_billing'] = $agreement->is_primary_for_billing ?? false;
            $agreementData['billing_contact'] = [
                'name'  => $agreement->billing_contact_name  ?? $agreement->superAdmin->name  ?? null,
                'email' => $agreement->billing_contact_email ?? $agreement->superAdmin->email ?? null,
                'phone' => $agreement->billing_contact_phone ?? $agreement->superAdmin->phone ?? null,
            ];

            $signatureStatus     = $this->getSignatureStatus($agreement);
            $signatureCompletion = $this->checkSignaturesComplete($agreementId);

            $agreementData['has_signed']           = $hasSigned;
            $agreementData['can_sign']             = !$hasSigned;
            $agreementData['user_type']            = 'super_admin';
            $agreementData['developer_signed']     = $signatureStatus['developer_signed'];
            $agreementData['signature_status']     = $signatureStatus;
            $agreementData['signature_completion'] = $signatureCompletion;

            $existingSignatures = $agreement->signatures->map(function ($signature) {
                return [
                    'id'             => $signature->id,
                    'user_name'      => $signature->user->name ?? 'Unknown',
                    'user_type'      => $signature->signature_type === 'developer' ? 'Developer' : 'Super Admin',
                    'signature_date' => $signature->signature_date->format('F j, Y'),
                    'signature_name' => $signature->signature_name,
                ];
            });

            $this->logAudit('agreement_signing_viewed', 'Agreement signing page viewed by super admin', [
                'agreement_id'   => $agreementId,
                'super_admin_id' => $user->id,
                'is_primary'     => $agreement->is_primary_for_billing ?? false,
            ]);

            return view('superadmin.billing.agreement-signing', compact(
                'agreement',
                'agreementData',
                'existingSignatures',
                'hasSigned'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to view agreement for signing: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading agreement for signing: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | SIGNATURE SUBMISSION (with auto-invoice trigger)
     * ============================================================ */

    public function submitSignature(Request $request, $agreementId)
    {
        $rateLimitKey = 'signature-submission:' . auth()->id() . ':' . $agreementId;
        if (RateLimiter::tooManyAttempts($rateLimitKey, $this->maxSignatureAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return redirect()->back()
                ->with('error', 'Too many signature attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.');
        }

        $validator = Validator::make($request->all(), [
            'signature_data' => 'required|string',
            'signature_type' => 'required|in:typed,draw,upload',
            'signature_name' => 'required|string|max:255',
            'signature_date' => 'required|date|before_or_equal:now',
            'ip_address'     => 'required|ip',
            'accept_terms'   => 'required|accepted',
        ], [
            'signature_data.required'      => 'Please provide your signature',
            'accept_terms.accepted'        => 'You must accept the terms and conditions',
            'signature_date.before_or_equal'=> 'Signature date cannot be in the future',
        ]);

        if ($validator->fails()) {
            Log::warning('Signature validation failed', [
                'errors'             => $validator->errors()->all(),
                'signature_type'     => $request->input('signature_type'),
                'has_signature_data' => !empty($request->input('signature_data')),
                'accept_terms'       => $request->input('accept_terms'),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the signature submission errors');
        }

        $signatureType = $request->input('signature_type');
        $signatureData = $request->input('signature_data');

        if ($signatureType === 'typed' && trim($signatureData) === '') {
            return redirect()->back()->withErrors(['signature_data' => 'Please type your signature'])->withInput();
        }

        if (in_array($signatureType, ['draw', 'upload'], true)) {
            if (empty($signatureData) || !str_starts_with($signatureData, 'data:image')) {
                return redirect()->back()->withErrors(['signature_data' => 'Please provide a valid signature image'])->withInput();
            }

            try {
                $parts = explode(',', $signatureData);
                $base64 = $parts[1] ?? $signatureData;

                if (!preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $base64)) {
                    return redirect()->back()->withErrors(['signature_data' => 'Invalid signature image format'])->withInput();
                }

                $size = (int) (strlen(rtrim($base64, '=')) * 3 / 4);
                if ($size > 2 * 1024 * 1024) {
                    return redirect()->back()->withErrors(['signature_data' => 'Signature image is too large (max 2MB)'])->withInput();
                }

                $imageData = base64_decode($base64);
                if ($imageData === false) {
                    return redirect()->back()->withErrors(['signature_data' => 'Invalid signature image data'])->withInput();
                }

                $image = @imagecreatefromstring($imageData);
                if (!$image) {
                    return redirect()->back()->withErrors(['signature_data' => 'Invalid image format. Please use JPEG, PNG, or GIF'])->withInput();
                }
                imagedestroy($image);

            } catch (\Exception $e) {
                Log::warning('Failed to validate signature data', ['error' => $e->getMessage()]);
                return redirect()->back()->withErrors(['signature_data' => 'Failed to validate signature image'])->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $user      = auth()->user();

            if ($agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to sign this agreement');
            }

            if ($agreement->status !== 'pending') {
                return redirect()->back()->with('error', 'Agreement is not in pending status for signing');
            }

            $hasSigned = AgreementSignature::where('agreement_id', $agreementId)
                ->where('user_id', $user->id)
                ->where('signature_type', 'super_admin')
                ->exists();

            if ($hasSigned) {
                return redirect()->back()->with('error', 'You have already signed this agreement');
            }

            $data = $validator->validated();

            $signatureHash = hash('sha256',
                $user->id .
                $agreementId .
                $data['signature_name'] .
                $data['signature_date'] .
                Str::random(32)
            );

            $signature = AgreementSignature::create([
                'agreement_id'     => $agreementId,
                'user_id'          => $user->id,
                'signature_type'   => 'super_admin',
                'signature_data'   => $data['signature_data'],
                'signature_format' => $data['signature_type'],
                'signature_name'   => $data['signature_name'],
                'signature_date'   => $data['signature_date'],
                'ip_address'       => $data['ip_address'],
                'user_agent'       => $request->userAgent(),
                'status'           => 'verified',
                'digital_hash'     => $signatureHash,
                'session_id'       => session()->getId(),
            ]);

            $signaturePath = $this->saveSignatureImage($data['signature_data'], $agreementId, $user->id, true);
            if ($signaturePath) {
                $signature->update(['signature_path' => $signaturePath]);
            }

            $agreement->update([
                'super_admin_signed_at'     => now(),
                'last_signature_attempt_at' => now(),
            ]);

            $signatureStatus = $this->checkSignaturesComplete($agreementId);
            $invoice = null;

            if ($signatureStatus['complete']) {
                $agreement->update([
                    'status'                => 'active',
                    'agreed_at'             => now(),
                    'agreed_by'             => $user->id,
                    'signing_completed_at'  => now(),
                ]);

                $this->generateFinalAgreementPdf($agreementId);

                // AUTO-INVOICE GENERATION for the primary agreement
                if ($agreement->is_primary_for_billing) {
                    try {
                        $developerSettings = DeveloperSetting::first();
                        if ($developerSettings) {
                            $invoice = $this->generateInvoiceAfterSigning($developerSettings, $agreement);

                            if ($invoice) {
                                $this->logAudit('invoice_generated_after_signing', 'Invoice generated after super admin signing', [
                                    'agreement_id'   => $agreementId,
                                    'invoice_number' => $invoice->invoice_number,
                                    'is_primary'     => $agreement->is_primary_for_billing,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to generate invoice after super admin signing: ' . $e->getMessage(), [
                            'agreement_id' => $agreementId,
                        ]);
                    }
                }

                $this->sendSignatureCompletionNotifications($agreementId);

                $this->logAudit('primary_agreement_activated', 'Primary billing agreement activated', [
                    'agreement_id'     => $agreementId,
                    'super_admin_id'   => $user->id,
                    'agreement_number' => $agreement->agreement_number,
                ]);
            } else {
                $this->sendSignaturePendingNotification($agreementId, 'developer');
            }

            DB::commit();
            RateLimiter::clear($rateLimitKey);

            $this->logAudit('agreement_signed', 'Agreement signed by super admin', $this->sanitizeLogData([
                'agreement_id'       => $agreementId,
                'super_admin_id'     => $user->id,
                'signature_id'       => $signature->id,
                'signature_type'     => $data['signature_type'],
                'digital_hash'       => $signatureHash,
                'signature_complete' => $signatureStatus['complete'],
                'is_primary'         => $agreement->is_primary_for_billing ?? false,
                'invoice_generated'  => $invoice ? $invoice->invoice_number : null,
                'session_id'         => session()->getId(),
            ]));

            $message = $signatureStatus['complete']
                ? 'Agreement signed successfully! Both parties have signed and the agreement is now active.'
                : 'Your signature has been recorded. Waiting for developer to sign.';

            if ($agreement->is_primary_for_billing && $signatureStatus['complete']) {
                $message .= ' As the primary billing contact, you will receive all future invoices.';
                if ($invoice) {
                    $message .= ' An invoice has been generated for the current billing period.';
                }
            }

            return redirect()->route('superadmin.billing.view-agreement', $agreementId)
                ->with('success', $message)
                ->with('signature_complete', $signatureStatus['complete'])
                ->with('signature_id', $signature->id)
                ->with('invoice_generated', $invoice ? true : false)
                ->with('invoice_number', $invoice ? $invoice->invoice_number : null);

        } catch (\Exception $e) {
            DB::rollBack();
            RateLimiter::hit($rateLimitKey);

            Log::error('Failed to submit signature: ' . $e->getMessage(), [
                'exception'      => $e,
                'agreement_id'   => $agreementId,
                'user_id'        => auth()->id(),
                'signature_type' => $request->input('signature_type'),
                'request_data'   => $request->except(['signature_data', 'ip_address']),
            ]);

            return redirect()->back()
                ->with('error', 'Unable to process signature. Please try again.')
                ->withInput();
        }
    }

    /* ============================================================
     | INVOICES
     * ============================================================ */

    public function viewInvoice($invoiceId)
    {
        try {
            $user = auth()->user();

            if ($user->type !== User::TYPE_SUPER_ADMIN) {
                abort(403, 'Unauthorized to view this invoice');
            }

            $invoice = BillingInvoice::with(['developerSetting'])->findOrFail($invoiceId);

            if ($invoice->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to view this invoice');
            }

            // Resolve agreement — schema uses agreement_id OR admin_billing_record_id
            $agreement = null;
            if (Schema::hasColumn('billing_invoices', 'agreement_id') && $invoice->agreement_id) {
                $agreement = AdminBillingRecord::find($invoice->agreement_id);
            } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id') && $invoice->admin_billing_record_id) {
                $agreement = AdminBillingRecord::find($invoice->admin_billing_record_id);
            }

            $payments      = collect();
            $totalPaid     = (float) ($invoice->paid_amount ?? 0);
            $remaining     = max(0, (float) $invoice->amount - $totalPaid);
            $isPaid        = $remaining <= 0 || $invoice->status === 'paid';
            $paymentMethodDetails = null;
            $signatureStatus = null;
            $isPrimaryInvoice = false;

            if ($agreement) {
                $payments = $agreement->payments()->where('status', 'confirmed')->get();
                $totalPaid = max($totalPaid, $payments->sum('amount_paid'));
                $remaining = max(0, (float) $invoice->amount - $totalPaid);
                $isPaid    = $remaining <= 0 || $invoice->status === 'paid';
                $paymentMethodDetails = $this->getPaymentMethodDetails($agreement);

                $developerSigned  = $agreement->signatures()->where('signature_type', 'developer')->exists();
                $superAdminSigned = $agreement->signatures()->where('signature_type', 'super_admin')->exists();

                $signatureStatus = [
                    'developer_signed'   => $developerSigned,
                    'super_admin_signed' => $superAdminSigned,
                    'all_signed'         => ($developerSigned && $superAdminSigned),
                ];
                $isPrimaryInvoice = $agreement->is_primary_for_billing ?? false;
            }

            $paymentMethods  = $this->getPaymentMethodsConfiguration();
            $onlineProviders = $this->getOnlinePaymentProviders();
            $invoiceFile     = $this->getInvoiceFilePath($invoice);

            $this->logAudit('invoice_viewed', 'Super Admin viewed invoice', [
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoice->invoice_number,
                'super_admin_id' => $user->id,
                'is_primary'     => $isPrimaryInvoice,
                'has_agreement'  => $agreement ? true : false,
            ]);

            return view('superadmin.billing.invoice-details', compact(
                'invoice',
                'agreement',
                'payments',
                'totalPaid',
                'remaining',
                'isPaid',
                'paymentMethods',
                'signatureStatus',
                'paymentMethodDetails',
                'invoiceFile',
                'isPrimaryInvoice',
                'onlineProviders'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to view invoice', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return redirect()->route('superadmin.billing.dashboard')
                ->with('error', 'Error loading invoice details: ' . $e->getMessage());
        }
    }

    public function invoiceList(Request $request)
    {
        try {
            $user = auth()->user();

            if ($user->type !== User::TYPE_SUPER_ADMIN) {
                abort(403, 'Unauthorized to view invoices');
            }

            $query = BillingInvoice::where('super_admin_id', $user->id)
                ->with(['developerSetting']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('issue_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('issue_date', '<=', $request->date_to);
            }
            if ($request->filled('search')) {
                $search = '%' . $request->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'LIKE', $search)
                      ->orWhere('description', 'LIKE', $search);
                });
            }

            $invoices = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20))
                ->withQueryString();

            foreach ($invoices as $invoice) {
                $agreement = null;
                if (Schema::hasColumn('billing_invoices', 'agreement_id') && $invoice->agreement_id) {
                    $agreement = AdminBillingRecord::find($invoice->agreement_id);
                } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id') && $invoice->admin_billing_record_id) {
                    $agreement = AdminBillingRecord::find($invoice->admin_billing_record_id);
                }

                $invoice->is_primary_invoice     = $agreement && $agreement->is_primary_for_billing;
                $invoice->has_agreement          = $agreement ? true : false;
                $invoice->agreement_id_for_payment = $agreement ? $agreement->id : null;
            }

            // Separate stats query so pagination doesn't leak into sums
            $statsBase = BillingInvoice::where('super_admin_id', $user->id);

            $stats = [
                'total_invoices'  => (clone $statsBase)->count(),
                'total_amount'    => (float) (clone $statsBase)->sum('amount'),
                'paid_amount'     => (float) (clone $statsBase)->sum('paid_amount'),
                'pending_amount'  => (float) (clone $statsBase)->where('status', 'pending')->sum('amount'),
                'overdue_amount'  => (float) (clone $statsBase)->where('status', 'overdue')->sum('amount'),
                'primary_invoices'=> (clone $statsBase)
                    ->when(Schema::hasColumn('billing_invoices', 'agreement_id'), function ($q) {
                        $q->whereHas('adminBillingRecord', function ($qq) {
                            $qq->where('is_primary_for_billing', true);
                        });
                    })
                    ->count(),
            ];

            $onlineProviders = $this->getOnlinePaymentProviders();

            $this->logAudit('invoice_list_viewed', 'Super Admin viewed invoice list', [
                'super_admin_id' => $user->id,
                'total_invoices' => $stats['total_invoices'],
            ]);

            return view('superadmin.billing.invoice-list', compact('invoices', 'stats', 'onlineProviders'));

        } catch (\Exception $e) {
            Log::error('Failed to list invoices', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('superadmin.billing.dashboard')
                ->with('error', 'Error loading invoices: ' . $e->getMessage());
        }
    }

    public function downloadInvoice($invoiceId)
    {
        try {
            $user = auth()->user();

            if ($user->type !== User::TYPE_SUPER_ADMIN) {
                abort(403, 'Unauthorized to download this invoice');
            }

            $invoice = BillingInvoice::findOrFail($invoiceId);

            if ($invoice->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to download this invoice');
            }

            $filePath = $this->getInvoiceFilePath($invoice);

            if (!$filePath) {
                $filePath = $this->generateInvoicePdf($invoice);
            }

            $this->logAudit('invoice_downloaded', 'Super Admin downloaded invoice', [
                'invoice_id'     => $invoiceId,
                'invoice_number' => $invoice->invoice_number,
                'super_admin_id' => $user->id,
            ]);

            return response()->download(
                storage_path('app/' . $filePath),
                'invoice-' . $invoice->invoice_number . '.pdf'
            );

        } catch (\Exception $e) {
            Log::error('Failed to download invoice', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to download invoice: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | ONLINE PAYMENT
     * ============================================================ */

    public function showRecordPaymentForm($agreementId)
    {
        try {
            $user = auth()->user();

            if ($user->type !== User::TYPE_SUPER_ADMIN) {
                abort(403, 'Unauthorized to record payment');
            }

            $agreement = AdminBillingRecord::with(['developerSetting', 'superAdmin'])
                ->findOrFail($agreementId);

            if ($agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to record payment for this agreement');
            }

            if ($agreement->status !== 'active') {
                return redirect()->back()->with('error', 'Agreement is not active. Cannot record payment.');
            }

            $paymentMethods  = $this->getPaymentMethodsConfiguration();
            $onlineProviders = $this->getOnlinePaymentProviders();

            $hasOnlineProvider = false;
            foreach ($onlineProviders as $provider) {
                if ($provider['available'] && $provider['enabled']) {
                    $hasOnlineProvider = true;
                    break;
                }
            }

            $remaining = max(0, $agreement->amount - $agreement->amount_received);

            $this->logAudit('payment_form_viewed', 'Super Admin viewed payment recording form', [
                'agreement_id'         => $agreementId,
                'super_admin_id'       => $user->id,
                'is_primary'           => $agreement->is_primary_for_billing ?? false,
                'has_online_provider'  => $hasOnlineProvider,
            ]);

            return view('superadmin.billing.record-payment', compact(
                'agreement',
                'paymentMethods',
                'onlineProviders',
                'remaining',
                'hasOnlineProvider'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to load payment recording form: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payment form: ' . $e->getMessage());
        }
    }

    public function recordOwnPayment(Request $request, $agreementId)
    {
        $onlineProviders = ['paystack', 'expresspay', 'flutterwave', 'hubtel'];
        $isOnlinePayment = in_array($request->payment_method, $onlineProviders, true);

        if (!$isOnlinePayment) {
            Log::warning('Offline payment attempted in online-only mode', [
                'payment_method' => $request->payment_method,
                'agreement_id'   => $agreementId,
                'user_id'        => auth()->id(),
            ]);

            return redirect()->back()
                ->with('error', 'Only online payments are supported. Please select a valid payment provider.')
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'amount_paid'           => 'required|numeric|min:0.01',
            'payment_date'          => 'required|date|before_or_equal:today',
            'payment_method'        => 'required|in:paystack,expresspay,flutterwave,hubtel',
            'transaction_reference' => 'nullable|string|max:100',
            'notes'                 => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please fix the payment errors.');
        }

        try {
            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $user      = auth()->user();

            if ($agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to record payment for this agreement');
            }

            if ($agreement->status !== 'active') {
                return redirect()->back()
                    ->with('error', 'Agreement is not active. Cannot process payment.')
                    ->withInput();
            }

            $remaining = max(0, $agreement->amount - $agreement->amount_received);
            if ($remaining <= 0) {
                return redirect()->back()->with('info', 'This agreement is already fully paid.');
            }

            $amountPaid = (float) $request->amount_paid;
            if (abs($amountPaid - $remaining) > 0.01) {
                return redirect()->back()
                    ->with('error', 'Only full payment is accepted. The remaining balance is ' . number_format($remaining, 2) . ' GHS.')
                    ->withInput();
            }

            return $this->processOnlinePayment($request, $agreementId);

        } catch (\Exception $e) {
            Log::error('Payment processing error: ' . $e->getMessage(), [
                'agreement_id' => $agreementId,
                'user_id'      => auth()->id() ?? null,
                'trace'        => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to process payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function recordPayment(Request $request, $agreementId)
    {
        return $this->recordOwnPayment($request, $agreementId);
    }

    private function processOnlinePayment(Request $request, $agreementId)
    {
        try {
            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $user      = auth()->user();

            if ($agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to process payment for this agreement');
            }

            $provider    = $request->payment_method;
            $amount      = (float) $request->amount_paid;
            $description = "Payment for agreement #{$agreement->agreement_number} - {$agreement->description}";
            $email       = $user->email;

            if (!$this->checkDeveloperProviderConfiguration($provider)) {
                return redirect()->back()
                    ->with('error', "The developer has not configured {$provider} for online payments. Please contact the developer.")
                    ->withInput();
            }

            $paymentResponse = $this->initializePaymentWithProvider($provider, $amount, $description, $email, $agreement);

            if (!$paymentResponse['success']) {
                return redirect()->back()
                    ->with('error', 'Payment initialization failed: ' . $paymentResponse['message'])
                    ->withInput();
            }

            session([
                'pending_payment' => [
                    'agreement_id' => $agreementId,
                    'amount'       => $amount,
                    'provider'     => $provider,
                    'reference'    => $paymentResponse['reference'],
                    'user_id'      => $user->id,
                    'timestamp'    => now()->toDateTimeString(),
                ],
            ]);

            $this->logAudit('online_payment_initialized', 'Super Admin initialized online payment', [
                'agreement_id'    => $agreementId,
                'super_admin_id'  => $user->id,
                'provider'        => $provider,
                'amount'          => $amount,
                'reference'       => $paymentResponse['reference'],
                'redirect_url'    => $paymentResponse['authorization_url'],
            ]);

            return redirect($paymentResponse['authorization_url']);

        } catch (\Exception $e) {
            Log::error('Failed to process online payment: ' . $e->getMessage(), [
                'agreement_id' => $agreementId,
                'provider'     => $request->payment_method ?? 'unknown',
                'trace'        => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to process online payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    private function initializePaymentWithProvider($provider, $amount, $description, $email, $agreement)
    {
        return match ($provider) {
            'paystack'    => $this->initializePaystackPayment($amount, $description, $email, $agreement),
            'expresspay'  => $this->initializeExpressPayPayment($amount, $description, $email, $agreement),
            'flutterwave' => $this->initializeFlutterwavePayment($amount, $description, $email, $agreement),
            'hubtel'      => $this->initializeHubtelPayment($amount, $description, $email, $agreement),
            default       => ['success' => false, 'message' => 'Unsupported payment provider'],
        };
    }

    /* ---------- Provider initializers ---------- */

    private function initializePaystackPayment($amount, $description, $email, $agreement)
    {
        $secretKey = env('DEVELOPER_PAYSTACK_SECRET_KEY');
        if (empty($secretKey)) {
            return ['success' => false, 'message' => 'Paystack secret key is not configured'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type'  => 'application/json',
            ])->post('https://api.paystack.co/transaction/initialize', [
                'amount'       => $amount * 100,
                'email'        => $email,
                'metadata'     => [
                    'agreement_id'      => $agreement->id,
                    'agreement_number'  => $agreement->agreement_number,
                    'super_admin_id'    => auth()->id(),
                    'super_admin_email' => $email,
                    'description'       => $description,
                    'is_primary'        => $agreement->is_primary_for_billing ?? false,
                    'type'              => 'super_admin_payment',
                ],
                'callback_url' => route('superadmin.billing.payment-callback'),
                'reference'    => 'PAY-' . strtoupper(Str::random(10)) . '-' . time(),
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'Paystack initialization failed'];
            }

            $data = $response->json()['data'] ?? [];

            return [
                'success'           => true,
                'reference'         => $data['reference'],
                'authorization_url' => $data['authorization_url'],
                'access_code'       => $data['access_code'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Paystack error: ' . $e->getMessage()];
        }
    }

    private function initializeExpressPayPayment($amount, $description, $email, $agreement)
    {
        $merchantId = env('DEVELOPER_EXPRESSPAY_MERCHANT_ID');
        $apiKey     = env('DEVELOPER_EXPRESSPAY_API_KEY');

        if (empty($merchantId) || empty($apiKey)) {
            return ['success' => false, 'message' => 'ExpressPay credentials are not configured'];
        }

        try {
            $baseUrl = env('EXPRESSPAY_BASE_URL', 'https://api.expresspaygh.com');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->post($baseUrl . '/payments/initialize', [
                'merchant_id'    => $merchantId,
                'amount'         => $amount,
                'description'    => $description,
                'customer_email' => $email,
                'customer_name'  => auth()->user()->name ?? 'Super Admin',
                'reference'      => 'EXP-' . strtoupper(Str::random(8)) . '-' . time(),
                'callback_url'   => route('superadmin.billing.payment-callback'),
                'metadata'       => [
                    'agreement_id'     => $agreement->id,
                    'agreement_number' => $agreement->agreement_number,
                    'super_admin_id'   => auth()->id(),
                    'is_primary'       => $agreement->is_primary_for_billing ?? false,
                ],
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'ExpressPay initialization failed'];
            }

            $data = $response->json()['data'] ?? [];

            return [
                'success'           => true,
                'reference'         => $data['reference'] ?? $data['transaction_id'] ?? null,
                'authorization_url' => $data['authorization_url'] ?? $data['redirect_url'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'ExpressPay error: ' . $e->getMessage()];
        }
    }

    private function initializeFlutterwavePayment($amount, $description, $email, $agreement)
    {
        $secretKey = env('DEVELOPER_FLUTTERWAVE_SECRET_KEY');
        $publicKey = env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY');

        if (empty($secretKey) || empty($publicKey)) {
            return ['success' => false, 'message' => 'Flutterwave credentials are not configured'];
        }

        try {
            $baseUrl = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type'  => 'application/json',
            ])->post($baseUrl . '/payments', [
                'tx_ref'          => 'FLW-' . strtoupper(Str::random(10)) . '-' . time(),
                'amount'          => $amount,
                'currency'        => 'GHS',
                'redirect_url'    => route('superadmin.billing.payment-callback'),
                'payment_options' => 'card,mobilemoney,ussd',
                'customer'        => [
                    'email' => $email,
                    'name'  => auth()->user()->name ?? 'Super Admin',
                ],
                'meta' => [
                    'agreement_id'     => $agreement->id,
                    'agreement_number' => $agreement->agreement_number,
                    'super_admin_id'   => auth()->id(),
                    'is_primary'       => $agreement->is_primary_for_billing ?? false,
                ],
                'customizations' => [
                    'title'       => 'Billing Payment',
                    'description' => $description,
                ],
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'Flutterwave initialization failed'];
            }

            $data = $response->json()['data'] ?? [];

            return [
                'success'           => true,
                'reference'         => $data['tx_ref'] ?? null,
                'authorization_url' => $data['link'] ?? $data['redirect_url'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Flutterwave error: ' . $e->getMessage()];
        }
    }

    private function initializeHubtelPayment($amount, $description, $email, $agreement)
    {
        $clientId     = env('DEVELOPER_HUBTEL_CLIENT_ID');
        $clientSecret = env('DEVELOPER_HUBTEL_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'message' => 'Hubtel credentials are not configured'];
        }

        try {
            $baseUrl = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');
            $auth    = base64_encode($clientId . ':' . $clientSecret);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $auth,
                'Content-Type'  => 'application/json',
            ])->post($baseUrl . '/merchantaccount/merchant-payments', [
                'amount'          => $amount,
                'description'     => $description,
                'callbackUrl'     => route('superadmin.billing.payment-callback'),
                'clientReference' => 'HUB-' . strtoupper(Str::random(8)) . '-' . time(),
                'customer' => [
                    'email' => $email,
                    'name'  => auth()->user()->name ?? 'Super Admin',
                ],
                'metadata' => [
                    'agreement_id'     => $agreement->id,
                    'agreement_number' => $agreement->agreement_number,
                    'super_admin_id'   => auth()->id(),
                    'is_primary'       => $agreement->is_primary_for_billing ?? false,
                ],
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'Hubtel initialization failed'];
            }

            $data = $response->json()['data'] ?? [];

            return [
                'success'           => true,
                'reference'         => $data['transactionId'] ?? $data['reference'] ?? null,
                'authorization_url' => $data['redirectUrl'] ?? $data['authorizationUrl'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Hubtel error: ' . $e->getMessage()];
        }
    }

    /* ---------- Callback & verification ---------- */

    public function paymentCallback(Request $request)
    {
        try {
            $reference = $request->get('reference')
                ?? $request->get('tx_ref')
                ?? $request->get('transaction_id');

            if (!$reference) {
                Log::warning('Payment callback missing reference', ['request' => $request->all()]);
                return redirect()->route('superadmin.billing.dashboard')
                    ->with('error', 'Payment callback missing reference');
            }

            $pendingPayment = session('pending_payment');

            if (!$pendingPayment) {
                Log::warning('No pending payment found in session', ['reference' => $reference]);
                return redirect()->route('superadmin.billing.dashboard')
                    ->with('error', 'Payment session expired. Please try again.');
            }

            $verificationResult = $this->verifyPaymentWithProvider($pendingPayment['provider'], $reference);

            if (!$verificationResult['success']) {
                return redirect()->route('superadmin.billing.dashboard')
                    ->with('error', 'Payment verification failed: ' . $verificationResult['message']);
            }

            return $this->recordSuccessfulOnlinePayment($pendingPayment, $verificationResult);

        } catch (\Exception $e) {
            Log::error('Payment callback error: ' . $e->getMessage());
            return redirect()->route('superadmin.billing.dashboard')
                ->with('error', 'Payment processing error: ' . $e->getMessage());
        }
    }

    private function verifyPaymentWithProvider($provider, $reference)
    {
        return match ($provider) {
            'paystack'    => $this->verifyPaystackPayment($reference),
            'expresspay'  => $this->verifyExpressPayPayment($reference),
            'flutterwave' => $this->verifyFlutterwavePayment($reference),
            'hubtel'      => $this->verifyHubtelPayment($reference),
            default       => ['success' => false, 'message' => 'Unsupported provider'],
        };
    }

    private function verifyPaystackPayment($reference)
    {
        $secretKey = env('DEVELOPER_PAYSTACK_SECRET_KEY');
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get('https://api.paystack.co/transaction/verify/' . $reference);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'Paystack verification failed'];
            }

            $data = $response->json()['data'] ?? [];
            if (($data['status'] ?? 'failed') !== 'success') {
                return ['success' => false, 'message' => 'Payment was not successful: ' . ($data['status'] ?? 'unknown')];
            }

            return [
                'success'        => true,
                'amount'         => $data['amount'] / 100,
                'reference'      => $data['reference'],
                'transaction_id' => $data['id'] ?? null,
                'status'         => 'confirmed',
                'payment_method' => 'paystack',
                'provider_data'  => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Paystack verification error: ' . $e->getMessage()];
        }
    }

    private function verifyExpressPayPayment($reference)
    {
        $apiKey  = env('DEVELOPER_EXPRESSPAY_API_KEY');
        $baseUrl = env('EXPRESSPAY_BASE_URL', 'https://api.expresspaygh.com');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->get($baseUrl . '/payments/verify/' . $reference);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'ExpressPay verification failed'];
            }

            $data   = $response->json()['data'] ?? [];
            $status = $data['status'] ?? 'failed';
            if (!in_array($status, ['completed', 'success'], true)) {
                return ['success' => false, 'message' => 'Payment was not successful: ' . $status];
            }

            return [
                'success'        => true,
                'amount'         => $data['amount'] ?? 0,
                'reference'      => $data['reference'] ?? $reference,
                'transaction_id' => $data['transaction_id'] ?? null,
                'status'         => 'confirmed',
                'payment_method' => 'expresspay',
                'provider_data'  => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'ExpressPay verification error: ' . $e->getMessage()];
        }
    }

    private function verifyFlutterwavePayment($reference)
    {
        $secretKey = env('DEVELOPER_FLUTTERWAVE_SECRET_KEY');
        $baseUrl   = env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
            ])->get($baseUrl . '/transactions/verify_by_reference?tx_ref=' . $reference);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'Flutterwave verification failed'];
            }

            $data   = $response->json()['data'] ?? [];
            $status = $data['status'] ?? 'failed';
            if ($status !== 'successful') {
                return ['success' => false, 'message' => 'Payment was not successful: ' . $status];
            }

            return [
                'success'        => true,
                'amount'         => $data['amount'] ?? 0,
                'reference'      => $data['tx_ref'] ?? $reference,
                'transaction_id' => $data['id'] ?? null,
                'status'         => 'confirmed',
                'payment_method' => 'flutterwave',
                'provider_data'  => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Flutterwave verification error: ' . $e->getMessage()];
        }
    }

    private function verifyHubtelPayment($reference)
    {
        $clientId     = env('DEVELOPER_HUBTEL_CLIENT_ID');
        $clientSecret = env('DEVELOPER_HUBTEL_CLIENT_SECRET');
        $baseUrl      = env('HUBTEL_BASE_URL', 'https://api.hubtel.com/v1');

        try {
            $auth = base64_encode($clientId . ':' . $clientSecret);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $auth,
            ])->get($baseUrl . '/merchantaccount/merchant-payments/' . $reference);

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json()['message'] ?? 'Hubtel verification failed'];
            }

            $data   = $response->json()['data'] ?? [];
            $status = $data['status'] ?? 'failed';
            if (!in_array($status, ['completed', 'success'], true)) {
                return ['success' => false, 'message' => 'Payment was not successful: ' . $status];
            }

            return [
                'success'        => true,
                'amount'         => $data['amount'] ?? 0,
                'reference'      => $data['transactionId'] ?? $reference,
                'transaction_id' => $data['transactionId'] ?? null,
                'status'         => 'confirmed',
                'payment_method' => 'hubtel',
                'provider_data'  => $data,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Hubtel verification error: ' . $e->getMessage()];
        }
    }

    /* ---------- Final payment recording ---------- */

    private function recordSuccessfulOnlinePayment($pendingPayment, $verificationResult)
    {
        DB::beginTransaction();

        try {
            $agreement = AdminBillingRecord::findOrFail($pendingPayment['agreement_id']);
            $user      = User::findOrFail($pendingPayment['user_id']);

            $payment = AgreementPayment::create([
                'admin_billing_record_id' => $agreement->id,
                'amount_paid'             => $verificationResult['amount'],
                'payment_date'            => now(),
                'payment_method'          => $verificationResult['payment_method'],
                'transaction_reference'   => $verificationResult['reference'],
                'notes'                   => 'Online payment via ' . ucfirst($verificationResult['payment_method']) .
                                             ' - Transaction ID: ' . ($verificationResult['transaction_id'] ?? 'N/A'),
                'status'                  => 'confirmed',
                'recorded_by'             => $user->id,
                'confirmed_by'            => $user->id,
                'confirmed_at'            => now(),
                'recorded_at'             => now(),
                'provider_data'           => json_encode($verificationResult['provider_data'] ?? []),
            ]);

            $agreement->update([
                'amount_received' => $agreement->amount,
                'payment_status'  => 'paid',
            ]);

            $this->updateInvoicePaymentStatus($agreement->id, $verificationResult['amount']);

            session()->forget('pending_payment');

            DB::commit();

            $this->logAudit('online_payment_confirmed', 'Online payment confirmed', [
                'agreement_id' => $agreement->id,
                'payment_id'   => $payment->id,
                'amount'       => $verificationResult['amount'],
                'provider'     => $verificationResult['payment_method'],
                'reference'    => $verificationResult['reference'],
            ]);

            return redirect()->route('superadmin.billing.view-agreement', $agreement->id)
                ->with('success', 'Payment confirmed successfully! Amount: ' .
                    number_format($verificationResult['amount'], 2) . ' GHS');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record online payment: ' . $e->getMessage());

            return redirect()->route('superadmin.billing.dashboard')
                ->with('error', 'Payment was processed but failed to record: ' . $e->getMessage());
        }
    }

    /**
     * Update invoice status against the real schema.
     * Uses `amount` / `paid_amount` and looks up by agreement_id OR admin_billing_record_id.
     */
    private function updateInvoicePaymentStatus($agreementId, $amount)
    {
        try {
            if (!Schema::hasTable('billing_invoices')) {
                return;
            }

            $invoiceQuery = BillingInvoice::where('status', '!=', 'paid');

            if (Schema::hasColumn('billing_invoices', 'agreement_id')) {
                $invoiceQuery->where('agreement_id', $agreementId);
            } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                $invoiceQuery->where('admin_billing_record_id', $agreementId);
            } else {
                return;
            }

            $invoice = $invoiceQuery->orderByDesc('created_at')->first();
            if (!$invoice) {
                return;
            }

            $totalPaid = (float) AgreementPayment::where('admin_billing_record_id', $agreementId)
                ->where('status', 'confirmed')
                ->sum('amount_paid');

            $invoiceAmount = (float) ($invoice->amount ?? 0);

            if ($totalPaid >= $invoiceAmount && $invoiceAmount > 0) {
                $invoice->update([
                    'status'      => 'paid',
                    'paid_amount' => $totalPaid,
                    'paid_at'     => now(),
                ]);
            } else {
                $invoice->update([
                    'status'      => 'partial',
                    'paid_amount' => $totalPaid,
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update invoice status: ' . $e->getMessage());
        }
    }

    private function checkDeveloperProviderConfiguration($provider)
    {
        return match ($provider) {
            'paystack'    => !empty(env('DEVELOPER_PAYSTACK_SECRET_KEY')) && !empty(env('DEVELOPER_PAYSTACK_PUBLIC_KEY')),
            'expresspay'  => !empty(env('DEVELOPER_EXPRESSPAY_MERCHANT_ID')) && !empty(env('DEVELOPER_EXPRESSPAY_API_KEY')),
            'flutterwave' => !empty(env('DEVELOPER_FLUTTERWAVE_SECRET_KEY')) && !empty(env('DEVELOPER_FLUTTERWAVE_PUBLIC_KEY')),
            'hubtel'      => !empty(env('DEVELOPER_HUBTEL_CLIENT_ID')) && !empty(env('DEVELOPER_HUBTEL_CLIENT_SECRET')),
            default       => false,
        };
    }

    public function getOnlinePaymentProviders()
    {
        return [
            'paystack' => [
                'name'        => 'Paystack',
                'icon'        => 'fa-credit-card',
                'color'       => '#00B3E6',
                'description' => 'Pay with card or bank transfer',
                'available'   => $this->checkDeveloperProviderConfiguration('paystack'),
                'enabled'     => env('DEVELOPER_PAYSTACK_ENABLED', false),
            ],
            'expresspay' => [
                'name'        => 'ExpressPay',
                'icon'        => 'fa-bolt',
                'color'       => '#00A859',
                'description' => 'Fast mobile payments',
                'available'   => $this->checkDeveloperProviderConfiguration('expresspay'),
                'enabled'     => env('DEVELOPER_EXPRESSPAY_ENABLED', false),
            ],
            'flutterwave' => [
                'name'        => 'Flutterwave',
                'icon'        => 'fa-globe',
                'color'       => '#F5A623',
                'description' => 'Multiple payment options',
                'available'   => $this->checkDeveloperProviderConfiguration('flutterwave'),
                'enabled'     => env('DEVELOPER_FLUTTERWAVE_ENABLED', false),
            ],
            'hubtel' => [
                'name'        => 'Hubtel',
                'icon'        => 'fa-phone-alt',
                'color'       => '#E71D36',
                'description' => 'Mobile money & bank transfers',
                'available'   => $this->checkDeveloperProviderConfiguration('hubtel'),
                'enabled'     => env('DEVELOPER_HUBTEL_ENABLED', false),
            ],
        ];
    }

    /* ============================================================
     | WEBHOOK
     * ============================================================ */

    public function webhook(Request $request)
    {
        Log::info('Payment webhook received', ['payload' => $request->all()]);

        try {
            $provider = $request->get('provider') ?? $this->detectProviderFromWebhook($request);

            if (!$provider) {
                return response()->json(['status' => 'error', 'message' => 'Unknown provider'], 400);
            }

            if (!$this->verifyWebhookSignature($request, $provider)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }

            $reference = $this->getWebhookReference($request, $provider);
            if (!$reference) {
                return response()->json(['status' => 'error', 'message' => 'Missing reference'], 400);
            }

            $verificationResult = $this->verifyPaymentWithProvider($provider, $reference);
            if (!$verificationResult['success']) {
                return response()->json(['status' => 'error', 'message' => 'Verification failed'], 400);
            }

            // Match against a pending payment row (or session as fallback)
            $pendingPaymentRow = null;
            if (Schema::hasTable('agreement_payments')) {
                $pendingPaymentRow = DB::table('agreement_payments')
                    ->where('transaction_reference', $reference)
                    ->whereIn('status', ['pending_confirmation', 'pending'])
                    ->first();
            }

            if ($pendingPaymentRow) {
                DB::table('agreement_payments')
                    ->where('id', $pendingPaymentRow->id)
                    ->update([
                        'status'        => 'confirmed',
                        'confirmed_at'  => now(),
                        'provider_data' => json_encode($verificationResult['provider_data'] ?? []),
                    ]);

                $agreement = AdminBillingRecord::find($pendingPaymentRow->admin_billing_record_id);
                if ($agreement) {
                    $newAmountReceived = $agreement->amount_received + $verificationResult['amount'];
                    $agreement->update([
                        'amount_received' => $newAmountReceived,
                        'payment_status'  => $newAmountReceived >= $agreement->amount ? 'paid' : 'partial',
                    ]);

                    $this->updateInvoicePaymentStatus($agreement->id, $verificationResult['amount']);
                }
            } else {
                // Fallback — session-based pending payment
                $pendingPayment = session('pending_payment');
                if ($pendingPayment) {
                    $agreement = AdminBillingRecord::find($pendingPayment['agreement_id']);
                    if ($agreement) {
                        AgreementPayment::create([
                            'admin_billing_record_id' => $agreement->id,
                            'amount_paid'             => $verificationResult['amount'],
                            'payment_date'            => now(),
                            'payment_method'          => $provider,
                            'transaction_reference'   => $reference,
                            'notes'                   => 'Webhook-confirmed online payment',
                            'status'                  => 'confirmed',
                            'recorded_by'             => $pendingPayment['user_id'],
                            'confirmed_by'            => $pendingPayment['user_id'],
                            'confirmed_at'            => now(),
                            'recorded_at'             => now(),
                            'provider_data'           => json_encode($verificationResult['provider_data'] ?? []),
                        ]);

                        $agreement->update([
                            'amount_received' => $agreement->amount,
                            'payment_status'  => 'paid',
                        ]);

                        $this->updateInvoicePaymentStatus($agreement->id, $verificationResult['amount']);
                        session()->forget('pending_payment');
                    }
                }
            }

            Log::info('Webhook payment confirmed', [
                'reference' => $reference,
                'provider'  => $provider,
                'amount'    => $verificationResult['amount'],
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function detectProviderFromWebhook($request)
    {
        $data = $request->all();

        if (($data['event'] ?? null) === 'charge.success') return 'paystack';
        if (($data['status'] ?? null) === 'successful' && isset($data['data']['tx_ref'])) return 'flutterwave';
        if (isset($data['transactionId'], $data['status'])) return 'hubtel';
        if (isset($data['reference'], $data['status'])) return 'expresspay';

        return null;
    }

    private function verifyWebhookSignature($request, $provider)
    {
        switch ($provider) {
            case 'paystack':
                $signature = $request->header('X-Paystack-Signature');
                $secret    = env('DEVELOPER_PAYSTACK_SECRET_KEY');
                $payload   = $request->getContent();
                $computed  = hash_hmac('sha512', $payload, $secret);
                return hash_equals($computed, (string) $signature);

            case 'flutterwave':
                $signature = $request->header('Verif-Hash');
                $secret    = env('DEVELOPER_FLUTTERWAVE_SECRET_KEY');
                return $signature === $secret;

            case 'hubtel':
                $signature = $request->header('X-Hubtel-Signature');
                $secret    = env('DEVELOPER_HUBTEL_CLIENT_SECRET');
                $payload   = $request->getContent();
                $computed  = hash_hmac('sha256', $payload, $secret);
                return hash_equals($computed, (string) $signature);

            case 'expresspay':
                $apiKey   = $request->header('Authorization');
                $expected = 'Bearer ' . env('DEVELOPER_EXPRESSPAY_API_KEY');
                return $apiKey === $expected;

            default:
                return false;
        }
    }

    private function getWebhookReference($request, $provider)
    {
        $data = $request->all();

        return match ($provider) {
            'paystack'    => $data['data']['reference'] ?? null,
            'flutterwave' => $data['data']['tx_ref'] ?? null,
            'hubtel'      => $data['transactionId'] ?? null,
            'expresspay'  => $data['reference'] ?? null,
            default       => $request->get('reference'),
        };
    }

    /* ============================================================
     | INVOICE HELPERS
     * ============================================================ */

    private function getInvoiceFilePath(BillingInvoice $invoice)
    {
        $path = "invoices/{$invoice->id}/invoice.pdf";
        return Storage::exists($path) ? $path : null;
    }

    private function generateInvoicePdf(BillingInvoice $invoice)
    {
        try {
            $agreement = null;
            if (Schema::hasColumn('billing_invoices', 'agreement_id') && $invoice->agreement_id) {
                $agreement = AdminBillingRecord::find($invoice->agreement_id);
            } elseif (Schema::hasColumn('billing_invoices', 'admin_billing_record_id') && $invoice->admin_billing_record_id) {
                $agreement = AdminBillingRecord::find($invoice->admin_billing_record_id);
            }

            $superAdmin = $invoice->superAdmin;

            $data = [
                'invoice'         => $invoice,
                'agreement'       => $agreement,
                'superAdmin'      => $superAdmin,
                'currency'        => $invoice->currency ?? 'GHS',
                'date'            => now()->format('F j, Y'),
                'line_items'      => $invoice->items ?? [
                    [
                        'description' => $invoice->description ?? 'Invoice Item',
                        'quantity'    => 1,
                        'unit_price'  => $invoice->amount,
                        'total'       => $invoice->amount,
                    ],
                ],
                'is_primary'      => $agreement && $agreement->is_primary_for_billing,
                'billing_contact' => $agreement ? [
                    'name'  => $agreement->billing_contact_name,
                    'email' => $agreement->billing_contact_email,
                    'phone' => $agreement->billing_contact_phone,
                ] : null,
            ];

            $directory = "invoices/{$invoice->id}";
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory, 0755, true);
            }

            $path = "{$directory}/invoice.pdf";

            if ($this->pdfService && method_exists($this->pdfService, 'generateInvoicePdf')) {
                return $this->pdfService->generateInvoicePdf($data, $path);
            }

            // Fallback — write an HTML placeholder if PDF generation isn't available
            Storage::put($path, '<html><body><h1>Invoice ' . e($invoice->invoice_number) . '</h1></body></html>');

            return $path;

        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getPaymentMethodDetails($agreement)
    {
        $details = [
            'method'       => $agreement->payment_method ?? 'bank_transfer',
            'method_label' => $this->getPaymentMethodLabel($agreement->payment_method ?? 'bank_transfer'),
            'display_text' => '',
            'fields'       => [],
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
                    'Bank Name'      => $agreement->payment_bank_name ?? 'Not specified',
                    'Account Number' => $agreement->payment_account_number ?? 'Not specified',
                    'Account Name'   => $agreement->payment_account_name ?? 'Not specified',
                    'Bank Branch'    => $agreement->payment_bank_branch ?? 'Not specified',
                ];
                break;

            case 'mobile_money':
            case 'mtn':
            case 'telecel':
            case 'airteltigo':
                $networkLabels = [
                    'mtn'          => 'MTN Mobile Money',
                    'telecel'      => 'Telecel (Vodafone) Cash',
                    'airteltigo'   => 'AirtelTigo Money',
                    'mobile_money' => 'Mobile Money',
                ];
                $networkName = $networkLabels[$agreement->payment_method] ?? ucfirst($agreement->payment_method);

                $details['display_text'] = sprintf(
                    '%s - %s (%s)',
                    $networkName,
                    $agreement->payment_mobile_number ?? 'N/A',
                    $agreement->payment_account_name ?? 'N/A'
                );
                $details['fields'] = [
                    'Network'       => $networkName,
                    'Mobile Number' => $agreement->payment_mobile_number ?? 'Not specified',
                    'Account Name'  => $agreement->payment_account_name ?? 'Not specified',
                ];
                break;

            case 'cash':
                $details['display_text'] = 'Cash Payment';
                $details['fields'] = [
                    'Payment Type' => 'Cash Payment',
                    'Payable To'   => $agreement->payment_account_name ?? 'Developer',
                ];
                break;

            case 'check':
                $details['display_text'] = 'Check Payment';
                $details['fields'] = [
                    'Payment Type' => 'Check Payment',
                    'Payable To'   => $agreement->payment_account_name ?? 'Developer',
                ];
                break;

            default:
                $details['display_text'] = ucfirst(str_replace('_', ' ', $agreement->payment_method ?? 'Standard Payment'));
                $details['fields'] = ['Payment Method' => $details['display_text']];
                break;
        }

        return $details;
    }

    private function getPaymentMethodLabel($method)
    {
        $methods = [
            'bank_transfer' => 'Bank Transfer',
            'mobile_money'  => 'Mobile Money',
            'mtn'           => 'MTN Mobile Money',
            'telecel'       => 'Telecel (Vodafone) Cash',
            'airteltigo'    => 'AirtelTigo Money',
            'cash'          => 'Cash',
            'check'         => 'Check',
            'paystack'      => 'Paystack',
            'expresspay'    => 'ExpressPay',
            'flutterwave'   => 'Flutterwave',
            'hubtel'        => 'Hubtel',
        ];
        return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    public function getPaymentMethodsConfiguration()
    {
        return [
            'bank_transfer' => [
                'name'        => 'Bank Transfer',
                'icon'        => 'fa-university',
                'fields'      => ['account_name', 'account_number', 'bank_name', 'bank_branch'],
                'description' => 'Direct bank transfer',
            ],
            'mobile_money' => [
                'name'        => 'Mobile Money',
                'icon'        => 'fa-mobile-alt',
                'fields'      => ['mobile_number', 'mobile_network', 'account_name'],
                'description' => 'Mobile money payment',
            ],
            'cash' => [
                'name'        => 'Cash',
                'icon'        => 'fa-money-bill',
                'fields'      => [],
                'description' => 'Cash payment',
            ],
            'check' => [
                'name'        => 'Check',
                'icon'        => 'fa-file-invoice',
                'fields'      => ['account_name'],
                'description' => 'Check payment',
            ],
        ];
    }

    /* ============================================================
     | AUTO-INVOICE AFTER SIGNING
     * ============================================================ */

    /**
     * Generate the recurring invoice via the shared DeveloperBillingService
     * so the super-admin and developer flows produce identical records.
     */
    private function generateInvoiceAfterSigning($developerSettings, $agreement)
    {
        try {
            $service = $this->billingService ?: app(DeveloperBillingService::class);
            $invoice = $service->generateRecurringInvoice($developerSettings);

            if ($invoice) {
                $this->scheduleInvoiceRemindersForAgreement($invoice, $developerSettings, $agreement);
            }

            return $invoice;
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice after signing: ' . $e->getMessage(), [
                'agreement_id' => $agreement->id ?? null,
            ]);
            return null;
        }
    }

    /**
     * Schedule InvoiceReminder rows for the configured reminder intervals.
     */
    private function scheduleInvoiceRemindersForAgreement($invoice, $developerSettings, $agreement): void
    {
        if (!Schema::hasTable('invoice_reminders')) {
            return;
        }

        $rules = $developerSettings->billing_rules;
        if (is_string($rules)) {
            $rules = json_decode($rules, true) ?: [];
        }
        if (!is_array($rules)) {
            $rules = [];
        }

        if (!($rules['send_payment_reminders'] ?? true)) {
            return;
        }

        $reminderDays = is_array($rules['reminder_days_before'] ?? null)
            ? $rules['reminder_days_before']
            : [7, 3, 1];

        $dueDate = $invoice->due_date
            ? Carbon::parse($invoice->due_date)
            : Carbon::now()->addDays(30);

        $today = Carbon::today();

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
                ['invoice_id' => $invoice->id, 'days_before_due' => $days],
                [
                    'developer_setting_id' => $developerSettings->id,
                    'super_admin_id'       => $agreement->super_admin_id,
                    'scheduled_for'        => $scheduledFor->toDateString(),
                    'status'               => 'pending',
                    'channel'              => 'email',
                ]
            );
        }
    }

    /* ============================================================
     | SIGNATURE DOWNLOADS / VERIFICATION
     * ============================================================ */

    public function downloadSignature($signatureId)
    {
        try {
            $signature = AgreementSignature::with(['user', 'agreement'])->findOrFail($signatureId);
            $user      = auth()->user();

            if ($signature->agreement->super_admin_id !== $user->id && $signature->user_id !== $user->id) {
                abort(403, 'Unauthorized to download this signature');
            }

            if (!$signature->signature_path || !Storage::exists($signature->signature_path)) {
                throw new \Exception('Signature file not found');
            }

            $filename = "Signature-{$signature->signature_name}-{$signature->signature_date->format('Y-m-d')}.png";

            $this->logAudit('signature_downloaded', 'Signature file downloaded', [
                'signature_id' => $signatureId,
                'user_id'      => $user->id,
            ]);

            return response()->download(
                storage_path('app/' . $signature->signature_path),
                $filename
            )->deleteFileAfterSend(false);

        } catch (\Exception $e) {
            Log::error('Failed to download signature: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download signature: ' . $e->getMessage());
        }
    }

    public function verifySignature($signatureId)
    {
        try {
            $signature = AgreementSignature::with(['user', 'agreement'])->findOrFail($signatureId);
            $user      = auth()->user();

            if ($signature->agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to verify this signature');
            }

            $verificationData = [
                'signature_id'        => $signature->id,
                'signature_name'      => $signature->signature_name,
                'signature_date'      => $signature->signature_date->format('Y-m-d H:i:s'),
                'signature_type'      => $signature->signature_type,
                'user_name'           => $signature->user->name ?? 'Unknown',
                'user_email'          => $signature->user->email ?? 'N/A',
                'agreement_number'    => $signature->agreement->agreement_number,
                'digital_hash'        => $signature->digital_hash,
                'verified_at'         => now()->format('Y-m-d H:i:s'),
                'verification_method' => 'digital_hash',
                'is_valid'            => !empty($signature->digital_hash),
            ];

            $verificationHash = hash('sha256', json_encode($verificationData) . Str::random(32));

            $this->logAudit('signature_verified', 'Signature digitally verified', [
                'signature_id'      => $signatureId,
                'user_id'           => $user->id,
                'verification_hash' => $verificationHash,
                'is_valid'          => $verificationData['is_valid'],
            ]);

            return response()->json([
                'success'           => true,
                'verification'      => $verificationData,
                'verification_hash' => $verificationHash,
                'timestamp'         => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to verify signature: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Signature verification failed',
            ], 500);
        }
    }

    public function downloadSignedAgreement($agreementId)
{
    try {
        $agreement = AdminBillingRecord::with(['developerSetting'])->findOrFail($agreementId);
        $user      = auth()->user();

        if ($agreement->super_admin_id !== $user->id) {
            abort(403, 'Unauthorized to download this agreement');
        }

        if ($agreement->status !== 'active') {
            return redirect()->back()->with('error', 'Agreement is not active yet');
        }

        // ---- Regenerate if missing or corrupt ----
        $needsRegen = true;
        if ($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path)) {
            $needsRegen = !$this->isValidPdf(Storage::path($agreement->signed_agreement_pdf_path));
        }

        if ($needsRegen) {
            if ($agreement->signed_agreement_pdf_path && Storage::exists($agreement->signed_agreement_pdf_path)) {
                Storage::delete($agreement->signed_agreement_pdf_path);
            }

            $this->generateFinalAgreementPdf($agreementId);
            $agreement->refresh();
        }

        if (!$agreement->signed_agreement_pdf_path || !Storage::exists($agreement->signed_agreement_pdf_path)) {
            throw new \Exception('Signed agreement PDF could not be generated');
        }

        $this->logAudit('signed_agreement_downloaded', 'Signed agreement PDF downloaded by super admin', [
            'agreement_id'   => $agreementId,
            'super_admin_id' => $user->id,
            'is_primary'     => $agreement->is_primary_for_billing ?? false,
        ]);

        return response()->download(
            storage_path('app/' . $agreement->signed_agreement_pdf_path),
            "Signed-Agreement-{$agreement->agreement_number}.pdf",
            ['Content-Type' => 'application/pdf']
        )->deleteFileAfterSend(false);

    } catch (\Exception $e) {
        Log::error('Failed to download signed agreement: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to download signed agreement: ' . $e->getMessage());
    }
}

/**
 * Quick check: does the file at $path start with the PDF magic bytes "%PDF-"?
 * Any file that isn't a valid PDF will fail this check, and will be regenerated.
 */
private function isValidPdf(string $path): bool
{
    if (!file_exists($path)) {
        return false;
    }

    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return false;
    }

    $header = fread($handle, 5);
    fclose($handle);

    return $header === '%PDF-';
}

    /* ============================================================
     | TERMS AGREEMENT / REJECTION
     * ============================================================ */

    public function agreeToBillingTerms(Request $request, $agreementId)
    {
        try {
            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $user      = auth()->user();

            if ($agreement->super_admin_id !== $user->id) {
                return redirect()->back()->with('error', 'Unauthorized to agree to this agreement');
            }

            if ($agreement->status !== 'pending') {
                return redirect()->back()->with('error', 'Agreement is not pending approval');
            }

            $agreement->update([
                'status'    => 'active',
                'agreed_at' => now(),
                'agreed_by' => $user->id,
            ]);

            $this->logAudit('agreement_agreed', 'Super Admin agreed to billing terms', [
                'agreement_id'   => $agreementId,
                'super_admin_id' => $user->id,
                'is_primary'     => $agreement->is_primary_for_billing ?? false,
            ]);

            return redirect()->route('superadmin.billing.dashboard')
                ->with('success', 'You have agreed to the billing terms successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to agree to billing terms: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to agree to billing terms: ' . $e->getMessage());
        }
    }

    public function rejectBillingTerms(Request $request, $agreementId)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please provide a rejection reason');
        }

        try {
            $agreement = AdminBillingRecord::findOrFail($agreementId);
            $user      = auth()->user();

            if ($agreement->super_admin_id !== $user->id) {
                return redirect()->back()->with('error', 'Unauthorized to reject this agreement');
            }

            if ($agreement->status !== 'pending') {
                return redirect()->back()->with('error', 'Agreement is not pending approval');
            }

            $data = $validator->validated();

            $agreement->update([
                'status'           => 'rejected',
                'rejection_reason' => $this->sanitizeInput($data['rejection_reason']),
                'rejected_at'      => now(),
                'rejected_by'      => $user->id,
            ]);

            $this->logAudit('agreement_rejected', 'Super Admin rejected billing terms', [
                'agreement_id'   => $agreementId,
                'super_admin_id' => $user->id,
                'is_primary'     => $agreement->is_primary_for_billing ?? false,
                'reason'         => $data['rejection_reason'],
            ]);

            return redirect()->route('superadmin.billing.dashboard')
                ->with('success', 'You have rejected the billing terms.');

        } catch (\Exception $e) {
            Log::error('Failed to reject billing terms: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to reject billing terms: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | PAYMENT VIEWS
     * ============================================================ */

    public function viewPaymentDetails($paymentId)
    {
        try {
            $payment = AgreementPayment::with(['agreement', 'recordedByUser', 'confirmedByUser'])
                ->findOrFail($paymentId);

            $user = auth()->user();

            if ($payment->agreement->super_admin_id !== $user->id) {
                abort(403, 'Unauthorized to view this payment');
            }

            $this->logAudit('payment_details_viewed', 'Payment details viewed by super admin', [
                'payment_id'     => $paymentId,
                'super_admin_id' => $user->id,
            ]);

            return view('superadmin.billing.payment-details', compact('payment'));

        } catch (\Exception $e) {
            Log::error('Failed to view payment details: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payment details: ' . $e->getMessage());
        }
    }

    public function sharedPaymentStatus(Request $request)
    {
        $user = auth()->user();

        try {
            if (!Schema::hasTable('super_admin_payment_records')) {
                return redirect()->back()->with('error', 'Payment tracking system is not yet configured. Please contact support.');
            }

            $isPrimary = AdminBillingRecord::where('super_admin_id', $user->id)
                ->where('is_primary_for_billing', true)
                ->exists();

            if (!$isPrimary) {
                return redirect()->back()->with('error', 'Only the primary billing contact can view shared payment status.');
            }

            $developerSettings = DeveloperSetting::first();
            $billingMonth = $request->get('billing_month', Carbon::now()->format('Y-m'));

            $allSuperAdmins = User::where('type', User::TYPE_SUPER_ADMIN)
                ->where('status', User::STATUS_ACTIVE)
                ->get(['id', 'name', 'email']);

            $paymentStatus = [];
            $totalPaid = 0;

            foreach ($allSuperAdmins as $superAdmin) {
                $agreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                    ->where('super_admin_id', $superAdmin->id)
                    ->first();

                $paidAmount = 0;
                if ($agreement) {
                    try {
                        $paidAmount = SuperAdminPaymentRecord::where('agreement_id', $agreement->id)
                            ->where('billing_month', $billingMonth)
                            ->sum('amount_paid');
                        $totalPaid += $paidAmount;
                    } catch (\Exception $e) {
                        Log::warning('Could not calculate paid amount for super admin: ' . $e->getMessage());
                    }
                }

                $paymentStatus[] = [
                    'super_admin' => $superAdmin,
                    'agreement'   => $agreement,
                    'paid_amount' => $paidAmount,
                    'is_primary'  => $agreement && $agreement->is_primary_for_billing,
                ];
            }

            $primaryAgreement = AdminBillingRecord::where('developer_setting_id', $developerSettings->id)
                ->where('is_primary_for_billing', true)
                ->first();

            $totalDue     = $primaryAgreement ? $primaryAgreement->amount : 0;
            $remainingDue = max(0, $totalDue - $totalPaid);

            $this->logAudit('shared_payment_status_viewed', 'Shared payment status viewed by primary super admin', [
                'super_admin_id' => $user->id,
                'billing_month'  => $billingMonth,
                'total_paid'     => $totalPaid,
                'total_due'      => $totalDue,
            ]);

            return view('superadmin.billing.shared-payment-status', compact(
                'paymentStatus',
                'totalPaid',
                'totalDue',
                'remainingDue',
                'billingMonth',
                'primaryAgreement'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to load shared payment status: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading shared payment status: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | REPORTS / HISTORY / EXPORT
     * ============================================================ */

    public function billingReports(Request $request)
    {
        $user = auth()->user();
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to view billing reports');
        }

        try {
            $developerSettings = DeveloperSetting::first();
            if (!$developerSettings) {
                return redirect()->back()->with('error', 'System billing not configured');
            }

            $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
            $endDate   = $request->get('end_date', now()->endOfMonth()->toDateString());

            $validator = Validator::make(
                ['start_date' => $startDate, 'end_date' => $endDate],
                [
                    'start_date' => 'date',
                    'end_date'   => 'date|after_or_equal:start_date|before_or_equal:today',
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Invalid date range');
            }

            $agreements = AdminBillingRecord::where('super_admin_id', $user->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->with(['developerSetting', 'payments' => function ($q) {
                    $q->where('status', 'confirmed');
                }])
                ->get();

            $payments = collect();
            if (Schema::hasTable('agreement_payments')) {
                $payments = DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.super_admin_id', $user->id)
                    ->where('p.status', 'confirmed')
                    ->whereBetween('p.payment_date', [$startDate, $endDate])
                    ->select('p.*', 'a.agreement_number', 'a.is_primary_for_billing')
                    ->orderBy('p.payment_date', 'desc')
                    ->get();
            }

            $isPrimary = $agreements->contains('is_primary_for_billing', true);
            $sharedPayments = collect();
            if ($isPrimary && Schema::hasTable('super_admin_payment_records')) {
                try {
                    $sharedPayments = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                        ->whereBetween('payment_date', [$startDate, $endDate])
                        ->with('superAdmin', 'agreement')
                        ->get();
                } catch (\Exception $e) {
                    Log::warning('Could not load shared payments: ' . $e->getMessage());
                }
            }

            $invoices = collect();
            try {
                $developerSettingIds = AdminBillingRecord::where('super_admin_id', $user->id)
                    ->pluck('developer_setting_id')
                    ->unique()
                    ->filter()
                    ->values()
                    ->toArray();

                if (!empty($developerSettingIds)) {
                    $invoices = BillingInvoice::whereIn('developer_setting_id', $developerSettingIds)
                        ->whereBetween('issue_date', [$startDate, $endDate])
                        ->orderBy('issue_date', 'desc')
                        ->get()
                        ->map(function ($invoice) use ($agreements) {
                            $invoice->is_primary_invoice = $agreements->contains(function ($a) use ($invoice) {
                                return $a->id === $invoice->agreement_id && $a->is_primary_for_billing;
                            });
                            return $invoice;
                        });
                }
            } catch (\Exception $e) {
                Log::warning('Could not load invoices for reports: ' . $e->getMessage());
            }

            $stats = [
                'total_agreements'       => $agreements->count(),
                'active_agreements'      => $agreements->where('status', 'active')->count(),
                'pending_agreements'     => $agreements->where('status', 'pending')->count(),
                'completed_agreements'   => $agreements->where('status', 'completed')->count(),
                'total_amount_agreed'    => $agreements->where('status', 'active')->sum('amount'),
                'total_amount_paid'      => $agreements->where('status', 'active')->sum('amount_received'),
                'total_payments'         => $payments->count(),
                'total_payment_amount'   => $payments->sum('amount_paid'),
                'total_invoices'         => $invoices->count(),
                'total_invoice_amount'   => $invoices->sum('amount'),
                'paid_invoices'          => $invoices->where('status', 'paid')->count(),
                'pending_invoices'       => $invoices->where('status', 'pending')->count(),
                'overdue_invoices'       => $invoices->where('status', 'overdue')->count(),
                'pending_payments'       => $agreements->where('status', 'active')->sum('amount')
                    - $agreements->where('status', 'active')->sum('amount_received'),
                'is_primary'             => $isPrimary,
                'shared_payment_total'   => $sharedPayments->sum('amount_paid'),
            ];

            $monthlyBreakdown       = $this->getMonthlyBreakdownForSuperAdmin($user->id, $startDate, $endDate);
            $paymentMethodBreakdown = $this->getPaymentMethodBreakdownForSuperAdmin($user->id, $startDate, $endDate);

            $this->logAudit('billing_reports_viewed', 'Billing reports viewed by super admin', [
                'super_admin_id' => $user->id,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'is_primary'     => $isPrimary,
            ]);

            return view('superadmin.billing.reports', compact(
                'stats',
                'agreements',
                'payments',
                'sharedPayments',
                'invoices',
                'monthlyBreakdown',
                'paymentMethodBreakdown',
                'startDate',
                'endDate',
                'isPrimary'
            ));

        } catch (\Exception $e) {
            Log::error('Failed to generate billing reports: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error generating reports: ' . $e->getMessage());
        }
    }

    public function paymentHistory(Request $request)
    {
        $user = auth()->user();
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to view payment history');
        }

        try {
            $payments = collect();
            if (Schema::hasTable('agreement_payments')) {
                $payments = DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.super_admin_id', $user->id)
                    ->when($request->filled('status'), function ($query) use ($request) {
                        return $query->where('p.status', $request->status);
                    })
                    ->when($request->filled('start_date') && $request->filled('end_date'), function ($query) use ($request) {
                        return $query->whereBetween('p.payment_date', [$request->start_date, $request->end_date]);
                    })
                    ->select('p.*', 'a.agreement_number', 'a.description', 'a.is_primary_for_billing')
                    ->orderBy('p.payment_date', 'desc')
                    ->paginate($request->per_page ?? 20)
                    ->withQueryString();
            }

            $stats = [
                'total_payments'      => $payments->total(),
                'confirmed_payments'  => $payments->where('status', 'confirmed')->count(),
                'pending_payments'    => $payments->where('status', 'pending_confirmation')->count(),
                'total_amount_paid'   => $payments->where('status', 'confirmed')->sum('amount_paid'),
                'pending_amount'      => $payments->where('status', 'pending_confirmation')->sum('amount_paid'),
            ];

            $this->logAudit('payment_history_viewed', 'Payment history viewed by super admin', [
                'super_admin_id' => $user->id,
            ]);

            return view('superadmin.billing.payment-history', compact('payments', 'stats'));

        } catch (\Exception $e) {
            Log::error('Failed to load payment history: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error loading payment history: ' . $e->getMessage());
        }
    }

    public function exportBillingData(Request $request)
    {
        $user = auth()->user();
        if ($user->type !== User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized to export billing data');
        }

        try {
            $exportType = $request->get('export_type', 'agreements');
            $startDate  = $request->get('start_date', now()->subMonth()->toDateString());
            $endDate    = $request->get('end_date', now()->toDateString());

            $fileName = 'superadmin-billing-export-' . $exportType . '-' . date('Y-m-d-H-i-s') . '.csv';

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$fileName",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0",
            ];

            switch ($exportType) {
                case 'agreements':
                    $data = AdminBillingRecord::where('super_admin_id', $user->id)
                        ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                        ->with('developerSetting')
                        ->get();

                    $columns = [
                        'Agreement Number', 'Is Primary', 'Billing Contact', 'Amount', 'Currency',
                        'Status', 'Payment Status', 'Created Date', 'Start Date', 'Due Date',
                        'Description', 'Payment Method', 'Amount Received', 'Developer',
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            $billingContact = '';
                            if ($item->is_primary_for_billing) {
                                $billingContact = sprintf('%s (%s)',
                                    $item->billing_contact_name ?? 'N/A',
                                    $item->billing_contact_email ?? 'N/A');
                            }

                            fputcsv($file, [
                                $item->agreement_number ?? 'N/A',
                                $item->is_primary_for_billing ? 'Yes' : 'No',
                                $billingContact,
                                number_format($item->amount, 2),
                                $item->currency,
                                ucfirst($item->status),
                                ucfirst($item->payment_status),
                                $item->created_at->format('Y-m-d'),
                                $item->start_date ? Carbon::parse($item->start_date)->format('Y-m-d') : 'N/A',
                                $item->due_date ? Carbon::parse($item->due_date)->format('Y-m-d') : 'N/A',
                                $item->description,
                                ucfirst(str_replace('_', ' ', $item->payment_method ?? 'N/A')),
                                number_format($item->amount_received, 2),
                                $item->developerSetting->developer_name ?? 'N/A',
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                case 'payments':
                    $data = DB::table('agreement_payments as p')
                        ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                        ->where('a.super_admin_id', $user->id)
                        ->whereBetween('p.payment_date', [$startDate, $endDate])
                        ->select('p.*', 'a.agreement_number', 'a.description', 'a.is_primary_for_billing')
                        ->get();

                    $columns = [
                        'Payment Date', 'Agreement Number', 'Is Primary Agreement', 'Amount Paid',
                        'Payment Method', 'Transaction Reference', 'Status', 'Recorded By',
                        'Confirmed By', 'Confirmed Date', 'Notes',
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            fputcsv($file, [
                                $item->payment_date,
                                $item->agreement_number ?? 'N/A',
                                $item->is_primary_for_billing ? 'Yes' : 'No',
                                number_format($item->amount_paid, 2),
                                ucfirst(str_replace('_', ' ', $item->payment_method)),
                                $item->transaction_reference ?? 'N/A',
                                ucfirst(str_replace('_', ' ', $item->status)),
                                $this->getUserName($item->recorded_by),
                                $this->getUserName($item->confirmed_by),
                                $item->confirmed_at ? Carbon::parse($item->confirmed_at)->format('Y-m-d') : 'N/A',
                                $item->notes ?? 'N/A',
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                case 'invoices':
                    $developerSettingIds = AdminBillingRecord::where('super_admin_id', $user->id)
                        ->pluck('developer_setting_id')
                        ->unique()
                        ->filter()
                        ->values()
                        ->toArray();

                    $data = collect();
                    if (!empty($developerSettingIds)) {
                        $data = BillingInvoice::whereIn('developer_setting_id', $developerSettingIds)
                            ->whereBetween('issue_date', [$startDate, $endDate])
                            ->with(['developerSetting'])
                            ->get();
                    }

                    $columns = [
                        'Invoice Number', 'Issue Date', 'Due Date', 'Amount', 'Paid Amount',
                        'Currency', 'Status', 'Invoice Type', 'Description', 'Payment Method',
                        'Paid At', 'Developer',
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            fputcsv($file, [
                                $item->invoice_number ?? 'N/A',
                                $item->issue_date ? Carbon::parse($item->issue_date)->format('Y-m-d') : 'N/A',
                                $item->due_date ? Carbon::parse($item->due_date)->format('Y-m-d') : 'N/A',
                                number_format((float) $item->amount, 2),
                                number_format((float) ($item->paid_amount ?? 0), 2),
                                $item->currency,
                                ucfirst($item->status),
                                ucfirst($item->invoice_type ?? 'N/A'),
                                $item->description ?? 'N/A',
                                ucfirst(str_replace('_', ' ', $item->payment_method ?? 'N/A')),
                                $item->paid_at ? Carbon::parse($item->paid_at)->format('Y-m-d') : 'N/A',
                                $item->developerSetting->developer_name ?? 'N/A',
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                case 'signatures':
                    $data = AgreementSignature::whereHas('agreement', function ($query) use ($user, $startDate, $endDate) {
                        $query->where('super_admin_id', $user->id)
                              ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                    })
                    ->with(['user', 'agreement'])
                    ->get();

                    $columns = [
                        'Signature ID', 'Agreement Number', 'Is Primary', 'User Name', 'User Type',
                        'Signature Name', 'Signature Date', 'Signature Type', 'Digital Hash',
                        'IP Address', 'Status', 'Created At',
                    ];

                    $callback = function () use ($data, $columns) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $columns);

                        foreach ($data as $item) {
                            fputcsv($file, [
                                $item->id,
                                $item->agreement->agreement_number ?? 'N/A',
                                $item->agreement->is_primary_for_billing ? 'Yes' : 'No',
                                $item->user->name ?? 'Unknown',
                                $item->signature_type === 'developer' ? 'Developer' : 'Super Admin',
                                $item->signature_name,
                                $item->signature_date->format('Y-m-d H:i:s'),
                                ucfirst($item->signature_format ?? 'typed'),
                                $item->digital_hash ?? 'N/A',
                                $item->ip_address,
                                ucfirst($item->status),
                                $item->created_at->format('Y-m-d H:i:s'),
                            ]);
                        }
                        fclose($file);
                    };
                    break;

                default:
                    return redirect()->back()->with('error', 'Invalid export type');
            }

            $this->logAudit('billing_data_exported', 'Billing data exported by super admin', [
                'export_type' => $exportType,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
            ]);

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Failed to export billing data: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error exporting billing data: ' . $e->getMessage());
        }
    }

    /* ============================================================
     | HELPERS — REPORTING
     * ============================================================ */

    private function getMonthlyBreakdownForSuperAdmin($superAdminId, $startDate, $endDate)
    {
        $breakdown = [];

        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);

        while ($current <= $end) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd   = $current->copy()->endOfMonth();

            $payments = 0;
            $invoices = 0;
            $sharedPayments = 0;

            if (Schema::hasTable('agreement_payments')) {
                try {
                    $payments = DB::table('agreement_payments as p')
                        ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                        ->where('a.super_admin_id', $superAdminId)
                        ->where('p.status', 'confirmed')
                        ->whereBetween('p.payment_date', [$monthStart, $monthEnd])
                        ->sum('p.amount_paid');
                } catch (\Exception $e) {
                    Log::warning('Could not calculate payments for monthly breakdown: ' . $e->getMessage());
                }
            }

            $isPrimary = AdminBillingRecord::where('super_admin_id', $superAdminId)
                ->where('is_primary_for_billing', true)
                ->exists();

            if ($isPrimary && Schema::hasTable('super_admin_payment_records')) {
                try {
                    $developerSettings = DeveloperSetting::first();
                    if ($developerSettings) {
                        $sharedPayments = SuperAdminPaymentRecord::where('developer_setting_id', $developerSettings->id)
                            ->whereBetween('payment_date', [$monthStart, $monthEnd])
                            ->sum('amount_paid');
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not calculate shared payments for monthly breakdown: ' . $e->getMessage());
                }
            }

            try {
                $developerSettingIds = AdminBillingRecord::where('super_admin_id', $superAdminId)
                    ->pluck('developer_setting_id')
                    ->unique()
                    ->filter()
                    ->values()
                    ->toArray();

                if (!empty($developerSettingIds)) {
                    $invoices = BillingInvoice::whereIn('developer_setting_id', $developerSettingIds)
                        ->whereBetween('issue_date', [$monthStart, $monthEnd])
                        ->sum('amount');
                }
            } catch (\Exception $e) {
                Log::warning('Could not load invoices for monthly breakdown: ' . $e->getMessage());
            }

            $breakdown[] = [
                'month'           => $current->format('F Y'),
                'total_payments'  => (float) $payments,
                'total_invoices'  => (float) $invoices,
                'shared_payments' => (float) $sharedPayments,
                'payment_count'   => $payments > 0 ? 1 : 0,
                'invoice_count'   => $invoices > 0 ? 1 : 0,
            ];

            $current->addMonth();
        }

        return $breakdown;
    }

    private function getPaymentMethodBreakdownForSuperAdmin($superAdminId, $startDate, $endDate)
    {
        $breakdown = collect();

        if (Schema::hasTable('agreement_payments')) {
            try {
                $breakdown = DB::table('agreement_payments as p')
                    ->join('admin_billing_records as a', 'p.admin_billing_record_id', '=', 'a.id')
                    ->where('a.super_admin_id', $superAdminId)
                    ->where('p.status', 'confirmed')
                    ->whereBetween('p.payment_date', [$startDate, $endDate])
                    ->select('p.payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_paid) as total_amount'))
                    ->groupBy('p.payment_method')
                    ->get()
                    ->map(function ($item) {
                        $methods = $this->getPaymentMethodsConfiguration();
                        $methodName = $methods[$item->payment_method]['name'] ?? ucfirst($item->payment_method);

                        return [
                            'payment_method' => $methodName,
                            'count'          => $item->count,
                            'total_amount'   => $item->total_amount,
                        ];
                    });
            } catch (\Exception $e) {
                Log::warning('Could not calculate payment method breakdown: ' . $e->getMessage());
            }
        }

        return $breakdown;
    }

    /* ============================================================
     | SIGNED AGREEMENT PDF + NOTIFICATIONS
     * ============================================================ */

    private function generateFinalAgreementPdf($agreementId)
    {
        try {
            $agreement = AdminBillingRecord::with(['developerSetting', 'signatures.user'])->findOrFail($agreementId);

            $pdfData = $this->prepareAgreementData($agreement);

            $pdfData['is_primary'] = $agreement->is_primary_for_billing ?? false;
            $pdfData['billing_contact'] = [
                'name'  => $agreement->billing_contact_name,
                'email' => $agreement->billing_contact_email,
                'phone' => $agreement->billing_contact_phone,
            ];

            if (isset($pdfData['agreement_terms']['amount'])) {
                $amount = $pdfData['agreement_terms']['amount'];
                if (is_string($amount)) {
                    $pdfData['agreement_terms']['amount'] = floatval(preg_replace('/[^0-9.-]/', '', $amount));
                }
            }

            $pdfData['signatures'] = $agreement->signatures->map(function ($signature) {
                return [
                    'user_name'      => $signature->user->name ?? 'Unknown',
                    'user_type'      => $signature->signature_type === 'developer' ? 'Developer' : 'Super Admin',
                    'signature_name' => $signature->signature_name,
                    'signature_date' => $signature->signature_date->format('F j, Y'),
                    'signature_path' => $signature->signature_path ? Storage::url($signature->signature_path) : null,
                    'digital_hash'   => $signature->digital_hash,
                ];
            });

            $pdfData['signing_completed_at']      = $agreement->signing_completed_at?->format('F j, Y \a\t g:i A');
            $pdfData['digital_verification_hash'] = hash('sha256',
                $agreementId .
                $agreement->agreement_number .
                optional($agreement->signing_completed_at)->timestamp .
                Str::random(32)
            );

            if ($this->pdfService && method_exists($this->pdfService, 'generateSignedAgreementPdf')) {
                $pdfPath = $this->pdfService->generateSignedAgreementPdf($pdfData);
            } else {
                $pdfPath = $this->generateSimpleSignedAgreementPdf($pdfData, $agreementId);
            }

            $agreement->update(['signed_agreement_pdf_path' => $pdfPath]);

            return $pdfPath;

        } catch (\Exception $e) {
            Log::error('Failed to generate final agreement PDF: ' . $e->getMessage());
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

        $html     = $this->createSignedAgreementHtml($pdfData);
        $pdf      = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'DejaVu Sans',
        ]);

        $filename = "signed_agreement_{$agreementId}_" . time() . '.pdf';
        $path     = "{$directory}/{$filename}";

        Storage::put($path, $pdf->output());

        return $path;
    } catch (\Exception $e) {
        Log::error('Failed to generate simple signed agreement: ' . $e->getMessage());
        throw $e;
    }
}

    private function createSignedAgreementHtml($pdfData)
    {
        $amount = $pdfData['agreement_terms']['amount'] ?? 0;
        if (is_string($amount)) {
            $amount = floatval(preg_replace('/[^0-9.-]/', '', $amount));
        }
        $formattedAmount = number_format($amount, 2);
        $currency        = $pdfData['agreement_terms']['currency'] ?? 'GHS';

        $primaryBadge = '';
        if (!empty($pdfData['is_primary'])) {
            $primaryBadge = '<div class="primary-badge">✓ PRIMARY BILLING CONTACT</div>';
        }

        $billingContactInfo = '';
        if (!empty($pdfData['billing_contact']['name'])) {
            $billingContactInfo = '
            <div class="section">
                <div class="section-title">Billing Contact Information</div>
                <p><strong>Name:</strong> ' . htmlspecialchars($pdfData['billing_contact']['name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($pdfData['billing_contact']['email'] ?? 'N/A') . '</p>
                <p><strong>Phone:</strong> ' . htmlspecialchars($pdfData['billing_contact']['phone'] ?? 'N/A') . '</p>
            </div>';
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>Signed Agreement ' . htmlspecialchars($pdfData['agreement_number']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .section { margin-bottom: 20px; }
                .section-title { font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 15px; }
                .signature-box { display: inline-block; width: 300px; margin: 20px; padding: 10px; border: 1px solid #000; }
                .signed-stamp { color: green; font-weight: bold; font-size: 18px; margin-top: 10px; }
                .primary-badge { background-color: #27ae60; color: white; padding: 8px 15px; border-radius: 5px; display: inline-block; margin: 10px 0; }
                .verification-info { background-color: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #007bff; }
                .hash { font-family: monospace; background-color: #eee; padding: 5px; word-break: break-all; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>SIGNED BILLING AGREEMENT</h1>
                <h2>Agreement Number: ' . htmlspecialchars($pdfData['agreement_number']) . '</h2>
                <p>Date: ' . ($pdfData['agreement_date'] ?? date('F j, Y')) . '</p>
                <p class="signed-stamp">✓ DIGITALLY SIGNED AND EXECUTED</p>
                ' . $primaryBadge . '
            </div>';

        $html .= '<div class="verification-info">
            <h3>Digital Verification</h3>
            <p><strong>Verification Hash:</strong></p>
            <p class="hash">' . ($pdfData['digital_verification_hash'] ?? 'N/A') . '</p>
            <p><strong>Signing Completed:</strong> ' . ($pdfData['signing_completed_at'] ?? 'N/A') . '</p>
            <p><em>This document has been digitally signed by all parties.</em></p>
        </div>';

        $html .= $billingContactInfo;
        $html .= '<div class="section"><div class="section-title">Digital Signatures</div>';

        foreach (($pdfData['signatures'] ?? []) as $signature) {
            $html .= '<div class="signature-box">
                <p><strong>' . htmlspecialchars($signature['user_type'] ?? 'Unknown') . ':</strong> ' . htmlspecialchars($signature['user_name'] ?? 'Unknown') . '</p>
                <p><strong>Signed Name:</strong> ' . htmlspecialchars($signature['signature_name'] ?? 'N/A') . '</p>
                <p><strong>Date Signed:</strong> ' . ($signature['signature_date'] ?? 'N/A') . '</p>
                <p><strong>Digital Hash:</strong></p>
                <p class="hash">' . ($signature['digital_hash'] ?? 'N/A') . '</p>
            </div>';
        }

        $html .= '</div>';

        $description       = $pdfData['agreement_terms']['description'] ?? 'No description provided';
        $billingFrequency  = ucfirst($pdfData['agreement_terms']['billing_frequency'] ?? 'monthly');
        $paymentDetails    = ucfirst(str_replace('_', ' ', $pdfData['agreement_terms']['payment_details'] ?? 'Bank Transfer'));

        $html .= '<div class="section">
            <div class="section-title">Agreement Terms (Original)</div>
            <p><strong>Amount:</strong> ' . $currency . ' ' . $formattedAmount . '</p>
            <p><strong>Description:</strong> ' . htmlspecialchars($description) . '</p>
            <p><strong>Billing Frequency:</strong> ' . htmlspecialchars($billingFrequency) . '</p>
            <p><strong>Payment Method:</strong> ' . htmlspecialchars($paymentDetails) . '</p>
            <p><strong>Signed on:</strong> ' . ($pdfData['signing_completed_at'] ?? date('F j, Y g:i A')) . '</p>
        </div>
        </body>
        </html>';

        return $html;
    }

    private function sendSignatureCompletionNotifications($agreementId)
    {
        try {
            $agreement = AdminBillingRecord::with(['superAdmin', 'developerSetting'])->findOrFail($agreementId);

            if ($agreement->superAdmin && $agreement->superAdmin->email) {
                $isPrimary = $agreement->is_primary_for_billing ?? false;
                $subject = $isPrimary
                    ? 'Primary Billing Agreement Signed - ' . $agreement->agreement_number
                    : 'Agreement Signed - ' . $agreement->agreement_number;

                if (view()->exists('emails.signature-completion-superadmin')) {
                    Mail::send('emails.signature-completion-superadmin', [
                        'agreement'  => $agreement,
                        'superAdmin' => $agreement->superAdmin,
                        'isPrimary'  => $isPrimary,
                    ], function ($message) use ($agreement, $subject) {
                        $message->to($agreement->superAdmin->email)->subject($subject);
                    });
                } else {
                    Mail::raw("Agreement {$agreement->agreement_number} has been signed and is now active.", function ($message) use ($agreement, $subject) {
                        $message->to($agreement->superAdmin->email)->subject($subject);
                    });
                }
            }

            $this->logAudit('signature_notifications_sent', 'Signature completion notifications sent', [
                'agreement_id'          => $agreementId,
                'super_admin_notified'  => !empty($agreement->superAdmin->email),
                'is_primary'            => $agreement->is_primary_for_billing ?? false,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send signature completion notifications: ' . $e->getMessage(), [
                'agreement_id' => $agreementId,
            ]);
        }
    }

    private function sendSignaturePendingNotification($agreementId, $pendingParty)
    {
        try {
            $agreement       = AdminBillingRecord::with(['developerSetting'])->findOrFail($agreementId);
            $developerEmail  = $agreement->developerSetting->developer_email ?? null;

            if ($developerEmail && $pendingParty === 'developer') {
                Mail::send('emails.signature-pending-developer', [
                    'agreement' => $agreement,
                    'isPrimary' => $agreement->is_primary_for_billing ?? false,
                ], function ($message) use ($agreement, $developerEmail) {
                    $message->to($developerEmail)->subject('Signature Pending - ' . $agreement->agreement_number);
                });

                $this->logAudit('signature_pending_notification_sent', 'Signature pending notification sent to developer', [
                    'agreement_id'  => $agreementId,
                    'pending_party' => $pendingParty,
                    'is_primary'    => $agreement->is_primary_for_billing ?? false,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send signature pending notification: ' . $e->getMessage());
        }
    }

    private function getUserName($userId)
    {
        if (!$userId) {
            return 'N/A';
        }
        $user = User::find($userId);
        return $user ? $user->name : 'N/A';
    }

    private function emptyStats(): array
    {
        return [
            'total_agreements'      => 0,
            'active_agreements'     => 0,
            'pending_agreements'    => 0,
            'completed_agreements'  => 0,
            'terminated_agreements' => 0,
            'total_amount_agreed'   => 0,
            'total_amount_paid'     => 0,
            'pending_payments'      => 0,
            'awaiting_signature'    => 0,
            'signed_agreements'     => 0,
            'is_primary'            => false,
            'shared_total_paid'     => 0,
            'shared_total_due'      => 0,
            'shared_remaining'      => 0,
        ];
    }

    /* ============================================================
 | STATISTICS / NOTIFICATIONS / AJAX HELPERS
 | ------------------------------------------------------------
 | Methods referenced by routes/web.php that were missing from
 | the controller. Adding them here keeps the routes working
 | without needing to strip them from the route file.
 * ============================================================ */

/**
 * Super admin billing statistics page.
 *
 * Route:  GET superadmin/billing/statistics  →  superadmin.billing.statistics
 * View:   resources/views/superadmin/billing/statistics.blade.php
 */
public function viewStatistics()
{
    try {
        $user = auth()->user();

        if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) {
            abort(403, 'Unauthorized access to billing statistics.');
        }

        $agreements = AdminBillingRecord::where('super_admin_id', $user->id)
            ->with(['developerSetting'])
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total_agreements'      => $agreements->count(),
            'active_agreements'     => $agreements->where('status', 'active')->count(),
            'pending_agreements'    => $agreements->where('status', 'pending')->count(),
            'completed_agreements'  => $agreements->where('status', 'completed')->count(),
            'terminated_agreements' => $agreements->where('status', 'terminated')->count(),

            'total_amount_agreed'   => $agreements->where('status', 'active')->sum('amount'),
            'total_amount_paid'     => $agreements->where('status', 'active')->sum('amount_received'),

            'signed_agreements'     => $agreements->whereNotNull('signed_agreement_pdf_path')->count(),
            'awaiting_signature'    => $agreements->where('status', 'pending')
                                                 ->whereNull('signed_agreement_pdf_path')
                                                 ->count(),
        ];

        $stats['total_outstanding'] = max(
            0,
            (float) $stats['total_amount_agreed'] - (float) $stats['total_amount_paid']
        );

        $settings = \App\Models\SystemSetting::getSettings();

        $this->logAudit('superadmin_billing_statistics_viewed', 'Super Admin billing statistics viewed', [
            'super_admin_id' => $user->id,
            'total_agreements' => $stats['total_agreements'],
        ]);

        if (view()->exists('superadmin.billing.statistics')) {
            return view('superadmin.billing.statistics', compact('agreements', 'stats', 'settings'));
        }

        // Fallback: redirect to dashboard with the same data in session
        return redirect()
            ->route('superadmin.billing.dashboard')
            ->with('info', 'Statistics summary: '
                . $stats['total_agreements'] . ' agreements, '
                . $stats['active_agreements'] . ' active, '
                . $stats['pending_agreements'] . ' pending.');
    } catch (\Throwable $e) {
        \Log::error('Failed to load billing statistics: ' . $e->getMessage(), [
            'user_id' => auth()->id(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return redirect()
            ->route('superadmin.billing.dashboard')
            ->with('error', 'Failed to load statistics: ' . $e->getMessage());
    }
}

/**
 * AJAX — return current payment status for an invoice.
 *
 * Route:  GET superadmin/billing/invoice/{invoiceId}/payment-status
 *         → superadmin.billing.get-payment-status
 */
public function getPaymentStatus(int $invoiceId)
{
    try {
        $user = auth()->user();

        if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $invoice = \App\Models\BillingInvoice::findOrFail($invoiceId);

        if ((int) $invoice->super_admin_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $totalPaid = (float) ($invoice->paid_amount ?? 0);
        $amount    = (float) ($invoice->amount ?? 0);
        $remaining = max(0, $amount - $totalPaid);

        return response()->json([
            'success'      => true,
            'status'       => $invoice->status,
            'is_paid'      => $invoice->status === 'paid' || $remaining <= 0,
            'amount'       => $amount,
            'paid_amount'  => $totalPaid,
            'remaining'    => $remaining,
            'currency'     => $invoice->currency ?? 'GHS',
            'updated_at'   => optional($invoice->updated_at)->toISOString(),
            'paid_at'      => optional($invoice->paid_at)->toISOString(),
            'timestamp'    => now()->toISOString(),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Failed to fetch invoice payment status', [
            'invoice_id' => $invoiceId,
            'error'      => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch payment status: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Download the signature certificate for a signature.
 *
 * Route:  GET superadmin/billing/signature/{signatureId}/certificate
 */
public function downloadSignatureCertificate(int $signatureId)
{
    try {
        $signature = \App\Models\AgreementSignature::with(['user', 'agreement'])
            ->findOrFail($signatureId);

        $user = auth()->user();

        if ((int) $signature->agreement->super_admin_id !== (int) $user->id) {
            abort(403, 'Unauthorized to download this certificate.');
        }

        $data = [
            'signature'   => $signature,
            'agreement'   => $signature->agreement,
            'signer'      => $signature->user,
            'generated_at' => now()->toISOString(),
            'generated_by' => $user->name,
            'certificate_id' => 'CERT-' . strtoupper(bin2hex(random_bytes(6))),
            'verification_hash' => hash('sha256',
                $signature->id . $signature->digital_hash . $signature->signature_date),
        ];

        $this->logAudit('signature_certificate_downloaded', 'Signature certificate downloaded', [
            'signature_id' => $signatureId,
            'user_id'      => $user->id,
        ]);

        if (!class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            throw new \Exception('DomPDF is not installed.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'superadmin.billing.signature-certificate',
            $data
        );
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('signature-certificate-' . $signature->id . '.pdf');
    } catch (\Throwable $e) {
        \Log::error('Failed to download signature certificate: ' . $e->getMessage());

        return redirect()
            ->back()
            ->with('error', 'Failed to download certificate: ' . $e->getMessage());
    }
}

/**
 * Download the invoice attached to an agreement.
 *
 * Route:  GET superadmin/billing/agreement/{agreementId}/download-invoice
 */
public function downloadAgreementInvoice(int $agreementId)
{
    try {
        $agreement = AdminBillingRecord::findOrFail($agreementId);
        $user      = auth()->user();

        if ((int) $agreement->super_admin_id !== (int) $user->id) {
            abort(403, 'Unauthorized to download this invoice.');
        }

        // Try to find the most recent invoice for this agreement
        $invoice = null;

        if (\Illuminate\Support\Facades\Schema::hasTable('billing_invoices')) {
            $query = \App\Models\BillingInvoice::query();

            if (\Illuminate\Support\Facades\Schema::hasColumn('billing_invoices', 'agreement_id')) {
                $query->where('agreement_id', $agreement->id);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('billing_invoices', 'admin_billing_record_id')) {
                $query->where('admin_billing_record_id', $agreement->id);
            }

            $invoice = $query->orderByDesc('created_at')->first();
        }

        if (!$invoice) {
            return redirect()->back()->with('error', 'No invoice found for this agreement.');
        }

        // Delegate to the existing downloadInvoice() method
        return $this->downloadInvoice($invoice->id);
    } catch (\Throwable $e) {
        \Log::error('Failed to download agreement invoice: ' . $e->getMessage());

        return redirect()->back()->with('error', 'Failed to download invoice: ' . $e->getMessage());
    }
}

/**
 * Send a test agreement email to the super admin.
 *
 * Route:  POST superadmin/billing/agreement/{agreementId}/send-test-email
 */
public function sendTestAgreementEmail(int $agreementId)
{
    try {
        $agreement = AdminBillingRecord::with(['developerSetting'])->findOrFail($agreementId);
        $user      = auth()->user();

        if ((int) $agreement->super_admin_id !== (int) $user->id) {
            abort(403, 'Unauthorized to send test email.');
        }

        try {
            \Mail::raw(
                "This is a test email for agreement #{$agreement->agreement_number}.\n\n"
                . "Amount: " . ($agreement->currency ?? 'GHS') . ' ' . number_format($agreement->amount, 2) . "\n"
                . "Status: " . ucfirst($agreement->status) . "\n"
                . "Sent at: " . now()->format('Y-m-d H:i:s'),
                function ($message) use ($user, $agreement) {
                    $message->to($user->email)
                        ->subject('Test Email — Agreement ' . $agreement->agreement_number);
                }
            );
        } catch (\Throwable $e) {
            \Log::warning('Test agreement email failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Test email failed: ' . $e->getMessage());
        }

        $this->logAudit('agreement_test_email_sent', 'Test email sent for agreement', [
            'agreement_id' => $agreement->id,
            'sent_to'      => $user->email,
        ]);

        return redirect()->back()->with('success', 'Test email sent to ' . $user->email);
    } catch (\Throwable $e) {
        \Log::error('Failed to send agreement test email: ' . $e->getMessage());

        return redirect()->back()->with('error', 'Failed to send test email: ' . $e->getMessage());
    }
}

/**
 * AJAX — unread notifications count for the current super admin.
 *
 * Route:  GET superadmin/billing/notifications/unread-count
 */
public function getUnreadNotificationsCount()
{
    try {
        $user = auth()->user();

        if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $count = 0;

        try {
            if (method_exists($user, 'unreadNotifications')) {
                $count = $user->unreadNotifications()->count();
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                $count = \DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('notifiable_type', get_class($user))
                    ->whereNull('read_at')
                    ->count();
            }
        } catch (\Throwable $e) {
            \Log::debug('Failed to count unread notifications: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'count'   => $count,
            'timestamp' => now()->toISOString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to count notifications: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * AJAX — recent notifications for the current super admin.
 *
 * Route:  GET superadmin/billing/notifications/recent
 */
public function getRecentNotifications()
{
    try {
        $user = auth()->user();

        if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $notifications = collect();

        try {
            if (method_exists($user, 'notifications')) {
                $notifications = $user->notifications()->latest()->limit(10)->get();
            } elseif (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                $notifications = \DB::table('notifications')
                    ->where('notifiable_id', $user->id)
                    ->where('notifiable_type', get_class($user))
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable $e) {
            \Log::debug('Failed to load recent notifications: ' . $e->getMessage());
        }

        return response()->json([
            'success'       => true,
            'notifications' => $notifications,
            'total'         => $notifications->count(),
            'timestamp'     => now()->toISOString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load notifications: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * AJAX — agreement details for modal display.
 *
 * Route:  GET superadmin/billing/agreements/{agreementId}/details
 */
public function getAgreementDetails(int $agreementId)
{
    try {
        $agreement = AdminBillingRecord::with(['developerSetting', 'superAdmin'])
            ->findOrFail($agreementId);

        $user = auth()->user();

        if ((int) $agreement->super_admin_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'agreement' => [
                'id'                 => $agreement->id,
                'agreement_number'   => $agreement->agreement_number,
                'status'             => $agreement->status,
                'payment_status'     => $agreement->payment_status,
                'amount'             => (float) $agreement->amount,
                'amount_received'    => (float) $agreement->amount_received,
                'currency'           => $agreement->currency,
                'billing_frequency'  => $agreement->billing_frequency,
                'description'        => $agreement->description,
                'start_date'         => optional($agreement->start_date)->format('Y-m-d'),
                'due_date'           => optional($agreement->due_date)->format('Y-m-d'),
                'is_primary_for_billing' => (bool) $agreement->is_primary_for_billing,
                'developer'          => [
                    'name'  => $agreement->developerSetting->developer_name ?? null,
                    'email' => $agreement->developerSetting->developer_email ?? null,
                ],
                'created_at'         => optional($agreement->created_at)->toISOString(),
                'updated_at'         => optional($agreement->updated_at)->toISOString(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load agreement details: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * AJAX — payment details for modal display.
 *
 * Route:  GET superadmin/billing/payments/{paymentId}/details
 */
public function getPaymentDetails(int $paymentId)
{
    try {
        $payment = \App\Models\AgreementPayment::with(['agreement'])
            ->findOrFail($paymentId);

        $user = auth()->user();

        if (!$payment->agreement || (int) $payment->agreement->super_admin_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'id'                    => $payment->id,
                'agreement_id'          => $payment->admin_billing_record_id,
                'amount_paid'           => (float) $payment->amount_paid,
                'payment_method'        => $payment->payment_method,
                'transaction_reference' => $payment->transaction_reference,
                'status'                => $payment->status,
                'payment_date'          => optional($payment->payment_date)->format('Y-m-d'),
                'confirmed_at'          => optional($payment->confirmed_at)->toISOString(),
                'recorded_at'           => optional($payment->recorded_at)->toISOString(),
                'notes'                 => $payment->notes,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load payment details: ' . $e->getMessage(),
        ], 500);
    }
}

}