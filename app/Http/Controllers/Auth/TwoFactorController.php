<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authentication\TwoFactorAuthService;
use App\Services\Authentication\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;
    protected LoginActivityService $activityService;

    /**
     * Constructor
     */
    public function __construct(
        TwoFactorAuthService $twoFactorService,
        LoginActivityService $activityService
    ) {
        $this->middleware('guest')->except([
            'showSetupForm',
            'enableTwoFactor',
            'disableTwoFactor',
            'showRecoveryCodes',
            'regenerateRecoveryCodes',
            'verifySetup'
        ]);
        
        $this->middleware('auth')->only([
            'showSetupForm',
            'enableTwoFactor',
            'disableTwoFactor',
            'showRecoveryCodes',
            'regenerateRecoveryCodes',
            'verifySetup'
        ]);
        
        $this->twoFactorService = $twoFactorService;
        $this->activityService = $activityService;
    }

    /**
     * Show the two-factor authentication verification form
     */
    public function showVerificationForm()
    {
        // Check if there's a pending 2FA session
        if (!session('2fa:user_id')) {
            return redirect()->route('login');
        }

        $userId = session('2fa:user_id');
        $user = User::find($userId);
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Get remaining attempts
        $remainingAttempts = $this->twoFactorService->getRemainingAttempts($user);

        return view('auth.two-factor-verify', [
            'user' => $user,
            'method' => $user->two_factor_method ?? 'email',
            'remaining_attempts' => $remainingAttempts,
            'trust_device_enabled' => config('auth.two_factor.trust_device', true)
        ]);
    }

    /**
     * Verify the two-factor authentication code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'trust_device' => 'sometimes|boolean'
        ]);

        $userId = session('2fa:user_id');
        if (!$userId) {
            throw ValidationException::withMessages([
                'code' => 'Session expired. Please login again.'
            ]);
        }

        $user = User::find($userId);
        if (!$user) {
            throw ValidationException::withMessages([
                'code' => 'User not found. Please login again.'
            ]);
        }

        // Check rate limiting
        $remainingAttempts = $this->twoFactorService->getRemainingAttempts($user);
        if ($remainingAttempts <= 0) {
            // Clear session and redirect to login
            session()->forget('2fa:user_id');
            session()->forget('2fa:remember');
            
            throw ValidationException::withMessages([
                'code' => 'Too many failed attempts. Please login again.'
            ]);
        }

        // Verify the code
        if (!$this->twoFactorService->verify($user, $request->code)) {
            // Increment failed attempts
            $this->twoFactorService->incrementFailedAttempts($user);
            
            // Record failed attempt
            $this->activityService->recordTwoFactorFailure($user, $request);
            
            throw ValidationException::withMessages([
                'code' => 'Invalid verification code. Please try again.'
            ])->redirectTo(route('2fa.verify'));
        }

        // Clear failed attempts
        $this->twoFactorService->clearFailedAttempts($user);
        
        // Record successful verification
        $this->activityService->recordTwoFactorSuccess($user, $request);
        
        // Trust device if requested
        if ($request->boolean('trust_device')) {
            $this->trustDevice($user, $request);
        }
        
        // Log the user in
        Auth::login($user, session('2fa:remember', false));
        
        // Clear 2FA session
        session()->forget('2fa:user_id');
        session()->forget('2fa:remember');
        
        // Update last activity
        $user->updateLastActivity();
        
        // Redirect to intended page
        $redirectTo = session()->pull('url.intended', route('dashboard'));
        
        return redirect()->intended($redirectTo)
            ->with('success', 'Two-factor authentication verified successfully.');
    }

    /**
     * Resend the two-factor authentication code
     */
    public function resendCode(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $userId = session('2fa:user_id') ?? $request->user_id;
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'error' => 'User not found'
            ], 404);
        }

        // Check rate limiting for resend requests
        $resendKey = '2fa_resend:' . $user->id;
        if (cache()->has($resendKey)) {
            return response()->json([
                'error' => 'Please wait before requesting another code.'
            ], 429);
        }

        // Send new code
        $sent = $this->twoFactorService->sendCode($user);
        
        if ($sent) {
            // Set rate limit (60 seconds)
            cache()->put($resendKey, true, now()->addSeconds(60));
            
            return response()->json([
                'success' => true,
                'message' => 'Verification code resent successfully.',
                'expires_in' => 600 // 10 minutes in seconds
            ]);
        }
        
        return response()->json([
            'error' => 'Failed to send verification code. Please try again.'
        ], 500);
    }

    /**
     * Show the 2FA setup form
     */
    public function showSetupForm(Request $request)
    {
        $user = $request->user();
        
        // If 2FA is already enabled, redirect to recovery codes page
        if ($user->two_factor_enabled) {
            return redirect()->route('2fa.recovery-codes')
                ->with('info', 'Two-factor authentication is already enabled.');
        }
        
        // Generate new secret if not exists
        if (!$user->two_factor_secret) {
            $user->two_factor_secret = $this->twoFactorService->generateSecret();
            $user->save();
        }
        
        // Get QR code URL
        $qrCodeUrl = $this->twoFactorService->getQRCodeUrl($user);
        
        return view('auth.two-factor-setup', [
            'user' => $user,
            'qr_code_url' => $qrCodeUrl,
            'secret' => $user->two_factor_secret,
            'method' => $request->user()->two_factor_method ?? 'email',
            'backup_codes_count' => config('auth.two_factor.backup_codes_count', 8)
        ]);
    }

    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string|size:6',
            'method' => 'sometimes|in:email,sms'
        ]);

        $user = $request->user();
        
        // Verify the code
        if (!$this->twoFactorService->verify($user, $request->verification_code)) {
            throw ValidationException::withMessages([
                'verification_code' => 'Invalid verification code. Please try again.'
            ]);
        }
        
        // Set 2FA method if provided
        if ($request->has('method')) {
            $user->two_factor_method = $request->method;
        }
        
        // Enable 2FA
        $this->twoFactorService->enable($user);
        
        // Generate backup codes
        $backupCodes = $this->twoFactorService->generateBackupCodes($user);
        
        // Log activity
        $this->activityService->recordActivity($user, $request, '2fa_enabled', 'web');
        
        // Return response with backup codes
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication enabled successfully.',
                'backup_codes' => $backupCodes
            ]);
        }
        
        // Store backup codes in session for display
        session()->flash('backup_codes', $backupCodes);
        
        return redirect()->route('2fa.recovery-codes')
            ->with('success', 'Two-factor authentication enabled successfully. Please save your backup codes.');
    }

    /**
     * Verify 2FA setup code
     */
    public function verifySetup(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);

        $user = $request->user();
        
        if (!$user->two_factor_secret) {
            return response()->json([
                'error' => '2FA not initialized. Please refresh the page.'
            ], 400);
        }
        
        $isValid = $this->twoFactorService->verify($user, $request->code);
        
        if ($request->wantsJson()) {
            return response()->json([
                'valid' => $isValid
            ]);
        }
        
        if (!$isValid) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }
        
        return redirect()->route('2fa.setup')
            ->with('code_validated', true);
    }

    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactor(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = $request->user();
        
        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Invalid password. Please try again.'
            ]);
        }
        
        // Disable 2FA
        $this->twoFactorService->disable($user);
        
        // Log activity
        $this->activityService->recordActivity($user, $request, '2fa_disabled', 'web');
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Two-factor authentication disabled successfully.'
            ]);
        }
        
        return redirect()->route('profile.settings')
            ->with('success', 'Two-factor authentication disabled successfully.');
    }

    /**
     * Show recovery codes
     */
    public function showRecoveryCodes(Request $request)
    {
        $user = $request->user();
        
        if (!$user->two_factor_enabled) {
            return redirect()->route('2fa.setup')
                ->with('warning', 'Please enable two-factor authentication first.');
        }
        
        $backupCodes = json_decode($user->two_factor_backup_codes ?? '[]', true);
        $remainingCount = count($backupCodes);
        
        // Check if we should show backup codes from session (during setup)
        $flashCodes = session('backup_codes');
        
        return view('auth.two-factor-recovery-codes', [
            'user' => $user,
            'backup_codes' => $flashCodes ?? $backupCodes,
            'remaining_count' => $remainingCount,
            'total_count' => config('auth.two_factor.backup_codes_count', 8),
            'show_warning' => $remainingCount <= 3
        ]);
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = $request->user();
        
        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Invalid password'], 400);
            }
            throw ValidationException::withMessages([
                'password' => 'Invalid password. Please try again.'
            ]);
        }
        
        // Generate new backup codes
        $backupCodes = $this->twoFactorService->generateBackupCodes($user);
        
        // Log activity
        $this->activityService->recordActivity($user, $request, '2fa_codes_regenerated', 'web');
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Recovery codes regenerated successfully.',
                'backup_codes' => $backupCodes
            ]);
        }
        
        session()->flash('backup_codes', $backupCodes);
        
        return redirect()->route('2fa.recovery-codes')
            ->with('success', 'New recovery codes generated. Please save them securely.');
    }

    /**
     * Trust current device (skip 2FA for future logins)
     */
    protected function trustDevice(User $user, Request $request): void
    {
        if (!config('auth.two_factor.trust_device', true)) {
            return;
        }
        
        // Generate device token
        $deviceToken = hash('sha256', $request->userAgent() . $request->ip() . $user->id);
        
        // Store trusted device
        \DB::table('trusted_devices')->updateOrInsert(
            [
                'user_id' => $user->id,
                'device_token' => $device_token
            ],
            [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'trusted_at' => now(),
                'expires_at' => now()->addDays(config('auth.two_factor.trust_device_days', 30))
            ]
        );
    }

    /**
     * Check if device is trusted
     */
    public function isDeviceTrusted(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        
        $deviceToken = hash('sha256', $request->userAgent() . $request->ip() . $user->id);
        
        $trusted = \DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('device_token', $deviceToken)
            ->where('expires_at', '>', now())
            ->exists();
        
        return $trusted;
    }

    /**
     * Remove trusted device
     */
    public function removeTrustedDevice(Request $request, $deviceId)
    {
        $user = $request->user();
        
        $deleted = \DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('id', $deviceId)
            ->delete();
        
        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Trusted device removed successfully.'
            ]);
        }
        
        return response()->json([
            'error' => 'Device not found.'
        ], 404);
    }

    /**
     * Get trusted devices list
     */
    public function getTrustedDevices(Request $request)
    {
        $user = $request->user();
        
        $devices = \DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->orderBy('trusted_at', 'desc')
            ->get();
        
        return response()->json([
            'devices' => $devices
        ]);
    }
}