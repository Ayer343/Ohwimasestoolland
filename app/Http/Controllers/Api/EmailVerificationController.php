<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'token'   => 'required|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $user = null;

        if ($request->filled('user_id')) {
            $user = User::find($request->user_id);
        }

        // If we don't have a user_id, try to find the user by a signed hash.
        // Laravel's built-in signed URL verification uses email + timestamp.
        // If you issue verification links via a custom token in the users
        // table, look the user up by that token instead.
        if (!$user) {
            $user = User::where('email_verification_token', $request->token)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification link.',
            ], 422);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        $user->forceFill([
            'email_verified_at'            => now(),
            'email_verification_token'     => null,
        ])->save();

        Log::info('Email verified', ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function resend(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'email'   => 'nullable|email|exists:users,email',
        ]);

        $user = null;

        if ($request->filled('user_id')) {
            $user = User::find($request->user_id);
        } elseif ($request->filled('email')) {
            $user = User::where('email', $request->email)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        // Issue a fresh token and (optionally) send the email.
        $token = $user->email_verification_token ?? bin2hex(random_bytes(32));
        $user->forceFill(['email_verification_token' => $token])->save();

        // Trigger your Mail/Notification here, e.g.:
        // $user->notify(new VerifyEmailNotification($token));

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent.',
        ]);
    }
}