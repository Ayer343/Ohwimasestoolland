<?php

namespace App\Http\Controllers;

use App\Models\SmsLog;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SmsController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    // ========================================== //
    // 📱 DASHBOARD & MAIN VIEWS                  //
    // ========================================== //

    /**
     * Display the SMS dashboard.
     */
    public function index()
    {
        $user = auth()->user();

        // Get system status
        $systemStatus = $this->smsService->getSystemStatus();
        $quickStatus  = $this->smsService->getQuickStatus();

        // Get usage statistics
        $usageStats = $this->smsService->getUsageStatistics();

        // Get recent logs
        $recentLogs = SmsLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get provider status
        $providers = $this->smsService->getAllProvidersWithStatus();

        // Get default provider
        $defaultProvider = $this->smsService->getDefaultProvider();

        // ----------------------------------------------------------
        // Effective sender ID — resolved server-side. Passed to the
        // view for informational display only. The composer never
        // edits this; sending uses the resolver chain internally.
        // ----------------------------------------------------------
        $effectiveSenderId       = null;
        $effectiveSenderIdSource = null;

        try {
            if ($defaultProvider) {
                $effectiveSenderId       = $this->smsService->resolveSenderId($defaultProvider);
                $effectiveSenderIdSource = $this->smsService->explainSenderId($defaultProvider)['source'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to resolve effective sender ID for dashboard: ' . $e->getMessage());
        }

        return view('sms.index', compact(
            'systemStatus',
            'quickStatus',
            'usageStats',
            'recentLogs',
            'providers',
            'defaultProvider',
            'effectiveSenderId',
            'effectiveSenderIdSource'
        ));
    }

    /**
     * Show the SMS compose form with user selection.
     */
    public function compose()
    {
        $user = auth()->user();

        // Check if system is ready
        if (!$this->smsService->isConfigured()) {
            return redirect()->route('sms.index')
                ->with('error', 'SMS system is not configured. Please contact your administrator.');
        }

        // Determine user capabilities
        $isDeveloper  = $user->isDeveloper();
        $isAdmin      = $user->isAdmin();
        $isSuperAdmin = $user->isSuperAdmin();
        $isLandlord   = $user->isLandlord();
        $isTenant     = $user->isTenant();

        // Determine if user can select recipients
        $canSelectUsers = $isSuperAdmin || $isAdmin || $isLandlord;
        $canBulkSend    = $isSuperAdmin || $isAdmin;

        // Get providers
        $providers       = $this->smsService->getAvailableProviders();
        $defaultProvider = $this->smsService->getDefaultProvider();

        // Get user types for filtering
        $userTypes = [
            'all'                  => 'All Users',
            'landlord'             => 'Landlords',
            'tenant'               => 'Tenants',
            'field_agent'          => 'Field Agents',
            'security_personnel'   => 'Security Personnel',
            'contractor'           => 'Contractors',
            'sanitation_personnel' => 'Sanitation Personnel',
            'admin'                => 'Admins',
            'super_admin'          => 'Super Admins',
        ];

        // Get properties for landlords to filter tenants
        $properties = $isLandlord ? $user->properties : collect();

        // Get recent SMS logs for quick reference
        $recentLogs = SmsLog::where('user_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        // SMS character limits
        $maxSmsLength = 1600; // 10x GSM-7 segments
        $gsm7Length   = 160;  // Standard GSM-7 character limit per segment

        // Get SMS status for the status banner
        $smsStatus = $this->smsService->getQuickStatus();

        // ----------------------------------------------------------
        // Effective Sender ID — resolved server-side via the
        // resolver chain. Shown read-only in the composer.
        //
        //   per-provider (.env) → global (DB) → system fallback
        //
        // The composer NEVER edits this. Sending uses the resolver
        // internally, so any user-submitted sender_id is ignored.
        // ----------------------------------------------------------
        $effectiveSenderId       = null;
        $effectiveSenderIdSource = null;

        try {
            $providerKey = $defaultProvider ?? config('sms.default');

            if ($providerKey) {
                $effectiveSenderId       = $this->smsService->resolveSenderId($providerKey);
                $effectiveSenderIdSource = $this->smsService->explainSenderId($providerKey)['source'] ?? null;
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to resolve effective sender ID for composer: ' . $e->getMessage());
        }

        return view('sms.compose', compact(
            'providers',
            'defaultProvider',
            'canSelectUsers',
            'canBulkSend',
            'userTypes',
            'properties',
            'recentLogs',
            'maxSmsLength',
            'gsm7Length',
            'smsStatus',
            'effectiveSenderId',
            'effectiveSenderIdSource'
        ));
    }

    /**
     * Get the list of users available for SMS.
     *
     * Accessible to Super Admins, Admins, Landlords, and Developers.
     */
    public function getUserListForSms(Request $request)
    {
        $currentUser = auth()->user();

        // ✅ Authorization: Allow Super Admin, Admin, Landlord, and Developer
        if (!$currentUser->isSuperAdmin()
            && !$currentUser->isAdmin()
            && !$currentUser->isLandlord()
            && !$currentUser->isDeveloper()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only administrators, landlords, and developers can access user lists.',
            ], 403);
        }

        try {
            // ✅ Build the query - only users with phone numbers
            $query = User::whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('type', '!=', User::TYPE_DEVELOPER);

            // ✅ Allow Developers and Super Admins to see Super Admins
            if (!$currentUser->isSuperAdmin() && !$currentUser->isDeveloper()) {
                $query->where('type', '!=', User::TYPE_SUPER_ADMIN);
            }

            // ✅ For landlords, only show their tenants
            if ($currentUser->isLandlord()
                && !$currentUser->isAdmin()
                && !$currentUser->isSuperAdmin()
                && !$currentUser->isDeveloper()
            ) {
                $tenantIds = $currentUser->tenants()->pluck('id')->toArray();
                $query->whereIn('id', $tenantIds);
            }

            // ✅ Filter by user type if provided
            if ($request->filled('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            // ✅ Filter by role if provided
            if ($request->filled('role') && $request->role !== 'all') {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('slug', $request->role);
                });
            }

            // ✅ Filter by property if provided (for landlords)
            if ($request->filled('property_id')) {
                $query->whereHas('properties', function ($q) use ($request) {
                    $q->where('id', $request->property_id);
                });
            }

            // ✅ Search by name, phone, or email
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // ✅ Limit results for performance
            $limit = min((int) $request->input('limit', 100), 500);

            // ✅ Select only needed fields
            $users = $query->select(
                    'id', 'name', 'email', 'phone', 'type',
                    'photo', 'status', 'phone_verified_at'
                )
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'users'   => $users->map(function ($user) {
                    return [
                        'id'               => $user->id,
                        'name'             => $user->name ?? 'Unknown User',
                        'phone'            => $user->phone,
                        'phone_formatted'  => $this->formatPhoneNumber($user->phone),
                        'email'            => $user->email,
                        'type'             => $user->type,
                        'type_label'       => $this->getUserTypeLabel($user->type),
                        'is_landlord'      => $user->type === User::TYPE_LANDLORD,
                        'has_photo'        => !empty($user->photo),
                        'photo_url'        => $user->photo_url ?? null,
                        'status'           => $user->status,
                        'status_label'     => $this->getStatusLabel($user->status),
                        'phone_verified'   => !is_null($user->phone_verified_at),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading users for SMS composer: ' . $e->getMessage(), [
                'user_id' => $currentUser->id,
                'error'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading users: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ========================================== //
    // 📨 SEND SMS (Single & Bulk)               //
    // ========================================== //

    /**
     * Send an SMS message (single or bulk).
     *
     * NOTE: The request deliberately does NOT accept a `sender_id`
     * field. The SmsService resolver chain determines the sender:
     *   per-provider (.env) → global (DB) → system fallback
     *
     * Any incoming `sender_id` is silently ignored so that users
     * cannot override the configured provider/admin sender ID.
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number'    => 'required_without:recipient_ids|string|max:20',
            'recipient_ids'   => 'required_without:phone_number|string',
            'message'         => 'required|string|max:1600',
            'provider'        => ['nullable', Rule::in(array_keys($this->smsService->getAvailableProviders()))],
            'scheduled_at'    => 'nullable|date|after:now',
            'is_test'         => 'boolean',
            'is_bulk'         => 'sometimes|boolean',
            'individual_messages' => 'sometimes|boolean',
            'batch_send'          => 'sometimes|boolean',
            'include_user_names'  => 'sometimes|boolean',
            'batch_size'          => 'nullable|integer|min:10|max:100',
            // NOTE: 'sender_id' intentionally not validated or accepted.
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // ----------------------------------------------------------
            // Handle bulk send (comma-separated recipient_ids)
            // ----------------------------------------------------------
            if ($request->filled('recipient_ids')) {
                return $this->handleBulkSend($request);
            }

            // ----------------------------------------------------------
            // Single send
            // ----------------------------------------------------------
            $provider = $request->provider ?? $this->smsService->getDefaultProvider();

            if (!$provider) {
                throw new \Exception('No SMS provider available.');
            }

            // ----------------------------------------------------------
            // ⚠️  Sender ID is NOT passed from the request.
            //
            // The SmsService::sendSMS() method calls resolveSenderId()
            // internally, which picks the correct sender:
            //   1. per-provider value (.env)  — developer-set
            //   2. global value (DB)          — admin-set
            //   3. system fallback
            //
            // This makes the composer safe: users cannot override the
            // configured sender, and the resolver is the single source
            // of truth for every send.
            // ----------------------------------------------------------
            $result = $this->smsService->sendSMS(
                $provider,
                $request->phone_number,
                $request->message,
                [
                    'scheduled_at' => $request->scheduled_at,
                    'is_test'      => $request->boolean('is_test'),
                ]
            );

            // Log the SMS
            $this->logSms($request->phone_number, $request->message, $result, $request);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            if ($result['success']) {
                return redirect()->route('sms.index')
                    ->with('success', $result['message'] ?? 'SMS sent successfully!');
            }

            return redirect()->back()
                ->with('error', $result['message'] ?? 'Failed to send SMS.')
                ->withInput();

        } catch (\Exception $e) {
            Log::error('SMS send error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send SMS: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to send SMS: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Handle bulk SMS sending.
     */
    protected function handleBulkSend(Request $request)
    {
        // ----------------------------------------------------------
        // Parse recipient IDs from the comma-separated string OR
        // from an array (supports both formats for backwards compat).
        // ----------------------------------------------------------
        $recipientIds = $request->input('recipient_ids');

        if (is_string($recipientIds)) {
            $recipientIds = array_filter(
                array_map('intval', explode(',', $recipientIds)),
                fn ($id) => $id > 0
            );
        } elseif (!is_array($recipientIds)) {
            $recipientIds = [];
        }

        if (empty($recipientIds)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid recipient IDs provided.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'No valid recipient IDs provided.')
                ->withInput();
        }

        $recipients = User::whereIn('id', $recipientIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        if ($recipients->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid recipients with phone numbers found.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'No valid recipients with phone numbers found.')
                ->withInput();
        }

        // ✅ Check if user has permission for bulk send
        $currentUser = auth()->user();
        if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can send bulk SMS.',
                ], 403);
            }

            return redirect()->back()
                ->with('error', 'Unauthorized. Only administrators can send bulk SMS.');
        }

        $provider = $request->provider ?? $this->smsService->getDefaultProvider();

        if (!$provider) {
            throw new \Exception('No SMS provider available.');
        }

        // ----------------------------------------------------------
        // Personalization: replace {name} placeholder with the
        // recipient's name if the checkbox was ticked.
        // ----------------------------------------------------------
        $includeNames = $request->boolean('include_user_names', false);

        $results = [
            'total'   => $recipients->count(),
            'success' => 0,
            'failed'  => 0,
            'details' => [],
        ];

        foreach ($recipients as $recipient) {
            try {
                $message = $request->message;

                if ($includeNames && !empty($recipient->name)) {
                    $message = str_replace('{name}', $recipient->name, $message);
                } else {
                    // Strip the placeholder so recipients don't see "{name}"
                    $message = str_replace('{name}', '', $message);
                }

                // --------------------------------------------------
                // ⚠️  Sender ID is NOT passed from the request.
                // The resolver chain decides. See send() for details.
                // --------------------------------------------------
                $result = $this->smsService->sendSMS(
                    $provider,
                    $recipient->phone,
                    $message,
                    [
                        'scheduled_at' => $request->scheduled_at,
                        'user_id'      => $recipient->id,
                        'is_test'      => $request->boolean('is_test'),
                    ]
                );

                if ($result['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }

                $results['details'][] = [
                    'user_id'    => $recipient->id,
                    'name'       => $recipient->name,
                    'phone'      => $recipient->phone,
                    'success'    => $result['success'],
                    'message_id' => $result['message_id'] ?? null,
                    'error'      => $result['error'] ?? null,
                ];

                // ✅ Log each SMS
                $this->logSms($recipient->phone, $message, $result, $request, $recipient->id);

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'user_id' => $recipient->id,
                    'name'    => $recipient->name,
                    'phone'   => $recipient->phone,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];

                Log::error('Bulk SMS failed for user ' . $recipient->id . ': ' . $e->getMessage());
            }
        }

        $message = "Bulk SMS sent: {$results['success']} successful, {$results['failed']} failed";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $results['success'] > 0,
                'message' => $message,
                'results' => $results,
            ]);
        }

        return redirect()->route('sms.index')
            ->with('success', $message);
    }

    // ========================================== //
    // 📊 LOGS & STATISTICS                       //
    // ========================================== //

    /**
     * Display SMS logs.
     */
    public function logs(Request $request)
    {
        $query = SmsLog::with('user');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by provider
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by phone number, message, or user name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', '%' . $search . '%')
                  ->orWhere('message', 'like', '%' . $search . '%')
                  ->orWhere('user_name', 'like', '%' . $search . '%');
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        // Get providers for filter
        $providers = SmsLog::distinct('provider')->pluck('provider')->filter();

        // Get status counts
        $statusCounts = [
            'total'   => SmsLog::count(),
            'sent'    => SmsLog::where('status', 'sent')->count(),
            'failed'  => SmsLog::where('status', 'failed')->count(),
            'pending' => SmsLog::where('status', 'pending')->count(),
        ];

        return view('sms.logs', compact('logs', 'providers', 'statusCounts'));
    }

    /**
     * Show SMS log details.
     */
    public function showLog(SmsLog $log)
    {
        return view('sms.log-detail', compact('log'));
    }

    /**
     * Delete SMS log.
     */
    public function deleteLog(SmsLog $log)
    {
        try {
            $log->delete();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'SMS log deleted successfully.',
                ]);
            }

            return redirect()->route('sms.logs')
                ->with('success', 'SMS log deleted successfully.');

        } catch (\Exception $e) {
            Log::error('SMS log deletion error: ' . $e->getMessage());

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete SMS log: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to delete SMS log: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete SMS logs.
     */
    public function bulkDeleteLogs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array',
            'ids.*' => 'exists:sms_logs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $deleted = SmsLog::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success'       => true,
                'message'       => "{$deleted} SMS log(s) deleted successfully.",
                'deleted_count' => $deleted,
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk SMS log deletion error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete SMS logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export SMS logs.
     */
    public function exportLogs(Request $request)
    {
        $query = SmsLog::with('user');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'sms_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $handle   = fopen('php://temp', 'w+');

        // Add UTF-8 BOM for Excel compatibility
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Add headers
        fputcsv($handle, [
            'ID',
            'User',
            'Provider',
            'Phone Number',
            'Message',
            'Status',
            'Message ID',
            'Error',
            'Is Test',
            'Execution Time (ms)',
            'Created At',
        ]);

        // Add data
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->user_name ?? $log->user->name ?? 'System',
                $log->provider,
                $log->phone_number,
                $log->message,
                $log->status,
                $log->message_id,
                $log->error_message,
                $log->is_test ? 'Yes' : 'No',
                $log->execution_time,
                $log->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get SMS statistics (API endpoint).
     */
    public function statistics(Request $request)
    {
        try {
            $stats = $this->smsService->getUsageStatistics();

            // Add additional stats
            $stats['providers_count'] = count($this->smsService->getAvailableProviders());
            $stats['system_ready']    = $this->smsService->isConfigured();

            // Add daily stats
            $stats['today'] = [
                'sent'   => SmsLog::whereDate('created_at', today())->where('status', 'sent')->count(),
                'failed' => SmsLog::whereDate('created_at', today())->where('status', 'failed')->count(),
                'total'  => SmsLog::whereDate('created_at', today())->count(),
            ];

            // Add weekly stats
            $stats['week'] = [
                'sent'   => SmsLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->where('status', 'sent')->count(),
                'failed' => SmsLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->where('status', 'failed')->count(),
                'total'  => SmsLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ];

            return response()->json([
                'success'   => true,
                'data'      => $stats,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('SMS statistics error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get SMS statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean up old SMS logs.
     */
    public function cleanup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'required|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator);
        }

        try {
            $result = $this->smsService->cleanupOldLogs($request->days);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return redirect()->route('sms.logs')
                ->with('success', $result['message']);

        } catch (\Exception $e) {
            Log::error('SMS cleanup error: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cleanup SMS logs: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to cleanup SMS logs: ' . $e->getMessage());
        }
    }

    // ========================================== //
    // 🔌 API STATUS ENDPOINTS                    //
    // ========================================== //

    /**
     * Get SMS status (API endpoint).
     */
    public function status(Request $request)
    {
        try {
            $systemStatus = $this->smsService->getSystemStatus();
            $quickStatus  = $this->smsService->getQuickStatus();
            $usageStats   = $this->smsService->getUsageStatistics();

            return response()->json([
                'success'       => true,
                'system_status' => $systemStatus,
                'quick_status'  => $quickStatus,
                'usage_stats'   => $usageStats,
                'timestamp'     => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('SMS status error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get SMS status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh SMS status (API endpoint).
     */
    public function refreshStatus(Request $request)
    {
        try {
            $this->smsService->clearStatusCache();

            $systemStatus = $this->smsService->getSystemStatus();
            $quickStatus  = $this->smsService->getQuickStatus();
            $usageStats   = $this->smsService->getUsageStatistics();

            return response()->json([
                'success'       => true,
                'message'       => 'SMS status refreshed successfully',
                'system_status' => $systemStatus,
                'quick_status'  => $quickStatus,
                'usage_stats'   => $usageStats,
                'timestamp'     => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('SMS refresh error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh SMS status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test SMS connection (API endpoint).
     */
    public function testConnection(Request $request)
    {
        try {
            $provider = $request->provider ?? $this->smsService->getDefaultProvider();

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'No SMS provider available.',
                ], 400);
            }

            $result = $this->smsService->testConnection($provider);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('SMS test error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to test SMS connection: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ========================================== //
    // 📝 PRIVATE HELPER METHODS                  //
    // ========================================== //

    /**
     * Format phone number for display.
     */
    private function formatPhoneNumber($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Remove any non-numeric characters except leading +
        $clean = preg_replace('/[^0-9+]/', '', $phone);

        // Format as (XXX) XXX-XXXX for 10-digit US numbers
        if (strlen($clean) === 10) {
            return '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6, 4);
        }

        // For international numbers, return as-is
        return $phone;
    }

    /**
     * Get user type label.
     */
    private function getUserTypeLabel(string $type): string
    {
        $labels = [
            User::TYPE_SUPER_ADMIN       => 'Super Admin',
            User::TYPE_ADMIN             => 'Admin',
            User::TYPE_LANDLORD          => 'Landlord',
            User::TYPE_TENANT            => 'Tenant',
            User::TYPE_FIELD_AGENT       => 'Field Agent',
            User::TYPE_SECURITY_PERSONNEL => 'Security Personnel',
            User::TYPE_DEVELOPER         => 'Developer',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Get status label.
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            User::STATUS_ACTIVE    => 'Active',
            User::STATUS_PENDING   => 'Pending',
            User::STATUS_SUSPENDED => 'Suspended',
            User::STATUS_INACTIVE  => 'Inactive',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Log SMS activity.
     *
     * NOTE: The `metadata` JSON no longer includes a user-supplied
     * sender_id — instead we log the *resolved* sender that was
     * actually used. This gives audit trail without trusting input.
     */
    private function logSms(string $phoneNumber, string $message, array $result, Request $request, ?int $userId = null)
    {
        try {
            // Resolve what sender was actually used (not what was sent in the request)
            $provider      = $request->provider ?? $this->smsService->getDefaultProvider();
            $resolvedSender = $result['details']['sender_id']
                ?? ($provider ? $this->smsService->resolveSenderId($provider) : null);

            SmsLog::create([
                'user_id'        => $userId ?? auth()->id(),
                'user_name'      => $userId ? User::find($userId)?->name : auth()->user()->name,
                'phone_number'   => $phoneNumber,
                'message'        => $message,
                'provider'       => $provider,
                'status'         => $result['success'] ? 'sent' : 'failed',
                'message_id'     => $result['message_id'] ?? null,
                'error_message'  => $result['error'] ?? null,
                'is_test'        => $request->boolean('is_test', false),
                'execution_time' => $result['execution_time'] ?? null,
                'created_by'     => auth()->id(),
                'metadata'       => json_encode([
                    // Resolved sender — informational, not user-supplied
                    'resolved_sender_id' => $resolvedSender,
                    'scheduled_at'       => $request->scheduled_at,
                    'ip_address'         => $request->ip(),
                    'user_agent'         => $request->userAgent(),
                ]),
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to log SMS: ' . $e->getMessage(), [
                'phone'   => $phoneNumber,
                'message' => $message,
            ]);
        }
    }
}