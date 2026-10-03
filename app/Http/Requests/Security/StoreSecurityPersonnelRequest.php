<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class StoreSecurityPersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        
        Log::info('🔍 StoreSecurityPersonnelRequest::authorize() called', [
            'user_id' => $user->id ?? 'null',
            'user_name' => $user->name ?? 'null',
            'user_type' => $user->type ?? 'null',
            'supervisor_level' => $user->supervisor_level ?? 'null',
            'can_be_supervisor' => $user->can_be_supervisor ?? 'null',
            'roles' => $user->roles->pluck('slug')->toArray(),
            'has_area_supervisor_role' => $user->hasRole('area_supervisor'),
        ]);
        
        if (!$user) {
            Log::info('❌ Form Request - No user logged in');
            return false;
        }
        
        // ✅ 1. Super Admins and Admins can always create
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            Log::info('✅ Form Request - Super Admin/Admin allowed');
            return true;
        }
        
        // ✅ 2. Area Supervisors (by role) can create
        if ($user->hasRole('area_supervisor') || $user->hasRole('post_commander')) {
            Log::info('✅ Form Request - Area Supervisor/Post Commander allowed');
            return true;
        }
        
        // ✅ 3. Security Personnel with supervisor_level >= 2 and can_be_supervisor = true
        if ($user->type === 6 && // USER_TYPE_SECURITY_PERSONNEL
            isset($user->supervisor_level) && 
            $user->supervisor_level >= 2 && 
            $user->can_be_supervisor) {
            Log::info('✅ Form Request - Security Personnel with supervisor level >= 2 allowed', [
                'supervisor_level' => $user->supervisor_level,
                'user_id' => $user->id,
            ]);
            return true;
        }
        
        // ✅ 4. Check active supervisor assignments with manage_personnel permission
        $hasAssignment = \App\Models\SecuritySupervisorAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereJsonContains('metadata->permissions', 'manage_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'add_personnel')
                  ->orWhereJsonContains('metadata->permissions', 'manage_security_team');
            })
            ->exists();
        
        if ($hasAssignment) {
            Log::info('✅ Form Request - Has manage_personnel permission allowed');
            return true;
        }
        
        Log::info('❌ Form Request - All checks failed - denied', [
            'user_id' => $user->id,
            'user_type' => $user->type,
            'supervisor_level' => $user->supervisor_level ?? 'null',
            'can_be_supervisor' => $user->can_be_supervisor ?? 'null',
        ]);
        return false;
    }

    public function rules(): array
    {
        return [
            // Personal Information
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'username' => 'nullable|string|max:50|unique:users,username',

            // Security Settings
            'password' => 'nullable|string|min:8|confirmed',
            
            // Status & Type
            'status' => ['required', Rule::in(['active', 'pending', 'inactive', 'suspended'])],
            
            // Post Assignment
            'security_post_id' => 'nullable|exists:security_posts,id',
            
            // Supervisor Assignment (Optional)
            'assign_supervisor' => 'sometimes|boolean',
            
            // ✅ FIX: Only validate supervisor_level if assign_supervisor is true AND has a value
            'supervisor_level' => [
                'nullable',
                'required_if:assign_supervisor,true',
                Rule::in([1, 2])
            ],
            'supervisor_score' => 'nullable|integer|min:0|max:100',
            'start_date' => [
                'nullable',
                'required_if:assign_supervisor,true',
                'date'
            ],
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_primary_supervisor' => 'sometimes|boolean',

            // Invitation Settings
            'send_invitation' => 'sometimes|boolean',
            'invitation_channels' => 'array|in:sms,whatsapp,email',
            'invitation_type' => 'in:welcome,registration,account_setup,password_setup',
            'invitation_message' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'Phone number is required.',
            'phone.unique' => 'This phone number is already registered.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'supervisor_level.required_if' => 'Supervisor level is required when assigning supervisor role.',
            'supervisor_level.in' => 'Supervisor level must be Team Lead (1) or Section Lead (2).',
            'start_date.required_if' => 'Start date is required when assigning supervisor role.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'invitation_channels.in' => 'Invalid invitation channel selected.',
            'invitation_type.in' => 'Invalid invitation type selected.',
        ];
    }
}