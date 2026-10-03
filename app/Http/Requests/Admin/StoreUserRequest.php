<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', 'in:' . implode(',', array_keys(User::getUserTypes()))],
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(User::getUserStatuses()))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'auto_verify_email' => ['sometimes', 'boolean'],
            'auto_verify_phone' => ['sometimes', 'boolean'],
            'send_invitation' => ['required', 'boolean'],
            
            // Password validation (required when NOT sending invitation)
            'password' => ['required_if:send_invitation,0', 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required_if:send_invitation,0', 'nullable', 'string', 'min:8'],
            
            // Invitation fields (required when sending invitation)
            'invitation_channels' => ['required_if:send_invitation,1', 'array', 'min:1'],
            'invitation_channels.*' => ['string', 'in:sms,email,whatsapp'],
            'invitation_type' => ['required_if:send_invitation,1', 'string', 'in:welcome,registration,account_setup,password_setup'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'invitation_message' => ['nullable', 'string', 'max:1000'],
            
            // Supervisor fields
            'can_be_supervisor' => ['sometimes', 'boolean'],
            'supervisor_level' => ['nullable', 'integer', 'min:0', 'max:3'],
            'supervisor_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'supervisor_certifications' => ['nullable', 'json'],
        ];
        
        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select a user type.',
            'type.in' => 'The selected user type is invalid.',
            
            'status.required' => 'Please select a status.',
            'status.in' => 'The selected status is invalid. Valid statuses are: ' . implode(', ', array_keys(User::getUserStatuses())),
            
            'name.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.required' => 'Phone number is required.',
            'phone.unique' => 'This phone number is already registered.',
            
            'password.required_if' => 'Password is required when not sending an invitation.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            
            'invitation_channels.required_if' => 'Please select at least one invitation channel.',
            'invitation_channels.min' => 'Please select at least one invitation channel.',
            'invitation_channels.*.in' => 'Invalid invitation channel selected.',
            'invitation_type.required_if' => 'Please select an invitation type.',
            
            'supervisor_level.min' => 'Supervisor level must be 0 or higher.',
            'supervisor_level.max' => 'Supervisor level cannot exceed 3.',
            'supervisor_score.min' => 'Supervisor score must be at least 0.',
            'supervisor_score.max' => 'Supervisor score cannot exceed 100.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean values are properly cast
        $this->merge([
            'send_invitation' => filter_var($this->input('send_invitation'), FILTER_VALIDATE_BOOLEAN),
            'auto_verify_email' => filter_var($this->input('auto_verify_email', false), FILTER_VALIDATE_BOOLEAN),
            'auto_verify_phone' => filter_var($this->input('auto_verify_phone', false), FILTER_VALIDATE_BOOLEAN),
            'can_be_supervisor' => filter_var($this->input('can_be_supervisor', false), FILTER_VALIDATE_BOOLEAN),
        ]);
        
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge(['status' => 'active']);
        }
        
        // If sending invitation, set status to pending
        if ($this->boolean('send_invitation')) {
            $this->merge(['status' => User::STATUS_PENDING]);
        }
        
        // If supervisor_level is null or empty, set to 0
        if (!$this->filled('supervisor_level')) {
            $this->merge(['supervisor_level' => 0]);
        }
        
        // If supervisor_score is null or empty, set to 0
        if (!$this->filled('supervisor_score')) {
            $this->merge(['supervisor_score' => 0]);
        }
        
        // Normalize phone number if provided
        if ($this->filled('phone')) {
            $this->merge(['phone' => $this->normalizePhoneNumber($this->input('phone'))]);
        }
    }
    
    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If starts with 0, replace with +233
        if (preg_match('/^0/', $phone)) {
            $phone = '+233' . substr($phone, 1);
        }
        
        // If starts with 233 without +, add +
        if (preg_match('/^233/', $phone) && !preg_match('/^\+/', $phone)) {
            $phone = '+' . $phone;
        }
        
        // If no + and not starting with 0 or 233, assume Ghana format
        if (!preg_match('/^\+/', $phone) && !preg_match('/^0/', $phone) && !preg_match('/^233/', $phone)) {
            $phone = '+233' . $phone;
        }
        
        return $phone;
    }
}