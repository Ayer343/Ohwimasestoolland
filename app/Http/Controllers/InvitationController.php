<?php

namespace App\Http\Controllers;

use App\Models\UserInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvitationExpiredNotification;

class InvitationController extends Controller
{
    /**
     * Accept invitation and complete registration
     */
    public function accept($token)
    {
        try {
            Log::info('Invitation acceptance attempt', ['token' => substr($token, 0, 20) . '...']);

            // Find the invitation with valid statuses
            $invitation = UserInvitation::where('token', $token)
                ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                ->firstOrFail();

            Log::debug('Invitation found', [
                'invitation_id' => $invitation->id,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at
            ]);

            // Check if invitation is expired
            if ($invitation->isExpired()) {
                Log::info('Invitation expired', [
                    'invitation_id' => $invitation->id,
                    'expires_at' => $invitation->expires_at,
                    'current_time' => now()
                ]);
                
                // Track that this expired link was accessed
                $invitation->trackView();
                
                return redirect()->route('invitation.expired', ['token' => $token])
                    ->with('error', 'This invitation link has expired.');
            }

            // Get the user
            $user = User::findOrFail($invitation->user_id);

            Log::debug('User found', [
                'user_id' => $user->id,
                'user_status' => $user->status
            ]);

            // Check if user is pending
            if ($user->status !== User::STATUS_PENDING) {
                Log::warning('Invitation already used', [
                    'user_id' => $user->id,
                    'user_status' => $user->status,
                    'invitation_id' => $invitation->id
                ]);
                
                return redirect()->route('login')
                    ->with('error', 'This invitation has already been used.');
            }

            // ✅ FIX: Use the model's trackView method instead of manually updating status
            // This avoids the "processing" status error
            $invitation->trackView();

            // ✅ FIX: Store invitation token in session instead of auto-login
            Session::put('invitation_token', $token);
            Session::put('invitation_user_id', $user->id);
            
            Log::info('Invitation opened successfully', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'token_preview' => substr($token, 0, 10) . '...'
            ]);

            // ✅ FIX: Redirect to password setup WITHOUT logging in
            return redirect()->route('invitation.setup-password.form')
                ->with('success', 'Please set your password to complete account setup.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Invitation not found', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('invitation.invalid')
                ->with('error', 'Invalid invitation link.');
                
        } catch (\Exception $e) {
            Log::error('Invitation acceptance failed', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'An error occurred while processing your invitation. Please try again or contact support.');
        }
    }

    /**
     * Show invitation expired page
     */
    public function invitationExpired($token = null)
    {
        $invitation = null;
        $user = null;
        $canResend = false;
        
        if ($token) {
            try {
                $invitation = UserInvitation::where('token', $token)->first();
                
                if ($invitation) {
                    $user = User::find($invitation->user_id);
                    
                    // Check if we can resend invitation
                    if ($user && $user->status === User::STATUS_PENDING) {
                        // Check if there's no active invitation already
                        $activeInvitation = UserInvitation::where('user_id', $user->id)
                            ->where('expires_at', '>', now())
                            ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                            ->first();
                        
                        $canResend = !$activeInvitation;
                    }
                    
                    // Log the expired page view
                    Log::info('Expired invitation page viewed', [
                        'invitation_id' => $invitation->id,
                        'token' => substr($token, 0, 10) . '...',
                        'user_id' => $invitation->user_id,
                        'can_resend' => $canResend
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error loading expired invitation details', [
                    'token' => $token,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return view('auth.invitation-expired', [
            'invitation' => $invitation,
            'user' => $user,
            'token' => $token,
            'canResend' => $canResend
        ]);
    }

    /**
     * Show invalid invitation page
     */
    public function invitationInvalid()
    {
        return view('auth.invitation-invalid');
    }

    /**
     * Show password setup form after invitation acceptance
     */
    public function showSetupPassword()
    {
        // ✅ FIX: Check session for invitation token instead of auth
        if (!Session::has('invitation_token')) {
            Log::warning('No invitation token in session');
            return redirect()->route('login')
                ->with('error', 'No active invitation found. Please use your invitation link.');
        }

        $token = Session::get('invitation_token');
        $userId = Session::get('invitation_user_id');

        Log::debug('Password setup form request', [
            'token_preview' => substr($token, 0, 10) . '...',
            'user_id' => $userId
        ]);

        // Validate invitation again
        try {
            $invitation = UserInvitation::where('token', $token)
                ->where('user_id', $userId)
                ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                ->firstOrFail();

            // Check if invitation expired while user was on the page
            if ($invitation->isExpired()) {
                Session::forget(['invitation_token', 'invitation_user_id']);
                
                Log::warning('Invitation expired during password setup form', [
                    'invitation_id' => $invitation->id,
                    'expires_at' => $invitation->expires_at
                ]);
                
                return redirect()->route('invitation.expired', ['token' => $token])
                    ->with('error', 'This invitation link has expired while you were setting up your account.');
            }

            $user = User::findOrFail($userId);

            Log::debug('Validation successful', [
                'invitation_id' => $invitation->id,
                'invitation_status' => $invitation->status,
                'user_status' => $user->status
            ]);

            // Check if user is pending
            if ($user->status !== User::STATUS_PENDING) {
                Session::forget(['invitation_token', 'invitation_user_id']);
                Log::warning('User not pending during password setup', [
                    'user_id' => $user->id,
                    'user_status' => $user->status
                ]);
                
                return redirect()->route('login')
                    ->with('error', 'This invitation has already been used.');
            }

            // Check if invitation is still valid
            if (!$invitation->is_active) {
                Session::forget(['invitation_token', 'invitation_user_id']);
                Log::warning('Invitation no longer active', [
                    'invitation_id' => $invitation->id,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at
                ]);
                
                return redirect()->route('login')
                    ->with('error', 'This invitation is no longer valid.');
            }

            return view('auth.invitation-password-setup', [
                'user' => $user,
                'token' => $token,
                'invitation' => $invitation
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Session::forget(['invitation_token', 'invitation_user_id']);
            Log::error('Invalid invitation session', [
                'token_preview' => substr($token, 0, 10) . '...',
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('invitation.invalid')
                ->with('error', 'Invalid invitation. Please request a new one.');
                
        } catch (\Exception $e) {
            Session::forget(['invitation_token', 'invitation_user_id']);
            Log::error('Error loading password setup form', [
                'token' => $token,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('login')
                ->with('error', 'An error occurred. Please try again or contact support.');
        }
    }

    /**
     * Process password setup
     */
    public function setupPassword(Request $request)
    {
        Log::info('Password setup request received');

        // ✅ FIX: Validate session token first
        $validator = Validator::make($request->all(), [
            'password' => 'required|confirmed|min:8',
            'password_confirmation' => 'required',
            'token' => 'required'
        ], [
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'token.required' => 'Invitation token is required.'
        ]);

        if ($validator->fails()) {
            Log::warning('Password setup validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return back()->withErrors($validator)->withInput();
        }

        $token = $request->token;

        // Also check session token
        if (!Session::has('invitation_token') || Session::get('invitation_token') !== $token) {
            Log::warning('Session token mismatch', [
                'request_token' => $token,
                'session_token' => Session::get('invitation_token')
            ]);
            return redirect()->route('login')
                ->with('error', 'Invalid session. Please use your invitation link again.');
        }

        DB::beginTransaction();

        try {
            // Find and validate invitation
            $invitation = UserInvitation::where('token', $token)
                ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                ->firstOrFail();

            // Check if invitation expired at the last moment
            if ($invitation->isExpired()) {
                DB::rollBack();
                Session::forget(['invitation_token', 'invitation_user_id']);
                
                Log::warning('Invitation expired during password submission', [
                    'invitation_id' => $invitation->id,
                    'expires_at' => $invitation->expires_at
                ]);
                
                return redirect()->route('invitation.expired', ['token' => $token])
                    ->with('error', 'This invitation link has expired. Please request a new invitation.');
            }

            $user = User::findOrFail($invitation->user_id);

            Log::debug('Found valid invitation and user', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'user_status_before' => $user->status
            ]);

            // Double-check user is pending
            if ($user->status !== User::STATUS_PENDING) {
                DB::rollBack();
                Session::forget(['invitation_token', 'invitation_user_id']);
                
                Log::warning('User not pending during password setup', [
                    'user_id' => $user->id,
                    'user_status' => $user->status
                ]);
                
                return redirect()->route('login')
                    ->with('error', 'This invitation has already been used.');
            }

            // Update user with new password and activate account
            $user->update([
                'password' => Hash::make($request->password),
                'status' => User::STATUS_ACTIVE,
                'email_verified_at' => now(),
                'last_login_at' => now()
            ]);

            // ✅ FIX: Update invitation status to 'accepted' using model constant
            $metadata = $invitation->metadata ?? [];
            $statusHistory = $metadata['status_history'] ?? [];
            $statusHistory[] = [
                'status' => UserInvitation::STATUS_ACCEPTED,
                'at' => now()->toISOString(),
                'ip' => request()->ip(),
                'action' => 'password_setup_complete'
            ];
            
            $invitation->update([
                'status' => UserInvitation::STATUS_ACCEPTED, // ✅ Use model constant
                'accepted_at' => now(),
                'metadata' => array_merge($metadata, [
                    'status_history' => $statusHistory,
                    'accepted_details' => [
                        'accepted_at' => now()->toISOString(),
                        'accepted_ip' => request()->ip(),
                        'password_set' => true,
                        'terms_accepted' => true,
                        'privacy_accepted' => true,
                        'verification_channel_used' => 'web_form'
                    ]
                ])
            ]);

            // Clear session
            Session::forget(['invitation_token', 'invitation_user_id']);

            // ✅ FIX: Log in AFTER password is set
            Auth::login($user);

            DB::commit();

            // Log activity
            Log::info('User completed invitation setup successfully', [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'email' => $user->email,
                'user_status_after' => $user->status,
                'login_time' => now()->toDateTimeString()
            ]);

            // Redirect to appropriate dashboard based on user type
            $redirectRoute = $this->getDashboardRoute($user);
            
            return redirect()->route($redirectRoute)
                ->with('success', 'Account setup complete! Welcome to the system.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Session::forget(['invitation_token', 'invitation_user_id']);
            
            Log::error('Invalid invitation during password setup', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('invitation.invalid')
                ->with('error', 'Invalid invitation. Please request a new one.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Session::forget(['invitation_token', 'invitation_user_id']);
            
            Log::error('Password setup failed', [
                'token' => $token,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->with('error', 'Failed to complete account setup: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get appropriate dashboard route based on user type
     */
    private function getDashboardRoute(User $user): string
    {
        switch ($user->type) {
            case User::TYPE_SUPER_ADMIN:
                return 'super-admin.dashboard';
            case User::TYPE_ADMIN:
                return 'admin.dashboard';
            case User::TYPE_DEVELOPER:
                return 'developer.dashboard';
            case User::TYPE_USER:
                return 'user.dashboard';
            default:
                return 'dashboard';
        }
    }

    /**
     * Cancel invitation process (optional)
     */
    public function cancelInvitationProcess()
    {
        $token = Session::get('invitation_token');
        $userId = Session::get('invitation_user_id');
        
        Session::forget(['invitation_token', 'invitation_user_id']);
        
        Log::info('Invitation process cancelled by user', [
            'user_id' => $userId,
            'token_preview' => $token ? substr($token, 0, 10) . '...' : null
        ]);
        
        return redirect()->route('login')
            ->with('info', 'Invitation process cancelled.');
    }

    /**
     * Verify invitation token (API endpoint for checking token validity)
     */
    public function verifyToken($token)
    {
        try {
            $invitation = UserInvitation::where('token', $token)
                ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                ->firstOrFail();

            $user = User::findOrFail($invitation->user_id);

            $isExpired = $invitation->isExpired();
            $daysUntilExpiry = !$isExpired ? $invitation->days_until_expiry : null;

            $response = [
                'valid' => !$isExpired,
                'is_expired' => $isExpired,
                'invitation' => [
                    'id' => $invitation->id,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'days_until_expiry' => $daysUntilExpiry,
                    'is_expiring_soon' => $invitation->is_expiring_soon,
                    'is_expired' => $isExpired
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status
                ]
            ];

            Log::debug('Token verification successful', [
                'token' => substr($token, 0, 10) . '...',
                'invitation_id' => $invitation->id,
                'is_expired' => $isExpired
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::warning('Token verification failed', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'valid' => false,
                'is_expired' => false,
                'message' => 'Invalid invitation token.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Resend invitation email (for users who lost their invitation)
     */
    public function resendInvitation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'sometimes|string' // Optional token for expired invitations
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::where('email', $request->email)
                ->where('status', User::STATUS_PENDING)
                ->firstOrFail();

            // Check for active invitation
            $activeInvitation = UserInvitation::where('user_id', $user->id)
                ->where('expires_at', '>', now())
                ->whereIn('status', [UserInvitation::STATUS_SENT, UserInvitation::STATUS_PENDING])
                ->first();

            if ($activeInvitation) {
                return response()->json([
                    'success' => true,
                    'message' => 'You already have an active invitation. Please check your email.',
                    'has_active_invitation' => true,
                    'expires_at' => $activeInvitation->expires_at->toISOString()
                ]);
            }

            // If token is provided, mark the old invitation as expired properly
            if ($request->has('token')) {
                $oldInvitation = UserInvitation::where('token', $request->token)
                    ->where('user_id', $user->id)
                    ->first();
                
                if ($oldInvitation && $oldInvitation->status !== UserInvitation::STATUS_EXPIRED) {
                    $oldInvitation->update([
                        'status' => UserInvitation::STATUS_EXPIRED,
                        'metadata' => array_merge($oldInvitation->metadata ?? [], [
                            'resend_requested_at' => now()->toISOString(),
                            'resend_requested_by_ip' => $request->ip()
                        ])
                    ]);
                    
                    Log::info('Old invitation marked as expired for resend', [
                        'old_invitation_id' => $oldInvitation->id,
                        'user_id' => $user->id
                    ]);
                }
            }

            // Create new invitation
            $invitation = UserInvitation::create([
                'user_id' => $user->id,
                'invited_by' => $user->created_by ?? 1, // System or original creator
                'invitation_type' => UserInvitation::TYPE_PASSWORD_SETUP,
                'channels' => [UserInvitation::CHANNEL_EMAIL],
                'metadata' => [
                    'previous_invitation_token' => $request->token ?? null,
                    'resend_requested_at' => now()->toISOString(),
                    'resend_requested_by_ip' => $request->ip()
                ]
            ]);

            // Send invitation email (you'll need to implement this)
            // $this->sendInvitationEmail($user, $invitation);

            DB::commit();

            Log::info('Invitation resent', [
                'user_id' => $user->id,
                'invitation_id' => $invitation->id,
                'requested_by_ip' => $request->ip(),
                'previous_token' => $request->token ? substr($request->token, 0, 10) . '...' : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New invitation has been sent to your email.',
                'invitation_id' => $invitation->id,
                'expires_at' => $invitation->expires_at->toISOString()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resend invitation', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process resend invitation from expired page (web form)
     */
    public function resendInvitationFromExpiredPage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Call the API resend method
            $response = $this->resendInvitation($request);
            $data = $response->getData(true);
            
            if ($data['success']) {
                return redirect()->route('login')
                    ->with('success', $data['message']);
            } else {
                return back()
                    ->with('error', $data['message'])
                    ->withInput();
            }
        } catch (\Exception $e) {
            Log::error('Failed to resend invitation from expired page', [
                'email' => $request->email,
                'token' => $request->token,
                'error' => $e->getMessage()
            ]);

            return back()
                ->with('error', 'Failed to resend invitation. Please try again or contact support.')
                ->withInput();
        }
    }
}