<?php

namespace App\Http\Requests\Api;

use App\Models\RegistrationPlan;
use App\Rules\FieldAgentExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreRegistrationPlanRequest extends FormRequest
{
    /**
     * Authorizes the request through the RegistrationPlanPolicy.
     *
     * The policy must be registered (auto-discovered or listed in
     * AuthServiceProvider::$policies). Without it, `can()` returns false
     * for EVERY user — including super admins — and the API returns 403.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            // No authenticated user on the request — Sanctum token missing
            // or invalid. Return false; the middleware usually catches this
            // as 401 before we get here.
            return false;
        }

        $allowed = $user->can('create', RegistrationPlan::class);

        // TEMP DEBUG: log the exact reason for a deny. Remove once the
        // policy is confirmed working in production.
        if (!$allowed) {
            Log::warning('RegistrationPlan create denied', [
                'user_id'       => $user->id,
                'email'         => $user->email,
                'roles'         => method_exists($user, 'getRoleSlugsAttribute')
                                    ? $user->getRoleSlugsAttribute()
                                    : null,
                'is_super_admin'=> method_exists($user, 'isSuperAdmin')
                                    ? $user->isSuperAdmin()
                                    : null,
                'is_admin'      => method_exists($user, 'isAdmin')
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
        return [
            // Single agent
            'assigned_agent_id'  => 'nullable|exists:users,id',
            'agent_name'         => 'nullable|string|max:255',
            'agent_phone'        => 'nullable|string|max:20',
            'agent_email'        => 'nullable|email',
            'invitation_method'  => 'nullable|in:sms,whatsapp,email,all_channels',

            // Multiple agents
            'agent_types'          => 'nullable|array',
            'agent_types.*'        => 'in:existing,new',
            'assigned_agent_ids'   => 'nullable|array',
            'assigned_agent_ids.*' => ['nullable', new FieldAgentExists],
            'agent_phones'         => 'nullable|array',
            'agent_phones.*'       => 'nullable|string|max:20',
            'agent_names'          => 'nullable|array',
            'agent_names.*'        => 'nullable|string|max:255',
            'agent_emails'         => 'nullable|array',
            'agent_emails.*'       => 'nullable|email',
            'invitation_methods'   => 'nullable|array',
            'invitation_methods.*' => 'nullable|in:sms,whatsapp,email,all_channels',

            // Plan config
            'zone'                    => 'required|string|max:100',
            'section'                 => 'nullable|string|max:100',
            'naming_pattern'          => 'required|string|max:100',
            'custom_pattern'          => 'nullable|string|max:100',
            'starting_point'          => 'required|string|max:50',
            'estimated_houses'        => 'required|integer|min:1|max:1000',
            'sequence_type'           => 'required|in:sequential,even_only,odd_only',
            'registration_start_date' => 'nullable|date',
            'registration_end_date'   => 'nullable|date|after_or_equal:registration_start_date',
            'instructions'            => 'nullable|string',
            'boundaries_description'  => 'nullable|string',
            'agent_assignment_type'   => 'required|in:single,multiple',
        ];
    }
}