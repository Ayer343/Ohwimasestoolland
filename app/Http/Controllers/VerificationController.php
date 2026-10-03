<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send phone verification code to current user
     */
    public function sendPhoneVerification()
    {
        $user = Auth::user();

        if (!$user->phone) {
            return response()->json([
                'success' => false,
                'message' => 'No phone number associated with your account'
            ], 400);
        }

        if (!$user->canSendVerificationCode()) {
            $waitTime = $user->getVerificationWaitTime();
            return response()->json([
                'success' => false,
                'message' => "Please wait {$waitTime} seconds before requesting another verification code"
            ], 429);
        }

        try {
            $verificationCode = $this->generatePhoneVerificationCode($user);

            // Send verification code via SMS
            $message = "Your verification code for " . config('app.name') . " is: {$verificationCode}. Valid for 10 minutes.";
            
            $smsResult = $this->smsService->sendSMS(
                $user->phone,
                $message,
                [
                    'is_test' => false,
                    'type' => 'phone_verification',
                    'user_id' => $user->id
                ]
            );

            if ($smsResult['success']) {
                Log::info("Phone verification code sent to user", [
                    'user_id' => $user->id,
                    'phone' => $this->maskPhone($user->phone),
                    'provider' => $smsResult['provider'] ?? 'unknown'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent successfully to ' . $this->maskPhone($user->phone),
                    'method' => $smsResult['provider'] ?? 'SMS',
                    'wait_time' => $user->getVerificationWaitTime()
                ]);
            } else {
                Log::error("Failed to send verification code SMS", [
                    'user_id' => $user->id,
                    'error' => $smsResult['message']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code: ' . $smsResult['message']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send verification code: " . $e->getMessage(), [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify current user's phone number
     */
    public function verifyPhone(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|string|size:6|regex:/^[0-9]+$/'
        ], [
            'verification_code.required' => 'Verification code is required',
            'verification_code.size' => 'Verification code must be 6 digits',
            'verification_code.regex' => 'Verification code must contain only numbers',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code format',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($this->verifyPhoneWithCode($user, $request->verification_code)) {
                // Update profile completion
                app(ProfileController::class)->updateProfileCompletion($user);

                DB::commit();

                Log::info("Phone number verified successfully", [
                    'user_id' => $user->id,
                    'phone' => $this->maskPhone($user->phone)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Phone number verified successfully!',
                    'profile_completion' => app(ProfileController::class)->calculateProfileCompletion($user),
                    'is_phone_verified' => true
                ]);
            } else {
                DB::rollBack();

                Log::warning("Phone verification failed - invalid or expired code", [
                    'user_id' => $user->id,
                    'provided_code' => $request->verification_code
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to verify phone for user ' . $user->id . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify phone number. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify user phone number as admin (manual verification)
     */
    public function adminVerifyPhone($id)
    {
        // Only super admin can access
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::findOrFail($id);

        DB::beginTransaction();

        try {
            $user->update([
                'phone_verified_at' => now(),
                'phone_verification_code' => null,
                'phone_verification_sent_at' => null,
            ]);

            DB::commit();

            return back()->with('success', 'Phone number verified successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to verify phone: ' . $e->getMessage());

            return back()->with('error', 'Failed to verify phone number. Please try again.');
        }
    }

    /**
     * Send phone verification code (admin)
     */
    public function sendVerificationCode($id)
    {
        // Only super admin can access
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $user = User::findOrFail($id);

        if (!$user->phone) {
            return response()->json([
                'success' => false,
                'message' => 'No phone number associated with this account'
            ], 400);
        }

        if (!$user->canSendVerificationCode()) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before sending another verification code'
            ], 400);
        }

        try {
            $verificationCode = $this->generatePhoneVerificationCode($user);

            // Send verification code via SMS
            $message = "Your phone verification code is: {$verificationCode}. Valid for 10 minutes.";
            
            $smsResult = $this->smsService->sendSMS(
                $user->phone,
                $message,
                [
                    'is_test' => false,
                    'type' => 'phone_verification',
                    'user_id' => $user->id
                ]
            );

            if ($smsResult['success']) {
                Log::info("Phone verification code sent by admin", [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    'sent_by' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent successfully',
                    'method' => $smsResult['provider'] ?? 'SMS'
                ]);
            } else {
                Log::error("Failed to send verification code SMS", [
                    'user_id' => $user->id,
                    'error' => $smsResult['message']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code: ' . $smsResult['message']
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send verification code: " . $e->getMessage(), [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Generate phone verification code
     */
    private function generatePhoneVerificationCode(User $user): string
    {
        $code = sprintf('%06d', random_int(1, 999999));
        
        $user->update([
            'phone_verification_code' => $code,
            'phone_verification_sent_at' => now(),
        ]);

        return $code;
    }

    /**
     * Verify phone with code
     */
    private function verifyPhoneWithCode(User $user, string $code): bool
    {
        if (!$user->phone_verification_code || 
            !$user->phone_verification_sent_at ||
            $user->phone_verification_code !== $code) {
            return false;
        }

        // Check if code is expired (15 minutes)
        if ($user->phone_verification_sent_at->addMinutes(15)->isPast()) {
            return false;
        }

        return $user->update([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
        ]);
    }

    /**
     * Mask phone number for display
     */
    private function maskPhone($phone): string
    {
        if (empty($phone) || strlen($phone) <= 6) {
            return $phone ?? '';
        }
        
        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }
}