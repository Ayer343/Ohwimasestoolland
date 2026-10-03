<?php

namespace App\Services;

use App\Models\DeveloperSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class BillingAccessService
{
    /**
     * Routes a super admin may always reach, even when restricted.
     */
    public const SUPER_ADMIN_ALWAYS_ALLOWED = [
        'superadmin.billing.*',
        'superadmin.billing.dashboard',
        'superadmin.billing.view-agreement',
        'superadmin.billing.view-agreement-for-signing',
        'superadmin.billing.submit-signature',
        'superadmin.billing.view-invoice',
        'superadmin.billing.invoice-list',
        'superadmin.billing.download-invoice',
        'superadmin.billing.download-signed-agreement',
        'superadmin.billing.download-signature',
        'superadmin.billing.record-payment',
        'superadmin.billing.record-own-payment',
        'superadmin.billing.payment-callback',
        'superadmin.billing.payment-history',
        'superadmin.billing.shared-payment-status',
        'superadmin.billing.webhook',
        'logout',
        'login',
        'password.*',
        'notifications.*',
        'billing.pay-now',
    ];

    /**
     * Routes that must remain open to landlords and tenants
     * regardless of billing state — because their payments
     * are the resolution path.
     */
    public const TENANT_LANDLORD_ALWAYS_ALLOWED = [
        'logout',
        'login',
        'password.*',
        'notifications.*',
        'billing.suspended',
    ];

    public function currentDeveloper(): ?DeveloperSetting
    {
        return DeveloperSetting::current();
    }

    /* ============================================================
     | SUPER ADMIN / ADMIN
     * ============================================================ */

    public function shouldRestrictSuperAdmin(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user || $user->type !== User::TYPE_SUPER_ADMIN) {
            return false;
        }

        $developer = $this->currentDeveloper();

        return $developer && $developer->isBillingRestrictingSuperAdmin();
    }

    public function shouldRestrictAdminToReadOnly(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user || $user->type !== User::TYPE_ADMIN) {
            return false;
        }

        $developer = $this->currentDeveloper();

        return $developer && $developer->isBillingRestrictingAdmins();
    }

    public function isAllowedForRestrictedSuperAdmin(string $routeName): bool
    {
        foreach (self::SUPER_ADMIN_ALWAYS_ALLOWED as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }
        return false;
    }

    /* ============================================================
     | LANDLORD / TENANT
     * ============================================================ */

    public function shouldHardLockLandlordOrTenant(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user || !in_array($user->type, [User::TYPE_LANDLORD, User::TYPE_TENANT], true)) {
            return false;
        }

        $developer = $this->currentDeveloper();

        return $developer && $developer->isBillingBlockingLandlordsAndTenants();
    }

    public function isAllowedForHardLockedTenantLandlord(string $routeName): bool
    {
        foreach (self::TENANT_LANDLORD_ALWAYS_ALLOWED as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return true;
            }
        }
        return false;
    }

    /* ============================================================
     | WRITE-GUARD FOR CONTROLLERS (defensive layer)
     * ============================================================ */

    /**
     * Throws a *handled* response if the current user may not perform
     * a billing-sensitive write operation.
     *
     * Behavior by request type:
     *
     *   - Web form POST  → redirect()->back()->withInput() + flash
     *                       ('billing_blocked', 'error' message).
     *                       User stays in context, keeps form data,
     *                       sees a banner explaining the block.
     *
     *   - AJAX / API     → JSON body with error code, message,
     *                       billing_state, operation, and a link to
     *                       the billing dashboard. HTTP 402 (or 403
     *                       for admins) — semantically correct — but
     *                       the *body* is app-specific, not Laravel's
     *                       default error page.
     *
     *   - Unauthenticated → abort(401) as before.
     *
     * The method never returns normally when the user is blocked.
     * It always throws an HTTP exception carrying the appropriate
     * response — which is exactly the semantics callers expect from
     * an "assert" method.
     *
     * @param  string  $operation  Human label for logs and messages
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function assertCanPerformWrite(string $operation = 'this action'): void
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // ------------------------------------------------------------
        // Landlords & tenants — hard lock only if the developer opted in.
        // ------------------------------------------------------------
        if (in_array($user->type, [User::TYPE_LANDLORD, User::TYPE_TENANT], true)) {
            if ($this->shouldHardLockLandlordOrTenant($user)) {
                Log::warning('[BillingAccess] Landlord/Tenant hard-locked', [
                    'user_id'   => $user->id,
                    'user_type' => $user->type,
                    'operation' => $operation,
                ]);

                $this->deny(
                    user:         $user,
                    errorCode:    'landlord_tenant_locked',
                    message:      'Service temporarily suspended pending billing resolution.',
                    status:       402,
                    operation:    $operation
                );
            }

            return;
        }

        // ------------------------------------------------------------
        // Super admin — blocked from write actions when restricted.
        // ------------------------------------------------------------
        if ($user->type === User::TYPE_SUPER_ADMIN && $this->shouldRestrictSuperAdmin($user)) {
            Log::warning('[BillingAccess] Super admin blocked from write', [
                'user_id'   => $user->id,
                'operation' => $operation,
            ]);

            $this->deny(
                user:          $user,
                errorCode:     'billing_restricted',
                message:       'Your system billing is overdue. Settle your invoice to resume administrative actions.',
                status:        402,
                operation:     $operation,
                redirectRoute: 'superadmin.billing.dashboard'
            );
        }

        // ------------------------------------------------------------
        // Admin staff — read-only when restricted.
        // ------------------------------------------------------------
        if ($user->type === User::TYPE_ADMIN && $this->shouldRestrictAdminToReadOnly($user)) {
            Log::warning('[BillingAccess] Admin blocked from write (read-only)', [
                'user_id'   => $user->id,
                'operation' => $operation,
            ]);

            $this->deny(
                user:         $user,
                errorCode:    'billing_read_only',
                message:      'Administrative actions are locked while the system billing is overdue. Please contact your super admin.',
                status:       403,
                operation:    $operation
            );
        }
    }

    /**
     * ✅ MODERN: Emit the right kind of denial for the request type.
     *
     * Web requests get a redirect-back with a flash + preserved input.
     * AJAX / API requests get structured JSON the front-end can act on.
     *
     * 402 is used (Payment Required) — semantically correct for a
     * billing-locked write — but the response body is app-specific,
     * never Laravel's default error page.
     *
     * This method *always throws* via abort($response). It never returns.
     */
    protected function deny(
        ?User $user,
        string $errorCode,
        string $message,
        int $status = 402,
        string $operation = 'this action',
        ?string $redirectRoute = null
    ): void {
        $request = request();

        // ------------------------------------------------------------
        // API / AJAX path — structured JSON.
        // ------------------------------------------------------------
        $isApi = $request->expectsJson()
              || $request->ajax()
              || $request->is('api/*')
              || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isApi) {
            $payload = [
                'success'       => false,
                'error'         => $errorCode,
                'message'       => $message,
                'operation'     => $operation,
                'billing_state' => $this->currentDeveloper()?->billing_state,
            ];

            // Where the user can go to fix this
            if ($errorCode === 'billing_restricted' && Route::has('superadmin.billing.dashboard')) {
                $payload['billing_dashboard'] = route('superadmin.billing.dashboard');
                $payload['retry_after']       = 'payment';
            }

            if ($redirectRoute && Route::has($redirectRoute)) {
                $payload['redirect_to'] = route($redirectRoute);
            }

            abort(response()->json($payload, $status));
        }

        // ------------------------------------------------------------
        // Web path — redirect back with input + flash.
        // ------------------------------------------------------------
        if ($redirectRoute && Route::has($redirectRoute)) {
            $redirect = redirect()->route($redirectRoute);
        } else {
            $redirect = redirect()->back()->withInput();
        }

        $redirect
            ->with('billing_blocked', true)
            ->with('billing_blocked_code', $errorCode)
            ->with('billing_blocked_operation', $operation)
            ->with('error', $message);

        abort($redirect);
    }

    /**
     * Non-throwing variant.
     *
     * Returns true if the user *may* perform a write, false otherwise.
     * Never mutates global auth state — uses a local check instead.
     */
    public function canPerformWrite(?User $user = null, string $operation = 'this action'): bool
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return false;
        }

        // Landlords / tenants
        if (in_array($user->type, [User::TYPE_LANDLORD, User::TYPE_TENANT], true)) {
            return !$this->shouldHardLockLandlordOrTenant($user);
        }

        // Super admin
        if ($user->type === User::TYPE_SUPER_ADMIN && $this->shouldRestrictSuperAdmin($user)) {
            return false;
        }

        // Admin
        if ($user->type === User::TYPE_ADMIN && $this->shouldRestrictAdminToReadOnly($user)) {
            return false;
        }

        return true;
    }

    /* ============================================================
     | BANNER DATA FOR VIEWS
     * ============================================================ */

    public function bannerPayload(): array
    {
        $developer = $this->currentDeveloper();

        if (!$developer) {
            return ['visible' => false];
        }

        $state = $developer->billing_state;

        return [
            'visible'        => $state !== 'not_configured' && $state !== 'active_current',
            'state'          => $state,
            'days_until_due' => $developer->daysUntilBillingDue(),
            'agreement'      => $developer->primary_billing_agreement,
            'is_overdue'     => in_array($state, ['active_overdue', 'active_suspended'], true),
            'is_due_soon'    => $state === 'active_due_soon',
            'is_pending_sig' => $state === 'pending_signature',
        ];
    }
}