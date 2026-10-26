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
        return [
            'type'   => ['required', 'string', 'in:' . implode(',', array_keys(User::getUserTypes()))],
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(User::getUserStatuses()))],
            'name'   => ['required', 'string', 'max:255'],

            // Email is optional (validated only if provided)
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],

            // Phone is required (primary identifier)
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],

            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],

            'auto_verify_email' => ['sometimes', 'boolean'],
            'auto_verify_phone' => ['sometimes', 'boolean'],
            'send_invitation'   => ['required', 'boolean'],

            // Password required only when NOT sending invitation
            'password'              => ['required_if:send_invitation,0', 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required_if:send_invitation,0', 'nullable', 'string', 'min:8'],

            // Invitation fields required only when sending invitation
            'invitation_channels'   => ['required_if:send_invitation,1', 'array', 'min:1'],
            'invitation_channels.*' => ['string', 'in:sms,email,whatsapp'],
            'invitation_type'       => ['required_if:send_invitation,1', 'string', 'in:welcome,registration,account_setup,password_setup'],
            'expires_in_days'       => ['nullable', 'integer', 'min:1', 'max:30'],
            'invitation_message'    => ['nullable', 'string', 'max:1000'],

            // ============================================================
            // SECURITY — eligibility is a single boolean, certifications
            // are optional free-text. No rank/level/score.
            // ============================================================
            'security_can_be_supervisor'         => ['sometimes', 'boolean'],
            'security_supervisor_certifications' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select a user type.',
            'type.in'       => 'The selected user type is invalid.',

            'status.required' => 'Please select a status.',
            'status.in'       => 'The selected status is invalid. Valid statuses are: ' . implode(', ', array_keys(User::getUserStatuses())),

            'name.required' => 'Full name is required.',

            'email.email'  => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',

            'phone.required' => 'Phone number is required.',
            'phone.unique'   => 'This phone number is already registered.',

            'password.required_if' => 'Password is required when not sending an invitation.',
            'password.min'         => 'Password must be at least 8 characters.',
            'password.confirmed'   => 'Password confirmation does not match.',

            'invitation_channels.required_if' => 'Please select at least one invitation channel.',
            'invitation_channels.min'         => 'Please select at least one invitation channel.',
            'invitation_channels.*.in'        => 'Invalid invitation channel selected.',
            'invitation_type.required_if'     => 'Please select an invitation type.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_invitation'            => filter_var($this->input('send_invitation'), FILTER_VALIDATE_BOOLEAN),
            'auto_verify_email'          => filter_var($this->input('auto_verify_email', false), FILTER_VALIDATE_BOOLEAN),
            'auto_verify_phone'          => filter_var($this->input('auto_verify_phone', false), FILTER_VALIDATE_BOOLEAN),
            'security_can_be_supervisor' => filter_var($this->input('security_can_be_supervisor', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        // Normalize empty email string → null so `nullable` rule behaves correctly
        if ($this->has('email') && trim((string) $this->input('email')) === '') {
            $this->merge(['email' => null]);
        }

        // Normalize empty certifications → null
        if ($this->has('security_supervisor_certifications')
            && trim((string) $this->input('security_supervisor_certifications')) === '') {
            $this->merge(['security_supervisor_certifications' => null]);
        }

        // Default status
        if (!$this->has('status')) {
            $this->merge(['status' => 'active']);
        }

        // Sending invitation → force status to pending
        if ($this->boolean('send_invitation')) {
            $this->merge(['status' => User::STATUS_PENDING]);
        }

        // Normalize phone number if provided
        if ($this->filled('phone')) {
            $this->merge(['phone' => $this->normalizePhoneNumber($this->input('phone'))]);
        }
    }

    /**
     * Normalize phone number to E.164 format.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (preg_match('/^0/', $phone)) {
            $phone = '+233' . substr($phone, 1);
        }

        if (preg_match('/^233/', $phone) && !preg_match('/^\+/', $phone)) {
            $phone = '+' . $phone;
        }

        if (!preg_match('/^\+/', $phone)
            && !preg_match('/^0/', $phone)
            && !preg_match('/^233/', $phone)) {
            $phone = '+233' . $phone;
        }

        return $phone;
    }
}