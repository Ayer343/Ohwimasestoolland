<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvitation;
use App\Models\SystemSetting;
use App\Models\SanitationPersonnel;
use App\Services\UserInvitationService;
use App\Services\MultiChannelInvitationService;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

class UserInvitationController extends Controller
{
    protected $userInvitationService;
    protected $multiChannelInvitationService;
    protected $smsService;
    protected $emailService;
    protected $whatsappService;

    // Configurable expiration properties
    protected $invitationExpiryDays;
    protected $invitationWarningDays;
    protected $invitationAutoExpiry;
    protected $invitationResendExtendsExpiry;

    public function __construct(
        UserInvitationService $userInvitationService,
        MultiChannelInvitationService $multiChannelInvitationService,
        SmsService $smsService,
        EmailService $emailService,
        WhatsAppService $whatsappService
    ) {
        $this->userInvitationService = $userInvitationService;
        $this->multiChannelInvitationService = $multiChannelInvitationService;
        $this->smsService = $smsService;
        $this->emailService = $emailService;
        $this->whatsappService = $whatsappService;

        // Load all configuration from config/app.php
        $this->invitationExpiryDays = config('app.invitation_expiry_days', 7);
        $this->invitationWarningDays = config('app.invitation_warning_days', 2);
        $this->invitationAutoExpiry = config('app.invitation_auto_expiry', true);
        $this->invitationResendExtendsExpiry = config('app.invitation_resend_extend_expiry', true);
    }

    /**
     * Show invitation acceptance form with enhanced validation and system branding
     */
    public function showAcceptForm($token)
    {
        try {
            // Load invitation with enhanced safe checking
            $invitation = UserInvitation::with(['user', 'invitedBy'])
                ->where('token', $token)
                ->firstOrFail();

            // Get system settings for branding
            $systemSettings = SystemSetting::getSettings();
            $systemName = $systemSettings->system_name ?? config('app.name', 'User Management System');
            $supportEmail = $systemSettings->system_email ?? 'support@example.com';

            // IMMEDIATE RAW CHECK - before any model methods are called
            $rawExpiresAt = $invitation->getRawOriginal('expires_at');
            $rawStatus = $invitation->getRawOriginal('status');
            $rawCreatedAt = $invitation->getRawOriginal('created_at');

            $isActuallyExpired = $rawExpiresAt && Carbon::parse($rawExpiresAt)->isPast();

            // CRITICAL: Detect corrupted invitations
            $isCorrupted = false;
            $hoursDifference = null;

            if ($rawCreatedAt && $rawExpiresAt) {
                $createdAt = Carbon::parse($rawCreatedAt);
                $expiresAt = Carbon::parse($rawExpiresAt);
                $hoursDifference = $createdAt->diffInHours($expiresAt);
                $isCorrupted = $hoursDifference < 24; // Less than 1 day difference
            }

            Log::info('User invitation accessed - Enhanced safe check', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'raw_status' => $rawStatus,
                'raw_expires_at' => $rawExpiresAt,
                'raw_created_at' => $rawCreatedAt,
                'is_actually_expired' => $isActuallyExpired,
                'is_corrupted' => $isCorrupted,
                'hours_difference' => $hoursDifference,
                'token' => $token,
                'auto_expiry_enabled' => $this->invitationAutoExpiry,
                'invitation_type' => $invitation->invitation_type,
                'system_name' => $systemName
            ]);

            // CRITICAL FIX: Auto-repair corrupted invitations before any status checks
            if ($isCorrupted && in_array($rawStatus, [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])) {
                Log::warning('Auto-repairing corrupted user invitation', [
                    'invitation_id' => $invitation->id,
                    'current_expires_at' => $rawExpiresAt,
                    'created_at' => $rawCreatedAt,
                    'hours_difference' => $hoursDifference
                ]);

                if ($invitation->fixExpirationDate()) {
                    $invitation->refresh();
                    Log::info('Successfully auto-repaired corrupted user invitation', [
                        'invitation_id' => $invitation->id,
                        'new_expires_at' => $invitation->getRawOriginal('expires_at')
                    ]);

                    // Reset the expiration check after repair
                    $isActuallyExpired = false;
                }
            }

            // Use safe status check after potential repair
            $safeStatus = $invitation->getSafeStatus();

            // Handle expiration based on safe status
            if ($safeStatus === 'expired' || $safeStatus === 'should_be_expired') {
                if ($this->invitationAutoExpiry && $safeStatus === 'should_be_expired' && !$isCorrupted) {
                    Log::warning('Marking user invitation as expired on access', [
                        'invitation_id' => $invitation->id,
                        'previous_status' => $rawStatus,
                        'expires_at' => $rawExpiresAt
                    ]);

                    $invitation->markAsExpired();
                    $invitation->refresh();
                }

                return redirect()->route('user.invitations.expired')
                    ->with('error', 'This invitation has expired.')
                    ->with('invitation', $invitation)
                    ->with('systemSettings', $systemSettings);
            }

            // FIXED: Use correct status checks - check status directly
            if ($invitation->isAccepted()) {
                return redirect()->route('user.invitations.success')
                    ->with('info', 'This invitation has already been accepted. Please login with your credentials.')
                    ->with('systemSettings', $systemSettings);
            }

            // FIXED: Check cancelled and revoked status using direct status comparison
            if ($invitation->status === UserInvitation::STATUS_CANCELLED ||
                $invitation->status === UserInvitation::STATUS_REVOKED) {
                return redirect()->route('user.invitations.invalid')
                    ->with('error', 'This invitation has been cancelled. Please contact the administrator.')
                    ->with('invitation', $invitation)
                    ->with('systemSettings', $systemSettings);
            }

            // FIXED: Check failed status
            if ($invitation->status === UserInvitation::STATUS_FAILED) {
                return redirect()->route('user.invitations.invalid')
                    ->with('error', 'This invitation failed to deliver. Please contact the administrator.')
                    ->with('invitation', $invitation)
                    ->with('systemSettings', $systemSettings);
            }

            // FIXED: Use isActive() method
            if (!$invitation->isActive()) {
                Log::warning('User invitation is not active', [
                    'invitation_id' => $invitation->id,
                    'status' => $rawStatus,
                    'is_actually_expired' => $isActuallyExpired,
                    'is_corrupted' => $isCorrupted
                ]);

                return redirect()->route('user.invitations.invalid')
                    ->with('error', 'This invitation is no longer valid.')
                    ->with('invitation', $invitation)
                    ->with('systemSettings', $systemSettings);
            }

            // Only track view AFTER all checks pass
            $invitation->trackView();

            Log::info('User invitation passed all checks, showing form', [
                'invitation_id' => $invitation->id,
                'user_id' => $invitation->user_id,
                'status' => $invitation->status,
                'viewed_at' => $invitation->viewed_at,
                'expires_at' => $invitation->expires_at?->toISOString(),
                'days_until_expiry' => $invitation->getDaysUntilExpiry(),
                'is_active' => $invitation->isActive(),
                'was_repaired' => $isCorrupted,
                'invitation_type' => $invitation->invitation_type,
                'system_name' => $systemName
            ]);

            // Show expiration warning
            $expirationWarning = null;
            if ($invitation->isExpiringSoon($this->invitationWarningDays)) {
                $days = $invitation->getDaysUntilExpiry();
                $expirationWarning = "This invitation expires in {$days} day" . ($days != 1 ? 's' : '') . ". Please accept it soon.";
            }

            // Show repair notification if invitation was just fixed
            $wasRepaired = $isCorrupted;

            // Get available verification channels based on user's contact info
            $availableChannels = $this->getAvailableVerificationChannels($invitation->user);

            return view('user-invitations.accept', compact(
                'invitation',
                'expirationWarning',
                'wasRepaired',
                'availableChannels',
                'systemSettings',
                'systemName',
                'supportEmail'
            ));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Invalid invitation token accessed', [
                'token' => $token,
                'error' => 'Invitation not found'
            ]);

            return redirect()->route('user.invitations.invalid')
                ->with('error', 'Invalid invitation link. Please check the link or contact the administrator.')
                ->with('systemSettings', SystemSetting::getSettings());

        } catch (\Exception $e) {
            Log::error('Error accessing user invitation form', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('user.invitations.invalid')
                ->with('error', 'Invalid invitation link. Please contact the administrator.')
                ->with('systemSettings', SystemSetting::getSettings());
        }
    }

    /**
     * Get available verification channels for user
     */
    private function getAvailableVerificationChannels(User $user)
    {
        $channels = [];

        if ($user->phone) {
            $channels[] = 'sms';
            $channels[] = 'whatsapp';
        }

        if ($user->email) {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * Process invitation acceptance with proper role-based redirect
     * FIXED: Sanitation personnel redirect is now guarded with fallback chain.
     */
    public function processAcceptance(Request $request, $token)
    {
        try {
            // Enhanced validation rules with multi-channel verification support
            $validator = Validator::make($request->all(), [
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                ],
                'agree_terms' => 'required|accepted',
                'agree_privacy' => 'required|accepted',
                'verification_code' => 'sometimes|required|size:6',
                'verification_channel' => 'sometimes|required|in:sms,whatsapp,email'
            ], [
                'password.required' => 'Password is required',
                'password.min' => 'Password must be at least 8 characters',
                'password.confirmed' => 'Password confirmation does not match',
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
                'agree_terms.required' => 'You must accept the terms and conditions',
                'agree_terms.accepted' => 'You must accept the terms and conditions',
                'agree_privacy.required' => 'You must accept the privacy policy',
                'agree_privacy.accepted' => 'You must accept the privacy policy',
                'verification_code.required' => 'Verification code is required',
                'verification_code.size' => 'Verification code must be 6 digits',
                'verification_channel.required' => 'Verification channel is required'
            ]);

            if ($validator->fails()) {
                Log::info('User invitation acceptance validation failed', [
                    'token' => $token,
                    'errors' => $validator->errors()->toArray(),
                    'ip' => $request->ip()
                ]);

                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Please correct the errors below.');
            }

            // Prepare acceptance data
            $acceptanceData = [
                'password' => $request->password,
                'agree_terms' => $request->agree_terms,
                'agree_privacy' => $request->agree_privacy,
                'verification_code' => $request->verification_code,
                'verification_channel' => $request->verification_channel,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ];

            // Use service to process acceptance
            $result = $this->userInvitationService->processInvitationAcceptance($token, $acceptanceData);

            if (!$result['success']) {
                return redirect()->back()
                    ->with('error', $result['message'])
                    ->withInput();
            }

            // Log the user in automatically
            auth()->login($result['user']);

            // Get invitation for additional context
            $invitation = UserInvitation::where('token', $token)->first();
            $wasRepaired = $invitation ? $this->checkIfInvitationWasRepaired($invitation) : false;

            Log::info("User invitation accepted successfully via service", [
                'invitation_id' => $invitation?->id,
                'user_id' => $result['user']->id,
                'user_name' => $result['user']->name,
                'user_type' => $result['user']->type,
                'user_type_name' => $result['user']->type_name,
                'ip_address' => $request->ip(),
                'accepted_at' => now()->toISOString(),
                'was_repaired' => $wasRepaired
            ]);

            // ✅ Determine the correct redirect route based on user type
            $redirectRoute = $this->getRedirectRouteForUser($result['user']);

            Log::info('Redirecting user after invitation acceptance', [
                'user_id' => $result['user']->id,
                'user_type' => $result['user']->type,
                'user_type_name' => $result['user']->type_name,
                'redirect_route' => $redirectRoute,
                'redirect_url' => route($redirectRoute)
            ]);

            // ✅ Redirect to the user's specific dashboard
            return redirect()->route($redirectRoute)
                ->with('success', $this->getWelcomeMessage($result['user']))
                ->with('info', $wasRepaired ? 'Note: Your invitation expiration was automatically extended.' : null);

        } catch (\Exception $e) {
            Log::error('Error processing user invitation acceptance', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('user.invitations.invalid')
                ->with('error', 'Invalid invitation link. Please contact the administrator.');
        }
    }

    /**
     * Get the correct redirect route based on user type
     *
     * ✅ Sanitation personnel route is looked up with a fallback chain:
     *    sanitation.dashboard → sanitation.personnel.index → dashboard
     *    This prevents a 500 if a route name changes.
     */
    private function getRedirectRouteForUser(User $user): string
    {
        $userType = $user->getRawOriginal('type');

        // Define the route mapping for each user type
        $routeMap = [
            User::TYPE_SUPER_ADMIN => 'super-admin.dashboard',
            User::TYPE_ADMIN => 'admin.dashboard',
            User::TYPE_LANDLORD => 'landlord.dashboard',
            User::TYPE_TENANT => 'tenant.dashboard',
            User::TYPE_FIELD_AGENT => 'field-agent.dashboard',
            User::TYPE_DEVELOPER => 'developer.dashboard',
            User::TYPE_SECURITY_PERSONNEL => 'security.dashboard',
            User::TYPE_SANITATION_PERSONNEL => 'sanitation.dashboard',
            User::TYPE_CONTRACTOR => 'contractor.dashboard',
            User::TYPE_FORMER_LANDLORD => 'dashboard',
        ];

        // ✅ Special case: sanitation personnel — try a chain of routes
        if ($userType === User::TYPE_SANITATION_PERSONNEL) {
            $route = $this->resolveSanitationRedirectRoute($user);
            return $route;
        }

        // Generic path for other user types
        $route = $routeMap[$userType] ?? 'dashboard';

        if (!Route::has($route)) {
            Log::warning('Redirect route not found for user type, falling back to dashboard', [
                'user_id' => $user->id,
                'user_type' => $userType,
                'route' => $route
            ]);
            $route = 'dashboard';
        }

        return $route;
    }

    /**
     * ✅ Resolve the sanitation personnel redirect route with a safe fallback chain.
     *
     * Order of preference:
     *   1. sanitation.dashboard           — primary destination
     *   2. sanitation.personnel.index     — if the dashboard doesn't exist
     *   3. dashboard                      — final safety net
     *
     * Also logs a warning if the user has no linked SanitationPersonnel record,
     * since that would render an empty dashboard.
     */
    private function resolveSanitationRedirectRoute(User $user): string
    {
        // Warn if the sanitation user has no personnel record — dashboard would be empty.
        try {
            $hasPersonnelRecord = SanitationPersonnel::where('user_id', $user->id)->exists();

            if (!$hasPersonnelRecord) {
                Log::warning('Sanitation user accepted invite but has no SanitationPersonnel record', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_name' => $user->name,
                ]);
            }
        } catch (\Exception $e) {
            // Never let this warning break the redirect
            Log::debug('Could not check SanitationPersonnel existence', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 1. Primary destination
        if (Route::has('sanitation.dashboard')) {
            return 'sanitation.dashboard';
        }

        // 2. Secondary destination
        if (Route::has('sanitation.personnel.index')) {
            Log::warning('sanitation.dashboard route missing — falling back to sanitation.personnel.index', [
                'user_id' => $user->id,
            ]);
            return 'sanitation.personnel.index';
        }

        // 3. Final safety net
        Log::warning('No sanitation route found — falling back to generic dashboard', [
            'user_id' => $user->id,
        ]);
        return 'dashboard';
    }

    /**
     * Send user invitation using UserInvitationService
     */
    public function sendInvitation(Request $request)
    {
        // Only allow if user is authenticated and has permission
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'invitation_type' => 'required|in:welcome,registration,account_setup,password_setup',
            'channels' => 'sometimes|array',
            'channels.*' => 'in:email,sms,whatsapp',
            'custom_message' => 'nullable|string|max:500',
            'expires_in_days' => 'nullable|integer|min:1|max:30'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Use service to create and send invitation
        $result = $this->userInvitationService->createAndSendInvitation(
            $request->all(),
            Auth::user()
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 422);
        }

        $invitation = $result['invitation'];
        $user = $invitation->user;

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully!',
            'invitation_url' => $invitation->getInvitationUrl(),
            'delivery_results' => $result['delivery_results'],
            'available_channels' => $result['available_channels'],
            'invitation' => [
                'id' => $invitation->id,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at->toISOString(),
                'sent_via' => $invitation->channels,
                'invitation_type' => $invitation->invitation_type,
                'custom_message' => $invitation->custom_message
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type_name,
                'status' => $user->status
            ],
            'config' => [
                'expiry_days' => $this->invitationExpiryDays,
                'warning_days' => $this->invitationWarningDays,
                'auto_expiry_enabled' => $this->invitationAutoExpiry
            ]
        ]);
    }

    /**
     * Resend invitation using UserInvitationService
     */
    public function resendInvitation(Request $request, $userId)
    {
        // Only allow if user is authenticated and has permission
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], 401);
        }

        try {
            $user = User::findOrFail($userId);
            $currentUser = Auth::user();

            // Use service to resend invitation
            $result = $this->userInvitationService->resendInvitation(
                $user,
                $currentUser,
                $request->all()
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 422);
            }

            $invitation = $result['invitation'];

            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully!',
                'invitation_url' => $invitation->getInvitationUrl(),
                'delivery_results' => $result['delivery_results'],
                'invitation' => [
                    'id' => $invitation->id,
                    'token' => $invitation->token,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'sent_via' => $invitation->channels,
                    'invitation_type' => $invitation->invitation_type,
                    'custom_message' => $invitation->custom_message
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type_name,
                    'status' => $user->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error resending invitation: ' . $e->getMessage(), [
                'user_id' => $userId,
                'requested_by' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show expired invitation page with system branding
     */
    public function expired()
    {
        $invitation = session('invitation');
        $systemSettings = SystemSetting::getSettings();
        $systemName = $systemSettings->system_name ?? config('app.name', 'User Management System');
        $supportEmail = $systemSettings->system_email ?? 'support@example.com';

        Log::info("User expired invitation page accessed", [
            'has_invitation' => !is_null($invitation),
            'invitation_id' => $invitation?->id,
            'expiry_days' => $this->invitationExpiryDays,
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'system_name' => $systemName
        ]);

        return view('user-invitations.expired', [
            'invitation' => $invitation,
            'expiry_days' => $this->invitationExpiryDays,
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'systemSettings' => $systemSettings,
            'systemName' => $systemName,
            'supportEmail' => $supportEmail
        ]);
    }

    /**
     * Show invalid invitation page with system branding
     */
    public function invalid()
    {
        $invitation = session('invitation');
        $systemSettings = SystemSetting::getSettings();
        $systemName = $systemSettings->system_name ?? config('app.name', 'User Management System');
        $supportEmail = $systemSettings->system_email ?? 'support@example.com';

        Log::info("User invalid invitation page accessed", [
            'has_invitation' => !is_null($invitation),
            'invitation_id' => $invitation?->id,
            'status' => $invitation?->status,
            'expiry_days' => $this->invitationExpiryDays,
            'system_name' => $systemName
        ]);

        return view('user-invitations.invalid', [
            'invitation' => $invitation,
            'expiry_days' => $this->invitationExpiryDays,
            'systemSettings' => $systemSettings,
            'systemName' => $systemName,
            'supportEmail' => $supportEmail
        ]);
    }

    /**
     * Show success page after acceptance with system branding
     */
    public function success()
    {
        $systemSettings = SystemSetting::getSettings();
        $systemName = $systemSettings->system_name ?? config('app.name', 'User Management System');
        $supportEmail = $systemSettings->system_email ?? 'support@example.com';

        Log::info("User invitation success page accessed", [
            'expiry_days' => $this->invitationExpiryDays,
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'system_name' => $systemName
        ]);

        return view('user-invitations.success', [
            'expiry_days' => $this->invitationExpiryDays,
            'auto_expiry_enabled' => $this->invitationAutoExpiry,
            'systemSettings' => $systemSettings,
            'systemName' => $systemName,
            'supportEmail' => $supportEmail
        ]);
    }

    /**
     * Get available channels for a user using service
     */
    public function getUserChannels(User $user, Request $request)
    {
        $availableChannels = $this->userInvitationService->getAvailableChannels($user);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'available_channels' => $availableChannels,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'has_phone' => !empty($user->phone),
                    'has_email' => !empty($user->email) && filter_var($user->email, FILTER_VALIDATE_EMAIL),
                ]
            ]);
        }

        return $availableChannels;
    }

    /**
     * Validate invitation token using enhanced UserInvitation model
     */
    public function validateToken($token)
    {
        try {
            $invitation = UserInvitation::with(['user', 'invitedBy'])
                ->where('token', $token)
                ->where('expires_at', '>', now())
                ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired invitation token',
                    'valid' => false
                ], 404);
            }

            return response()->json([
                'success' => true,
                'valid' => true,
                'invitation' => [
                    'id' => $invitation->id,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'expires_in_human' => $invitation->expires_at->diffForHumans(),
                    'channels' => $invitation->channels,
                    'invitation_type' => $invitation->invitation_type,
                    'custom_message' => $invitation->custom_message,
                    'sent_at' => $invitation->sent_at?->toISOString(),
                    'status' => $invitation->status,
                    'invited_by' => $invitation->invitedBy ? [
                        'id' => $invitation->invitedBy->id,
                        'name' => $invitation->invitedBy->name
                    ] : null
                ],
                'user' => [
                    'id' => $invitation->user->id,
                    'name' => $invitation->user->name,
                    'email' => $invitation->user->email,
                    'phone' => $invitation->user->phone,
                    'type' => $invitation->user->type_name,
                    'status' => $invitation->user->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error validating invitation token: ' . $e->getMessage(), [
                'token' => $token
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validating invitation token',
                'valid' => false
            ], 500);
        }
    }

    /**
     * Get invitation status using service methods
     */
    public function getInvitationStatus($userId)
    {
        try {
            $user = User::with(['invitations' => function($query) {
                $query->latest();
            }, 'invitations.invitedBy'])->findOrFail($userId);

            // Check permissions (only super admin or creator can view status)
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && $currentUser->id !== $user->created_by) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this user\'s invitation status.'
                ], 403);
            }

            $latestInvitation = $user->invitations->first();

            // Use service to get available channels
            $availableChannels = $this->userInvitationService->getAvailableChannels($user);
            $canReceiveInvitation = $user->canReceiveInvitation();

            $status = [
                'total_invitations' => $user->invitations->count(),
                'has_valid_invitation' => $user->has_valid_invitation,
                'invitation_status' => $latestInvitation?->status,
                'can_receive_invitation' => $canReceiveInvitation,
                'needs_password_setup' => $user->status === User::STATUS_PENDING,
                'available_channels' => $availableChannels,
                'latest_invitation' => $latestInvitation ? [
                    'id' => $latestInvitation->id,
                    'type' => $latestInvitation->invitation_type,
                    'channels' => $latestInvitation->channels,
                    'sent_at' => $latestInvitation->sent_at?->toISOString(),
                    'expires_at' => $latestInvitation->expires_at?->toISOString(),
                    'accepted_at' => $latestInvitation->accepted_at?->toISOString(),
                    'invited_by' => $latestInvitation->invitedBy?->name
                ] : null,
                'latest_invitation_url' => $latestInvitation ? $latestInvitation->getInvitationUrl() : null,
            ];

            return response()->json([
                'success' => true,
                'status' => $status,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type_name,
                    'status' => $user->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting invitation status: ' . $e->getMessage(), [
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitation status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invitation statistics using service
     */
    public function getInvitationStatistics(Request $request)
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view invitation statistics.'
                ], 403);
            }

            // Use service to get statistics
            $statistics = $this->userInvitationService->getStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting invitation statistics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitation statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all invitations (for admin management)
     */
    public function index(Request $request)
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view invitations.'
                ], 403);
            }

            $perPage = $request->get('per_page', 15);
            $invitations = UserInvitation::with(['user', 'invitedBy'])
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'invitations' => $invitations
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing invitations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show invitation details
     */
    public function show($invitationId)
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view invitation details.'
                ], 403);
            }

            $invitation = UserInvitation::with(['user', 'invitedBy'])->findOrFail($invitationId);

            return response()->json([
                'success' => true,
                'invitation' => $invitation
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing invitation details: ' . $e->getMessage(), [
                'invitation_id' => $invitationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitation details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an active invitation using service
     */
    public function cancelInvitation(Request $request, $invitationId)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], 401);
        }

        try {
            $invitation = UserInvitation::with('user')->findOrFail($invitationId);
            $currentUser = Auth::user();

            // Use service to cancel invitation
            $result = $this->userInvitationService->cancelInvitation(
                $invitationId,
                $currentUser
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Invitation cancelled successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling invitation: ' . $e->getMessage(), [
                'invitation_id' => $invitationId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invitation expiry configuration using service
     */
    public function getExpiryConfiguration()
    {
        $expiryConfig = $this->userInvitationService->getExpiryConfiguration();

        return response()->json([
            'success' => true,
            'expiry_configuration' => $expiryConfig
        ]);
    }

    /**
     * Clean up expired invitations using service
     */
    public function cleanupExpiredInvitations()
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to clean up invitations.'
                ], 403);
            }

            $result = $this->userInvitationService->cleanupOldInvitations();

            return response()->json([
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'expired_count' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning up expired invitations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error cleaning up expired invitations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show invitation expired page
     */
    public function showExpiredPage()
    {
        return view('auth.invitation-expired', [
            'message' => 'The invitation link you used has expired or is invalid.',
            'showContact' => true
        ]);
    }

    /**
     * Check if invitation was repaired
     */
    private function checkIfInvitationWasRepaired(UserInvitation $invitation): bool
    {
        $rawCreatedAt = $invitation->getRawOriginal('created_at');
        $rawExpiresAt = $invitation->getRawOriginal('expires_at');

        if ($rawCreatedAt && $rawExpiresAt) {
            $createdAt = Carbon::parse($rawCreatedAt);
            $expiresAt = Carbon::parse($rawExpiresAt);
            $hoursDifference = $createdAt->diffInHours($expiresAt);
            return $hoursDifference < 24;
        }

        return false;
    }

    /**
     * Get personalized welcome message based on user type and time
     */
    private function getWelcomeMessage(User $user): string
    {
        $timeOfDay = $this->getTimeOfDay();
        $greetings = [
            'morning' => 'Good morning',
            'afternoon' => 'Good afternoon',
            'evening' => 'Good evening'
        ];

        $greeting = $greetings[$timeOfDay] ?? 'Welcome';
        $typeSpecificMessage = $this->getTypeSpecificWelcome($user->type);

        return "{$greeting}, {$user->name}! {$typeSpecificMessage}";
    }

    /**
     * Get time of day for greeting
     */
    private function getTimeOfDay(): string
    {
        $hour = now()->hour;

        if ($hour < 12) return 'morning';
        if ($hour < 17) return 'afternoon';
        return 'evening';
    }

    /**
     * Get type-specific welcome message
     */
    private function getTypeSpecificWelcome(int $userType): string
    {
        $messages = [
            User::TYPE_SUPER_ADMIN => 'Your administrative account has been activated successfully.',
            User::TYPE_ADMIN => 'Your administrator account is now active and ready to use.',
            User::TYPE_LANDLORD => 'Your landlord account is now active. Start managing your properties!',
            User::TYPE_TENANT => 'Your tenant account is now active. Welcome to your new home!',
            User::TYPE_FIELD_AGENT => 'Your field agent account is now active. Check your assignments to get started!',
            User::TYPE_SECURITY_PERSONNEL => 'Your security checkpoint account is now active.',
            User::TYPE_SANITATION_PERSONNEL => 'Your sanitation personnel account is now active. You can now manage waste collection and sanitation tasks!',
            User::TYPE_CONTRACTOR => 'Your contractor account is now active. You can now view and manage your construction contracts!',
        ];

        return $messages[$userType] ?? 'Your account has been activated successfully.';
    }

    /**
     * Verify invitation token and return invitation details
     */
    public function verifyToken($token)
    {
        try {
            $result = $this->userInvitationService->validateInvitationToken($token);

            if (!$result['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code'] ?? 'INVALID_TOKEN'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token is valid',
                'valid' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Error verifying invitation token: ' . $e->getMessage(), [
                'token' => $token
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error verifying invitation token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invitation details by token
     */
    public function getInvitationDetails($token)
    {
        try {
            $result = $this->userInvitationService->getInvitationDetails($token);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error_code' => $result['error_code'] ?? 'INVALID_INVITATION'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'invitation' => $result['invitation'],
                'user' => $result['user'],
                'invited_by' => $result['invited_by'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting invitation details: ' . $e->getMessage(), [
                'token' => $token
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitation details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user invitation history
     */
    public function getUserInvitationHistory($userId)
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin() && $currentUser->id != $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view this user\'s invitation history.'
                ], 403);
            }

            $user = User::findOrFail($userId);
            $history = $this->userInvitationService->getUserInvitationHistory($user);

            return response()->json([
                'success' => true,
                'history' => $history,
                'total' => count($history)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user invitation history: ' . $e->getMessage(), [
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitation history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system invitation status
     */
    public function getSystemInvitationStatus()
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view system invitation status.'
                ], 403);
            }

            $status = $this->userInvitationService->getSystemInvitationStatus();

            return response()->json([
                'success' => true,
                'system_status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting system invitation status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving system invitation status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invitation analytics
     */
    public function getInvitationAnalytics()
    {
        try {
            $currentUser = Auth::user();
            if (!$currentUser->isSuperAdmin() && !$currentUser->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view invitation analytics.'
                ], 403);
            }

            $analytics = $this->userInvitationService->getInvitationAnalytics();

            return response()->json([
                'success' => true,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting invitation analytics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving invitation analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track invitation view (for analytics)
     */
    public function trackInvitationView($token)
    {
        try {
            $this->userInvitationService->trackInvitationView($token);

            return response()->json([
                'success' => true,
                'message' => 'View tracked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error tracking invitation view: ' . $e->getMessage(), [
                'token' => $token
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error tracking view: ' . $e->getMessage()
            ], 500);
        }
    }
}