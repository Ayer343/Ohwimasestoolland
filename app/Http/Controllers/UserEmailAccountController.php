<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserEmailAccount;
use App\Models\Email;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Webklex\IMAP\Facades\Client as ImapClient;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserEmailAccountController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var EmailService
     */
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    protected function getRoutePrefix(): string
    {
        $user = Auth::user();

        if (!$user) {
            return '';
        }

        if ($user->isSuperAdmin()) {
            return 'super-admin';
        } elseif ($user->isAdmin()) {
            return 'admin';
        } elseif ($user->isDeveloper()) {
            return 'developer';
        } elseif ($user->isLandlord()) {
            return 'landlord';
        } elseif ($user->isTenant()) {
            return 'tenant';
        }

        return '';
    }

    protected function getRouteNamePrefix(): string
    {
        $prefix = $this->getRoutePrefix();
        return $prefix ? $prefix . '.email-accounts' : 'email-accounts';
    }

    protected function getRouteName(string $name): string
    {
        $prefix = $this->getRouteNamePrefix();
        return $prefix . '.' . $name;
    }

    protected function getBaseUrl(): string
    {
        $prefix = $this->getRoutePrefix();
        return $prefix ? '/' . $prefix . '/email-accounts' : '/email-accounts';
    }

    protected function isAdmin(): bool
    {
        return Auth::user()->isAdmin();
    }

    protected function isSuperAdmin(): bool
    {
        return Auth::user()->isSuperAdmin();
    }

    protected function canManageEmailAccounts(): bool
    {
        return !Auth::user()->isAdmin();
    }

    // ========================================== //
    // 📧 BASE METHODS                            //
    // ========================================== //

    public function index()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            $emailAccounts = UserEmailAccount::with(['user' => function ($query) {
                $query->select('id', 'name', 'email', 'type');
            }])->withTrashed()->get();

            $primaryAccount = UserEmailAccount::where('is_primary', true)->first();

            $userNames = [];
            foreach ($emailAccounts as $account) {
                try {
                    $userNames[$account->user_id] = $account->user->name ?? 'Unknown User';
                } catch (\Exception $e) {
                    $userNames[$account->user_id] = 'Unknown User';
                }
            }

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'data' => $emailAccounts,
                    'primary' => $primaryAccount,
                    'user_names' => $userNames,
                    'is_admin_view' => true,
                ]);
            }

            return view('email-accounts.admin-index', compact('emailAccounts', 'primaryAccount', 'userNames'));
        }

        $emailAccounts = $user->emailAccounts()->withTrashed()->get();
        $primaryAccount = $user->primaryEmailAccount;

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $emailAccounts,
                'primary' => $primaryAccount,
                'is_admin_view' => false,
            ]);
        }

        return view('email-accounts.index', compact('emailAccounts', 'primaryAccount'));
    }

    public function create()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot link their own email accounts. Please contact Super Admin.'
                ], 403);
            }
            return redirect()->route($this->getRouteName('index'))
                ->with('error', 'Administrators cannot link their own email accounts. Please contact Super Admin.');
        }

        $emailAccounts = $user->emailAccounts()->get();
        $providers = [
            'gmail' => [
                'name' => 'Gmail',
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
            ],
            'outlook' => [
                'name' => 'Outlook/Hotmail',
                'imap_host' => 'outlook.office365.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.office365.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
            ],
            'yahoo' => [
                'name' => 'Yahoo Mail',
                'imap_host' => 'imap.mail.yahoo.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.mail.yahoo.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
            ],
            'custom' => [
                'name' => 'Custom/Other',
                'imap_host' => '',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
            ],
        ];

        return view('email-accounts.create', compact('providers', 'emailAccounts'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot link their own email accounts. Please contact Super Admin.'
                ], 403);
            }
            return redirect()->route($this->getRouteName('index'))
                ->with('error', 'Administrators cannot link their own email accounts. Please contact Super Admin.');
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:user_email_accounts,email',
            'password' => 'required|string|min:6',
            'provider' => ['required', Rule::in(['gmail', 'outlook', 'yahoo', 'custom'])],
            'display_name' => 'nullable|string|max:255',
            'imap_host' => 'required_if:provider,custom|string',
            'imap_port' => 'required_if:provider,custom|integer|min:1|max:65535',
            'imap_encryption' => 'required_if:provider,custom|in:ssl,tls,none',
            'smtp_host' => 'required_if:provider,custom|string',
            'smtp_port' => 'required_if:provider,custom|integer|min:1|max:65535',
            'smtp_encryption' => 'required_if:provider,custom|in:ssl,tls,none',
            'set_as_primary' => 'boolean',
            'sync_frequency' => ['required', Rule::in(['realtime', 'every_minute', 'every_five_minutes', 'every_fifteen_minutes', 'every_thirty_minutes', 'hourly', 'manual'])],
        ]);

        if ($validator->fails()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $providerSettings = $this->getProviderSettings($request->provider, $request);

            $verificationResult = $this->verifyCredentials(
                $request->email,
                $request->password,
                $providerSettings['imap_host'],
                $providerSettings['smtp_host'],
                $providerSettings['imap_port'] ?? 993,
                $providerSettings['smtp_port'] ?? 587,
                $providerSettings['imap_encryption'] ?? 'ssl',
                $providerSettings['smtp_encryption'] ?? 'tls'
            );

            $hasPrimary = $user->emailAccounts()->where('is_primary', true)->exists();
            $setAsPrimary = $request->boolean('set_as_primary') || !$hasPrimary;

            $emailAccount = UserEmailAccount::create([
                'user_id' => $user->id,
                'email' => $request->email,
                'display_name' => $request->display_name ?? $user->name,
                'reply_to_email' => $request->reply_to_email,
                'reply_to_name' => $request->reply_to_name,
                'imap_host' => $providerSettings['imap_host'],
                'imap_port' => $providerSettings['imap_port'],
                'imap_encryption' => $providerSettings['imap_encryption'],
                'imap_validate_cert' => false,
                'imap_timeout' => 30,
                'smtp_host' => $providerSettings['smtp_host'],
                'smtp_port' => $providerSettings['smtp_port'],
                'smtp_encryption' => $providerSettings['smtp_encryption'],
                'smtp_validate_cert' => false,
                'smtp_timeout' => 30,
                'encrypted_password' => $request->password,
                'provider' => $request->provider,
                'sync_frequency' => $request->sync_frequency,
                'status' => $verificationResult['success'] ? 'verified' : 'pending',
                'is_primary' => $setAsPrimary,
                'verified_at' => $verificationResult['success'] ? now() : null,
                'verification_error' => $verificationResult['success'] ? null : $verificationResult['error'],
                'verification_attempts' => 1,
                'last_verification_attempt_at' => now(),
                'is_connected' => $verificationResult['success'],
                'last_connected_at' => $verificationResult['success'] ? now() : null,
                'last_connection_error' => $verificationResult['success'] ? null : $verificationResult['error'],
            ]);

            if ($setAsPrimary) {
                $user->emailAccounts()
                    ->where('id', '!=', $emailAccount->id)
                    ->update(['is_primary' => false]);
            }

            DB::commit();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $verificationResult['success']
                        ? 'Email account linked and verified successfully!'
                        : 'Email account linked but verification failed. Please check your credentials.',
                    'data' => $emailAccount,
                    'verification' => $verificationResult,
                ]);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with('success', $verificationResult['success']
                    ? 'Email account linked and verified successfully!'
                    : 'Email account linked but verification failed. Please check your credentials.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Email account creation failed: ' . $e->getMessage(), [
                'email' => $request->email,
                'provider' => $request->provider,
                'trace' => $e->getTraceAsString(),
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to link email account: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to link email account: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(UserEmailAccount $emailAccount)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email account.');
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $emailAccount,
            ]);
        }

        return view('email-accounts.show', compact('emailAccount'));
    }

    public function edit(UserEmailAccount $emailAccount)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            abort(403, 'Administrators cannot edit email accounts. Please contact Super Admin.');
        }

        if ($emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email account.');
        }

        $providers = [
            'gmail' => 'Gmail',
            'outlook' => 'Outlook/Hotmail',
            'yahoo' => 'Yahoo Mail',
            'custom' => 'Custom/Other',
        ];

        return view('email-accounts.edit', compact('emailAccount', 'providers'));
    }

    public function update(Request $request, UserEmailAccount $emailAccount)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot update email accounts. Please contact Super Admin.'
                ], 403);
            }
            abort(403, 'Administrators cannot update email accounts. Please contact Super Admin.');
        }

        if ($emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email account.');
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', Rule::unique('user_email_accounts', 'email')->ignore($emailAccount->id)],
            'password' => 'nullable|string|min:6',
            'display_name' => 'nullable|string|max:255',
            'imap_host' => 'required|string',
            'imap_port' => 'required|integer|min:1|max:65535',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_encryption' => 'required|in:ssl,tls,none',
            'set_as_primary' => 'boolean',
            'sync_frequency' => ['required', Rule::in(['realtime', 'every_minute', 'every_five_minutes', 'every_fifteen_minutes', 'every_thirty_minutes', 'hourly', 'manual'])],
            'enable_signature' => 'boolean',
            'signature' => 'nullable|string',
            'enable_auto_reply' => 'boolean',
            'auto_reply_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $updateData = [
                'email' => $request->email,
                'display_name' => $request->display_name ?? $emailAccount->user->name,
                'reply_to_email' => $request->reply_to_email,
                'reply_to_name' => $request->reply_to_name,
                'imap_host' => $request->imap_host,
                'imap_port' => $request->imap_port,
                'imap_encryption' => $request->imap_encryption,
                'smtp_host' => $request->smtp_host,
                'smtp_port' => $request->smtp_port,
                'smtp_encryption' => $request->smtp_encryption,
                'sync_frequency' => $request->sync_frequency,
                'enable_signature' => $request->boolean('enable_signature'),
                'signature' => $request->signature,
                'enable_auto_reply' => $request->boolean('enable_auto_reply'),
                'auto_reply_message' => $request->auto_reply_message,
                'settings_changed_at' => now(),
            ];

            if ($request->filled('password')) {
                $updateData['encrypted_password'] = $request->password;
                $updateData['password_changed_at'] = now();
            }

            if ($request->filled('password') || $emailAccount->email !== $request->email) {
                $password = $request->filled('password')
                    ? $request->password
                    : $this->decryptPassword($emailAccount->encrypted_password);

                $verificationResult = $this->verifyCredentials(
                    $request->email,
                    $password,
                    $request->imap_host,
                    $request->smtp_host,
                    $request->imap_port,
                    $request->smtp_port,
                    $request->imap_encryption,
                    $request->smtp_encryption
                );

                $updateData['status'] = $verificationResult['success'] ? 'verified' : 'failed';
                $updateData['verified_at'] = $verificationResult['success'] ? now() : null;
                $updateData['verification_error'] = $verificationResult['success'] ? null : $verificationResult['error'];
                $updateData['verification_attempts'] = $emailAccount->verification_attempts + 1;
                $updateData['last_verification_attempt_at'] = now();
                $updateData['is_connected'] = $verificationResult['success'];
                $updateData['last_connected_at'] = $verificationResult['success'] ? now() : null;
                $updateData['last_connection_error'] = $verificationResult['success'] ? null : $verificationResult['error'];
            }

            $emailAccount->update($updateData);

            if ($request->boolean('set_as_primary')) {
                $emailAccount->user->emailAccounts()
                    ->where('id', '!=', $emailAccount->id)
                    ->update(['is_primary' => false]);
                $emailAccount->update(['is_primary' => true]);
            }

            DB::commit();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email account updated successfully!',
                    'data' => $emailAccount->fresh(),
                ]);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with('success', 'Email account updated successfully!');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Email account update failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email' => $request->email,
                'trace' => $e->getTraceAsString(),
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update email account: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update email account: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();

        Log::info('🔍 DESTROY METHOD CALLED with ID:', ['id' => $id]);

        try {
            $emailAccount = UserEmailAccount::findOrFail($id);

            if ($user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot delete email accounts. Please contact Super Admin.'
                ], 403);
            }

            if ($emailAccount->user_id !== $user->id && !$user->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this account.'
                ], 403);
            }

            if ($emailAccount->is_primary) {
                $newPrimary = $emailAccount->user->emailAccounts()
                    ->where('id', '!=', $emailAccount->id)
                    ->where('status', 'verified')
                    ->first();

                if ($newPrimary) {
                    $newPrimary->update(['is_primary' => true]);
                }
            }

            $emailAccount->delete();

            Log::info('✅ Email account deleted successfully', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email account unlinked successfully!',
                ]);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with('success', 'Email account unlinked successfully!');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('⚠️ Account not found for deletion', ['id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'Email account not found.'
            ], 404);

        } catch (Exception $e) {
            Log::error('❌ Email account deletion failed: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unlink email account: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to unlink email account: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        $user = Auth::user();
        $emailAccount = UserEmailAccount::withTrashed()->findOrFail($id);

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot restore email accounts. Please contact Super Admin.'
                ], 403);
            }
            abort(403, 'Administrators cannot restore email accounts. Please contact Super Admin.');
        }

        if ($emailAccount->user_id !== $user->id && !$user->isSuperAdmin()) {
            abort(403, 'You are not authorized to restore this account.');
        }

        try {
            $emailAccount->restore();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email account restored successfully!',
                    'data' => $emailAccount,
                ]);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with('success', 'Email account restored successfully!');

        } catch (Exception $e) {
            Log::error('Email account restoration failed: ' . $e->getMessage(), [
                'account_id' => $id,
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore email account: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to restore email account: ' . $e->getMessage());
        }
    }

    public function forceDelete($id)
    {
        $user = Auth::user();
        $emailAccount = UserEmailAccount::withTrashed()->findOrFail($id);

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot permanently delete email accounts. Please contact Super Admin.'
                ], 403);
            }
            abort(403, 'Administrators cannot permanently delete email accounts. Please contact Super Admin.');
        }

        if ($emailAccount->user_id !== $user->id && !$user->isSuperAdmin()) {
            abort(403, 'You are not authorized to permanently delete this account.');
        }

        try {
            $emailAccount->forceDelete();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email account permanently deleted!',
                ]);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with('success', 'Email account permanently deleted!');

        } catch (Exception $e) {
            Log::error('Email account force deletion failed: ' . $e->getMessage(), [
                'account_id' => $id,
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to permanently delete email account: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to permanently delete email account: ' . $e->getMessage());
        }
    }

    public function verify(Request $request)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Administrators cannot verify email credentials. Please contact Super Admin.'
            ], 403);
        }

        set_time_limit(120);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'provider' => ['required', Rule::in(['gmail', 'outlook', 'yahoo', 'custom'])],
            'imap_host' => 'required_if:provider,custom|string',
            'smtp_host' => 'required_if:provider,custom|string',
            'imap_port' => 'nullable|integer|min:1|max:65535',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'imap_encryption' => 'nullable|in:ssl,tls,none',
            'smtp_encryption' => 'nullable|in:ssl,tls,none',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->provider === 'custom') {
            $imapHost = $request->imap_host;
            $smtpHost = $request->smtp_host;
            $imapPort = $request->imap_port ?? 993;
            $smtpPort = $request->smtp_port ?? 587;
            $imapEncryption = $request->imap_encryption ?? 'ssl';
            $smtpEncryption = $request->smtp_encryption ?? 'tls';
        } else {
            $providerSettings = $this->getProviderSettings($request->provider, $request);
            $imapHost = $providerSettings['imap_host'];
            $smtpHost = $providerSettings['smtp_host'];
            $imapPort = $providerSettings['imap_port'] ?? 993;
            $smtpPort = $providerSettings['smtp_port'] ?? 587;
            $imapEncryption = $providerSettings['imap_encryption'] ?? 'ssl';
            $smtpEncryption = $providerSettings['smtp_encryption'] ?? 'tls';
        }

        $result = $this->verifyCredentials(
            $request->email,
            $request->password,
            $imapHost,
            $smtpHost,
            $imapPort,
            $smtpPort,
            $imapEncryption,
            $smtpEncryption
        );

        return response()->json($result);
    }

    public function reverify(UserEmailAccount $emailAccount)
    {
        set_time_limit(120);

        $user = Auth::user();

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot re-verify email accounts. Please contact Super Admin.'
                ], 403);
            }
            abort(403, 'Administrators cannot re-verify email accounts. Please contact Super Admin.');
        }

        if ($emailAccount->user_id !== $user->id && !$user->isSuperAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to re-verify this account.'
                ], 403);
            }
            abort(403, 'You are not authorized to re-verify this account.');
        }

        try {
            $password = $this->decryptPassword($emailAccount->encrypted_password);

            $verificationResult = $this->verifyCredentials(
                $emailAccount->email,
                $password,
                $emailAccount->imap_host,
                $emailAccount->smtp_host,
                $emailAccount->imap_port,
                $emailAccount->smtp_port,
                $emailAccount->imap_encryption,
                $emailAccount->smtp_encryption
            );

            $emailAccount->update([
                'status' => $verificationResult['success'] ? 'verified' : 'failed',
                'verified_at' => $verificationResult['success'] ? now() : null,
                'verification_error' => $verificationResult['success'] ? null : $verificationResult['error'],
                'verification_attempts' => ($emailAccount->verification_attempts ?? 0) + 1,
                'last_verification_attempt_at' => now(),
                'is_connected' => $verificationResult['success'],
                'last_connected_at' => $verificationResult['success'] ? now() : null,
                'last_connection_error' => $verificationResult['success'] ? null : $verificationResult['error'],
            ]);

            Log::info('Email account re-verification completed', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'success' => $verificationResult['success'],
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => $verificationResult['success'],
                    'message' => $verificationResult['success']
                        ? 'Account re-verified successfully!'
                        : 'Re-verification failed: ' . ($verificationResult['error'] ?? 'Unknown error'),
                    'verification' => $verificationResult,
                ], $verificationResult['success'] ? 200 : 422);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with($verificationResult['success'] ? 'success' : 'error',
                       $verificationResult['success']
                           ? 'Account re-verified successfully!'
                           : 'Re-verification failed: ' . ($verificationResult['error'] ?? 'Unknown error'));

        } catch (Exception $e) {
            Log::error('Email account re-verification failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $emailAccount->update([
                'status' => 'failed',
                'verification_error' => $e->getMessage(),
                'is_connected' => false,
                'last_connection_error' => $e->getMessage(),
                'last_verification_attempt_at' => now(),
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Re-verification failed: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Re-verification failed: ' . $e->getMessage());
        }
    }

    public function inbox(Request $request)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            $emailAccounts = UserEmailAccount::with('user')->get();

            if ($emailAccounts->isEmpty()) {
                return redirect()->route($this->getRouteName('index'))
                    ->with('warning', 'No email accounts have been linked. Please contact Super Admin.');
            }

            $emailIds = [];
            foreach ($emailAccounts as $account) {
                try {
                    $accountEmails = $account->emails()->where('folder', 'INBOX')->pluck('id')->toArray();
                    $emailIds = array_merge($emailIds, $accountEmails);
                } catch (\Exception $e) {
                }
            }

            $emails = Email::whereIn('id', $emailIds)
                ->orderBy('received_at', 'desc')
                ->paginate(20);

            return view('email-accounts.admin-inbox', compact('emailAccounts', 'emails'));
        }

        $emailAccount = $user->primaryEmailAccount;

        if (!$emailAccount) {
            return redirect()->route($this->getRouteName('index'))
                ->with('warning', 'Please link an email account first.');
        }

        $emails = $emailAccount->emails()
            ->where('folder', 'INBOX')
            ->orderBy('received_at', 'desc')
            ->paginate(20);

        return view('email-accounts.inbox', compact('emailAccount', 'emails'));
    }

    public function sent(Request $request)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            $emailAccounts = UserEmailAccount::with('user')->get();

            if ($emailAccounts->isEmpty()) {
                return redirect()->route($this->getRouteName('index'))
                    ->with('warning', 'No email accounts have been linked. Please contact Super Admin.');
            }

            $emailIds = [];
            foreach ($emailAccounts as $account) {
                try {
                    $accountEmails = $account->emails()->where('folder', 'SENT')->pluck('id')->toArray();
                    $emailIds = array_merge($emailIds, $accountEmails);
                } catch (\Exception $e) {
                }
            }

            $emails = Email::whereIn('id', $emailIds)
                ->orderBy('sent_at', 'desc')
                ->paginate(20);

            return view('email-accounts.admin-sent', compact('emailAccounts', 'emails'));
        }

        $emailAccount = $user->primaryEmailAccount;

        if (!$emailAccount) {
            return redirect()->route($this->getRouteName('index'))
                ->with('warning', 'Please link an email account first.');
        }

        $emails = $emailAccount->emails()
            ->where('folder', 'SENT')
            ->orderBy('sent_at', 'desc')
            ->paginate(20);

        return view('email-accounts.sent', compact('emailAccount', 'emails'));
    }

    public function compose()
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            $emailAccounts = UserEmailAccount::with('user')->get();

            if ($emailAccounts->isEmpty()) {
                return redirect()->route($this->getRouteName('index'))
                    ->with('warning', 'No email accounts have been linked. Please contact Super Admin.');
            }

            $defaultAccount = $emailAccounts->first();
            return view('email-accounts.admin-compose', compact('emailAccounts', 'defaultAccount'));
        }

        $emailAccount = $user->primaryEmailAccount;

        if (!$emailAccount) {
            return redirect()->route($this->getRouteName('index'))
                ->with('warning', 'Please link an email account first.');
        }

        return view('email-accounts.compose', compact('emailAccount'));
    }

    public function viewEmail(UserEmailAccount $emailAccount, $emailId)
    {
        set_time_limit(120);

        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email.');
        }

        $email = $emailAccount->emails()->findOrFail($emailId);

        if (empty($email->body) && !empty($email->message_id)) {
            try {
                $this->emailService->fetchAndStoreMessageBody($emailAccount, $email);
                $email->refresh();
            } catch (Exception $e) {
                Log::warning('Lazy body fetch failed in viewEmail', [
                    'email_id' => $email->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        if (!$email->is_read) {
            $email->update(['is_read' => true, 'read_at' => now()]);
        }

        return view('email-accounts.view-email', compact('emailAccount', 'email'));
    }

    /**
     * Send an email.
     *
     * ✅ PRIORITY for recipients (both here and in the client):
     *    1. A linked email account from `user_email_accounts`
     *         a. Primary account
     *         b. Any verified account
     *         c. Any linked account with an email
     *    2. The user's `email` column from the `users` table
     *    3. Skip the user if neither exists
     */
    public function sendEmail(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email_account_id' => 'required|exists:user_email_accounts,id',
            'to' => 'nullable|string',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'cc' => 'nullable|email',
            'bcc' => 'nullable|email',
            'recipient_ids' => 'nullable|string',
            'is_bulk' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $emailAccount = UserEmailAccount::findOrFail($request->email_account_id);

            if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
                if (request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access to this email account.'
                    ], 403);
                }
                abort(403, 'Unauthorized access to this email account.');
            }

            // ========================================== //
            // ✅ RESOLVE RECIPIENTS                       //
            // ========================================== //
            $resolvedRecipients = null;
            $isBulk = false;

            if ($request->filled('recipient_ids')) {
                $isBulk = true;

                $ids = collect(explode(',', (string) $request->input('recipient_ids')))
                    ->map(fn ($v) => (int) trim($v))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($ids)) {
                    return $this->sendEmailError('No valid recipients were selected.', $request);
                }

                // Eager-load email accounts with the primary → verified → any order
                $users = User::whereIn('id', $ids)
                    ->with(['emailAccounts' => function ($q) {
                        $q->orderByDesc('is_primary')
                          ->orderByRaw("CASE WHEN status = 'verified' THEN 1 ELSE 0 END DESC")
                          ->orderBy('id');
                    }])
                    ->get();

                // Apply the same priority as the client:
                // 1. Prefer a linked account (already sorted by priority)
                // 2. Otherwise fall back to the profile email
                $resolvedRecipients = $users->map(function (User $u) {
                    $primaryAccount = $u->emailAccounts->first();

                    if ($primaryAccount && !empty($primaryAccount->email)) {
                        return $primaryAccount->email;
                    }

                    if (!empty($u->email)) {
                        return $u->email;
                    }

                    return null;
                })
                ->filter()
                ->unique()
                ->values()
                ->all();

                if (empty($resolvedRecipients)) {
                    return $this->sendEmailError(
                        'None of the selected users have a linked email account or a profile email.',
                        $request
                    );
                }

                Log::info('Recipients resolved from user IDs', [
                    'account_id'     => $emailAccount->id,
                    'requested_ids'  => count($ids),
                    'resolved_count' => count($resolvedRecipients),
                ]);
            } else {
                $manualTo = trim((string) $request->input('to', ''));
                if ($manualTo === '' || !filter_var($manualTo, FILTER_VALIDATE_EMAIL)) {
                    return $this->sendEmailError('Please enter a valid recipient email address.', $request);
                }
                $resolvedRecipients = [$manualTo];
            }

            // ========================================== //
            // 🚀 SEND                                     //
            // ========================================== //
            if ($isBulk && count($resolvedRecipients) > 1) {
                $results = [
                    'total'      => count($resolvedRecipients),
                    'successful' => 0,
                    'failed'     => 0,
                    'errors'     => [],
                ];

                foreach ($resolvedRecipients as $recipient) {
                    $result = $this->emailService->sendEmailWithAccount(
                        $emailAccount,
                        $recipient,
                        $request->subject,
                        $request->body,
                        $request->cc,
                        $request->bcc
                    );

                    if ($result['success']) {
                        $results['successful']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'email' => $recipient,
                            'error' => $result['message'],
                        ];
                    }

                    usleep(120000);
                }

                Log::info('Bulk send completed', [
                    'account_id' => $emailAccount->id,
                    'total'      => $results['total'],
                    'successful' => $results['successful'],
                    'failed'     => $results['failed'],
                ]);

                if ($results['successful'] === 0) {
                    return $this->sendEmailError(
                        'Failed to send to all ' . $results['total'] . ' recipient(s). First error: '
                            . ($results['errors'][0]['error'] ?? 'unknown error'),
                        $request
                    );
                }

                $message = $results['failed'] === 0
                    ? 'Email sent successfully to all ' . $results['successful'] . ' recipient(s)!'
                    : "Sent to {$results['successful']} of {$results['total']} recipient(s); {$results['failed']} failed.";

                if (request()->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'data'    => $results,
                    ]);
                }

                return redirect()->route($this->getRouteName('sent'))
                    ->with($results['failed'] === 0 ? 'success' : 'warning', $message);
            }

            // Single send
            $singleRecipient = $resolvedRecipients[0];

            $result = $this->emailService->sendEmailWithAccount(
                $emailAccount,
                $singleRecipient,
                $request->subject,
                $request->body,
                $request->cc,
                $request->bcc
            );

            if ($result['success']) {
                if (request()->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $result['message']
                    ]);
                }
                return redirect()->route($this->getRouteName('sent'))
                    ->with('success', 'Email sent successfully!');
            }

            return $this->sendEmailError($result['message'] ?? 'Failed to send email.', $request);

        } catch (Exception $e) {
            Log::error('Email send failed: ' . $e->getMessage(), [
                'account_id' => $request->email_account_id,
                'to' => $request->to,
                'recipient_ids' => $request->recipient_ids,
                'trace' => $e->getTraceAsString(),
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send email: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to send email: ' . $e->getMessage())
                ->withInput();
        }
    }

    protected function sendEmailError(string $message, Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->back()->with('error', $message)->withInput();
    }

    public function markAsRead(UserEmailAccount $emailAccount, $emailId)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email.');
        }

        try {
            $email = $emailAccount->emails()->findOrFail($emailId);
            $email->update(['is_read' => true, 'read_at' => now()]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email marked as read.'
                ]);
            }

            return redirect()->back()->with('success', 'Email marked as read.');

        } catch (Exception $e) {
            Log::error('Mark as read failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email_id' => $emailId,
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark email as read.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to mark email as read.');
        }
    }

    public function markAsUnread(UserEmailAccount $emailAccount, $emailId)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email.');
        }

        try {
            $email = $emailAccount->emails()->findOrFail($emailId);
            $email->update(['is_read' => false, 'read_at' => null]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email marked as unread.'
                ]);
            }

            return redirect()->back()->with('success', 'Email marked as unread.');

        } catch (Exception $e) {
            Log::error('Mark as unread failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email_id' => $emailId,
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark email as unread.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to mark email as unread.');
        }
    }

    public function reply(Request $request, UserEmailAccount $emailAccount, $emailId)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email.');
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $originalEmail = $emailAccount->emails()->findOrFail($emailId);

            $result = $this->emailService->replyToEmail(
                $emailAccount,
                $originalEmail,
                $request->body
            );

            if ($result['success']) {
                return redirect()->route($this->getRouteName('view-email'), [$emailAccount->id, $emailId])
                    ->with('success', 'Reply sent successfully!');
            } else {
                return redirect()->back()
                    ->with('error', $result['message'])
                    ->withInput();
            }

        } catch (Exception $e) {
            Log::error('Reply failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email_id' => $emailId,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to send reply: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function forward(Request $request, UserEmailAccount $emailAccount, $emailId)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email.');
        }

        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $originalEmail = $emailAccount->emails()->findOrFail($emailId);

            $result = $this->emailService->forwardEmail(
                $emailAccount,
                $originalEmail,
                $request->to,
                $request->body
            );

            if ($result['success']) {
                return redirect()->route($this->getRouteName('sent'))
                    ->with('success', 'Email forwarded successfully!');
            } else {
                return redirect()->back()
                    ->with('error', $result['message'])
                    ->withInput();
            }

        } catch (Exception $e) {
            Log::error('Forward failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email_id' => $emailId,
                'to' => $request->to,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to forward email: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function deleteEmail(UserEmailAccount $emailAccount, $emailId)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email.');
        }

        try {
            $email = $emailAccount->emails()->findOrFail($emailId);
            $email->delete();

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email deleted successfully.'
                ]);
            }

            return redirect()->back()->with('success', 'Email deleted successfully.');

        } catch (Exception $e) {
            Log::error('Email deletion failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email_id' => $emailId,
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete email.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete email.');
        }
    }

    public function sync(UserEmailAccount $emailAccount)
    {
        set_time_limit(120);

        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email account.');
        }

        try {
            Log::info('Starting email sync', [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'user_id' => $emailAccount->user_id,
                'initiated_by' => $user->id,
            ]);

            $result = $this->emailService->syncAccount($emailAccount);

            Log::info('Email sync completed', [
                'account_id' => $emailAccount->id,
                'success' => $result['success'],
                'fetched' => $result['fetched'] ?? 0,
                'errors' => $result['errors'] ?? [],
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message'] ?? 'Sync completed',
                    'data' => $result,
                ]);
            }

            if ($result['success']) {
                return redirect()->back()
                    ->with('success', $result['message'] ?? 'Emails synced successfully!');
            } else {
                $errorMessage = $result['message'] ?? 'Sync failed';
                if (!empty($result['errors'])) {
                    $errorMessage .= ': ' . implode(', ', $result['errors']);
                }
                return redirect()->back()
                    ->with('error', $errorMessage);
            }

        } catch (Exception $e) {
            Log::error('Email sync exception: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'email' => $emailAccount->email,
                'trace' => $e->getTraceAsString(),
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to sync emails: ' . $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to sync emails: ' . $e->getMessage());
        }
    }

    public function setPrimary(UserEmailAccount $emailAccount)
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrators cannot set primary email accounts. Please contact Super Admin.'
                ], 403);
            }
            abort(403, 'Administrators cannot set primary email accounts. Please contact Super Admin.');
        }

        if ($emailAccount->user_id !== $user->id && !$user->isSuperAdmin()) {
            abort(403, 'You are not authorized to set primary for this account.');
        }

        try {
            DB::transaction(function () use ($emailAccount) {
                $emailAccount->user->emailAccounts()
                    ->where('id', '!=', $emailAccount->id)
                    ->update(['is_primary' => false]);
                $emailAccount->update(['is_primary' => true]);
            });

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Primary email account set successfully!',
                    'data' => $emailAccount->fresh(),
                ]);
            }

            return redirect()->route($this->getRouteName('index'))
                ->with('success', 'Primary email account set successfully!');

        } catch (Exception $e) {
            Log::error('Setting primary email failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to set primary email account: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to set primary email account: ' . $e->getMessage());
        }
    }

    public function stats(UserEmailAccount $emailAccount)
    {
        $user = Auth::user();

        if (!$user->isAdmin() && $emailAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized access to this email account.');
        }

        try {
            $stats = $this->emailService->getAccountStats($emailAccount);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (Exception $e) {
            Log::error('Fetching email stats failed: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========================================== //
    // 📧 RECIPIENT LIST                           //
    // ========================================== //

    /**
     * Return users that can be selected as recipients on the compose page.
     *
     * ✅ PRIORITY for `recipient_email`:
     *    1. A linked email account (primary → verified → any) from `user_email_accounts`
     *    2. The user's `email` column from the `users` table
     *    3. Exclude the user entirely if neither exists
     */
    public function listEmail(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 100);
            $limit = max(1, min(500, $limit));

            $users = User::query()
                ->with(['emailAccounts' => function ($q) {
                    // Sort: primary first, then verified, then any (by id)
                    $q->orderByDesc('is_primary')
                      ->orderByRaw("CASE WHEN status = 'verified' THEN 1 ELSE 0 END DESC")
                      ->orderBy('id');
                }])
                ->whereNotIn('type', [
                    User::TYPE_FORMER_LANDLORD,
                ])
                ->where(function ($q) {
                    // Must have a linked account OR an email column
                    $q->whereNotNull('email')
                      ->where('email', '!=', '')
                      ->orWhereHas('emailAccounts');
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();

            $payload = $users->map(function (User $u) {
                // ─────────────────────────────────────
                // ✅ PRIORITY 1 — Linked email account
                // ─────────────────────────────────────
                $recipientEmail = null;
                $chosenAccount  = null;

                $firstAccount = $u->emailAccounts->first();

                if ($firstAccount && !empty($firstAccount->email)) {
                    $recipientEmail = $firstAccount->email;
                    $chosenAccount  = $firstAccount;
                }

                // ─────────────────────────────────────
                // ✅ PRIORITY 2 — User's own email column
                // ─────────────────────────────────────
                if (empty($recipientEmail) && !empty($u->email)) {
                    $recipientEmail = $u->email;
                }

                // ─────────────────────────────────────
                // ✅ PRIORITY 3 — No email → exclude
                // ─────────────────────────────────────
                if (empty($recipientEmail)) {
                    return null;
                }

                // Metadata about accounts so the client can show a badge
                $accountMeta = $u->emailAccounts->map(function ($a) {
                    return [
                        'email'      => $a->email,
                        'status'     => $a->status,
                        'is_primary' => (bool) $a->is_primary,
                        'provider'   => $a->provider,
                    ];
                })->values()->all();

                return [
                    'id'               => $u->id,
                    'name'             => $u->name,
                    'email'            => $u->email,
                    'type'             => (int) $u->type,
                    'primary_role'     => $u->primary_role?->slug ?? null,
                    'status'           => $u->status,
                    'recipient_email'  => $recipientEmail,
                    'recipient_source' => $chosenAccount ? 'linked' : 'profile',
                    'email_accounts'   => $accountMeta,
                ];
            })
            ->filter()
            ->values()
            ->all();

            return response()->json([
                'success' => true,
                'users'   => $payload,
                'count'   => count($payload),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load users for email picker', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load users: ' . $e->getMessage(),
                'users'   => [],
            ], 500);
        }
    }

    // ========================================== //
    // 🔧 PROVIDER SETTINGS & VERIFICATION        //
    // ========================================== //

    private function getProviderSettings(string $provider, Request $request): array
    {
        $settings = [
            'gmail' => [
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
            ],
            'outlook' => [
                'imap_host' => 'outlook.office365.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.office365.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
            ],
            'yahoo' => [
                'imap_host' => 'imap.mail.yahoo.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.mail.yahoo.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
            ],
            'custom' => [
                'imap_host' => '',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
            ],
        ];

        if ($provider === 'custom') {
            return [
                'imap_host' => $request->imap_host,
                'imap_port' => $request->imap_port ?? 993,
                'imap_encryption' => $request->imap_encryption ?? 'ssl',
                'smtp_host' => $request->smtp_host,
                'smtp_port' => $request->smtp_port ?? 587,
                'smtp_encryption' => $request->smtp_encryption ?? 'tls',
            ];
        }

        return $settings[$provider] ?? $settings['custom'];
    }

    private function decryptPassword(?string $password): ?string
    {
        if (empty($password)) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($password);
        } catch (\Exception $e) {
            return $password;
        }
    }

    private function verifyCredentials(
        string $email,
        string $password,
        string $imapHost,
        string $smtpHost,
        int $imapPort = 993,
        int $smtpPort = 587,
        string $imapEncryption = 'ssl',
        string $smtpEncryption = 'tls'
    ): array {
        $result = [
            'success' => false,
            'imap' => false,
            'smtp' => false,
            'error' => null,
            'warning' => null,
            'details' => [],
        ];

        try {
            Log::debug('Starting email verification', [
                'email' => $email,
                'imap_host' => $imapHost,
                'imap_port' => $imapPort,
                'imap_encryption' => $imapEncryption,
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_encryption' => $smtpEncryption,
            ]);

            $imapConnected = false;
            $imapError = null;

            if (empty($imapHost)) {
                $imapError = 'IMAP host is empty';
                $result['details']['imap_error'] = $imapError;
                Log::warning('IMAP host missing, skipping IMAP verification');
            } else {
                $encryption = $imapEncryption;
                if ($encryption === 'none' || $encryption === '' || $encryption === null) {
                    $encryption = false;
                }

                $config = [
                    'host'           => $imapHost,
                    'port'           => (int) $imapPort,
                    'protocol'       => 'imap',
                    'encryption'     => $encryption,
                    'validate_cert'  => false,
                    'username'       => $email,
                    'password'       => $password,
                    'authentication' => null,
                    'timeout'        => 15,
                    'extensions'     => [],
                    'proxy'          => [
                        'socket'          => null,
                        'request_fulluri' => false,
                        'username'        => null,
                        'password'        => null,
                    ],
                ];

                Config::set('imap.accounts.default', $config);

                try {
                    $client = ImapClient::account('default');
                    $client->connect();

                    if ($client->isConnected()) {
                        $imapConnected = true;
                        $result['details']['imap_encryption'] = $imapEncryption;
                        $result['details']['imap_port'] = $imapPort;
                        $result['details']['imap_status'] = 'Connected successfully';

                        try {
                            $folder = $client->getFolder('INBOX');
                            if ($folder) {
                                $result['details']['total_messages'] = $folder->query()->all()->count();
                            }
                        } catch (\Exception $e) {
                        }

                        $client->disconnect();
                        Log::info('IMAP connection successful via webklex/laravel-imap');
                    } else {
                        $imapError = 'IMAP client reports not connected';
                    }
                } catch (\Exception $e) {
                    $imapError = $e->getMessage();
                    Log::debug('IMAP connection failed: ' . $imapError);
                }

                if (!$imapConnected) {
                    $result['details']['imap_error'] = $imapError ?? 'Connection failed';
                    Log::warning('IMAP connection failed, but continuing with SMTP verification');
                }
            }

            $result['imap'] = $imapConnected;

            $smtpConnected = false;
            $smtpError = null;

            $smtpConfigs = [
                ['port' => $smtpPort, 'encryption' => $smtpEncryption],
                ['port' => 587, 'encryption' => 'tls'],
                ['port' => 465, 'encryption' => 'ssl'],
            ];

            $seen = [];
            $uniqueConfigs = [];
            foreach ($smtpConfigs as $c) {
                $key = $c['port'] . '|' . $c['encryption'];
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $uniqueConfigs[] = $c;
                }
            }

            foreach ($uniqueConfigs as $config) {
                try {
                    Log::debug("Testing SMTP with {$config['encryption']} on port {$config['port']}");

                    $smtpResult = $this->testSmtpConnection(
                        $smtpHost,
                        $config['port'],
                        $email,
                        $password,
                        $config['encryption']
                    );

                    if ($smtpResult['success']) {
                        $smtpConnected = true;
                        $result['details']['smtp_encryption'] = $config['encryption'];
                        $result['details']['smtp_port'] = $config['port'];
                        $result['details']['smtp_status'] = 'Connected successfully';

                        Log::info('SMTP connection successful', [
                            'host' => $smtpHost,
                            'port' => $config['port'],
                            'encryption' => $config['encryption']
                        ]);
                        break;
                    } else {
                        $smtpError = $smtpResult['error'] ?? 'Connection failed';
                        Log::debug("SMTP connection failed: " . $smtpError);
                    }

                } catch (Exception $e) {
                    $smtpError = $e->getMessage();
                    Log::debug("SMTP connection failed: " . $e->getMessage());
                    continue;
                }
            }

            $result['smtp'] = $smtpConnected;

            if ($smtpConnected) {
                $result['details']['smtp_status'] = 'Connected successfully';
            } else {
                $result['details']['smtp_error'] = $smtpError ?? 'All connection attempts failed';
            }

            if ($result['imap'] && $result['smtp']) {
                $result['success'] = true;
            } elseif (!$result['imap'] && $result['smtp']) {
                $result['success'] = true;
                $result['warning'] = 'SMTP verified successfully, but IMAP connection failed. You can send emails, but reading incoming emails may not work. Error: '
                    . ($result['details']['imap_error'] ?? 'unknown');
            } else {
                $errors = [];
                if (!$result['imap']) {
                    $errors[] = 'IMAP: ' . ($result['details']['imap_error'] ?? 'Connection failed');
                }
                if (!$result['smtp']) {
                    $errors[] = 'SMTP: ' . ($result['details']['smtp_error'] ?? 'Connection failed');
                }
                $result['error'] = implode('; ', $errors);
            }

            if ($result['success']) {
                Log::info('Email verification successful', [
                    'email' => $email,
                    'imap_verified' => $result['imap'],
                    'smtp_verified' => $result['smtp'],
                ]);
            } else {
                Log::warning('Email verification failed', [
                    'email' => $email,
                    'error' => $result['error'],
                    'details' => $result['details'],
                ]);
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $result['details']['exception'] = $e->getMessage();
            Log::error('Email verification exception: ' . $e->getMessage(), [
                'email' => $email,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    private function testSmtpConnection(string $host, int $port, string $username, string $password, string $encryption = 'tls'): array
    {
        $result = [
            'success' => false,
            'error' => null,
            'details' => [],
        ];

        $timeout = 30;
        $errno = 0;
        $errstr = '';
        $fp = null;

        try {
            Log::debug("Testing SMTP with {$encryption} on port {$port}");

            $connectionHost = $host;
            if ($encryption === 'ssl') {
                $connectionHost = 'ssl://' . $host;
            }

            $fp = @fsockopen($connectionHost, $port, $errno, $errstr, $timeout);

            if (!$fp) {
                $result['error'] = "Connection failed: $errstr ($errno)";
                return $result;
            }

            stream_set_timeout($fp, $timeout);

            $response = $this->readSmtpResponse($fp);
            if (strpos($response, '220') !== 0) {
                $result['error'] = "Invalid server response: " . trim($response);
                fclose($fp);
                return $result;
            }

            fputs($fp, "EHLO localhost\r\n");
            $response = $this->readSmtpResponse($fp);

            if (strpos($response, '250') !== 0) {
                fputs($fp, "HELO localhost\r\n");
                $response = $this->readSmtpResponse($fp);
                if (strpos($response, '250') !== 0) {
                    $result['error'] = "EHLO/HELO failed: " . trim($response);
                    fclose($fp);
                    return $result;
                }
            }

            if ($encryption === 'tls') {
                fputs($fp, "STARTTLS\r\n");
                $response = $this->readSmtpResponse($fp);

                if (strpos($response, '220') !== 0) {
                    $result['error'] = "STARTTLS failed: " . trim($response);
                    fclose($fp);
                    return $result;
                }

                $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
                    $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
                }

                if (!stream_socket_enable_crypto($fp, true, $crypto_method)) {
                    $result['error'] = "TLS handshake failed";
                    fclose($fp);
                    return $result;
                }

                fputs($fp, "EHLO localhost\r\n");
                $response = $this->readSmtpResponse($fp);
                if (strpos($response, '250') !== 0) {
                    $result['error'] = "EHLO after TLS failed: " . trim($response);
                    fclose($fp);
                    return $result;
                }
            }

            fputs($fp, "AUTH LOGIN\r\n");
            $response = $this->readSmtpResponse($fp);

            if (strpos($response, '334') !== 0) {
                fputs($fp, "AUTH PLAIN " . base64_encode("\0" . $username . "\0" . $password) . "\r\n");
                $response = $this->readSmtpResponse($fp);
                if (strpos($response, '235') !== 0) {
                    $result['error'] = "Authentication failed (both LOGIN and PLAIN): " . trim($response);
                    fclose($fp);
                    return $result;
                }
            } else {
                fputs($fp, base64_encode($username) . "\r\n");
                $response = $this->readSmtpResponse($fp);
                if (strpos($response, '334') !== 0) {
                    $result['error'] = "Username authentication failed: " . trim($response);
                    fclose($fp);
                    return $result;
                }

                fputs($fp, base64_encode($password) . "\r\n");
                $response = $this->readSmtpResponse($fp);
                if (strpos($response, '235') !== 0) {
                    $result['error'] = "Password authentication failed: " . trim($response);
                    fclose($fp);
                    return $result;
                }
            }

            fputs($fp, "QUIT\r\n");
            fclose($fp);

            $result['success'] = true;
            return $result;

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            if ($fp && is_resource($fp)) {
                fclose($fp);
            }
            return $result;
        }
    }

    private function readSmtpResponse($fp): string
    {
        $response = '';
        while ($line = fgets($fp, 1024)) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
            if (strlen($response) > 8192) {
                break;
            }
        }
        return $response;
    }
}