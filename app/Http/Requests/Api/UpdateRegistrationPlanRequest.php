<?php

namespace App\Http\Requests\Api;

use App\Models\RegistrationPlan;
use Illuminate\Support\Facades\Log;

class UpdateRegistrationPlanRequest extends StoreRegistrationPlanRequest
{
    /**
     * Authorizes the request through the RegistrationPlanPolicy.
     *
     * Delegates to `update` (not `create`) and passes the actual plan
     * instance so the policy can do owner-scoped checks if needed.
     *
     * The policy must be registered (auto-discovered or listed in
     * AuthServiceProvider::$policies). Without it, `can()` returns false
     * for EVERY user — including super admins — and the API returns 403.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Resolve the plan from the route parameter (`{id}` for our API).
        // Falls back to `{plan}` in case the route later switches to
        // route-model binding.
        $planId = $this->route('id') ?? $this->route('plan');
        $plan = $planId instanceof RegistrationPlan
            ? $planId
            : RegistrationPlan::withTrashed()->find($planId);

        if ($plan === null) {
            // No plan to update — let the controller's findOrFail return 404.
            // Authorizing against a nonexistent plan would be misleading.
            return true;
        }

        $allowed = $user->can('update', $plan);

        // TEMP DEBUG: log the exact reason for a deny. Remove once the
        // policy is confirmed working in production.
        if (!$allowed) {
            Log::warning('RegistrationPlan update denied', [
                'plan_id'         => $plan->id,
                'plan_created_by' => $plan->created_by,
                'user_id'         => $user->id,
                'email'           => $user->email,
                'roles'           => method_exists($user, 'getRoleSlugsAttribute')
                                        ? $user->getRoleSlugsAttribute()
                                        : null,
                'is_super_admin'  => method_exists($user, 'isSuperAdmin')
                                        ? $user->isSuperAdmin()
                                        : null,
                'is_admin'        => method_exists($user, 'isAdmin')
                                        ? $user->isAdmin()
                                        : null,
                'token_abilities' => optional($user->currentAccessToken())
                                        ->abilities,
            ]);
        }

        return $allowed;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => 'required|in:draft,assigned,in_progress',
        ]);
    }
}