<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currentUser = auth()->user();

        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        $userId = $this->route('id') ?? $this->route('user');

        if (!$userId) {
            return false;
        }

        $targetUser = User::find($userId);

        if (!$targetUser) {
            return false;
        }

        if ($targetUser->type === User::TYPE_DEVELOPER && $targetUser->id !== $currentUser->id) {
            return false;
        }

        if ($targetUser->type === User::TYPE_SUPER_ADMIN && !$currentUser->isSuperAdmin()) {
            return false;
        }

        if ($targetUser->type === User::TYPE_ADMIN && $targetUser->id !== $currentUser->id && !$currentUser->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user');

        return [
            // Basic Info
            'name' => ['required', 'string', 'max:255'],

            // Email is optional (validated only if provided)
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],

            // Phone is required (primary identifier)
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],

            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'type'     => ['required', 'string', Rule::in(array_keys(User::getUserTypes()))],
            'status'   => ['required', 'string', Rule::in(array_keys(User::getUserStatuses()))],

            // Additional profile fields
            'gender'          => ['nullable', 'string', 'in:male,female,other'],
            'dob'             => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'region'          => ['nullable', 'string', 'max:255'],
            'digital_address' => ['nullable', 'string', 'max:100'],
            'location'        => ['nullable', 'string', 'max:500'],

            // Verification options
            'auto_verify_email'        => ['sometimes', 'boolean'],
            'auto_verify_phone'        => ['sometimes', 'boolean'],
            'remove_phone_verification'=> ['sometimes', 'boolean'],

            // ============================================================
            // SECURITY — eligibility is a single boolean, certifications
            // are optional free-text. No rank/level/score.
            // ============================================================
            'security_can_be_supervisor'         => ['sometimes', 'boolean'],
            'security_supervisor_certifications' => ['nullable', 'string', 'max:2000'],

            // Photo fields
            'photo'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',

            'email.email'  => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered to another user.',

            'phone.required' => 'Phone number is required.',
            'phone.unique'   => 'This phone number is already registered to another user.',

            'type.required'   => 'Please select a user type.',
            'status.required' => 'Please select a status.',

            'gender.in'    => 'Please select a valid gender option.',
            'dob.date'     => 'Please enter a valid date of birth.',
            'dob.before'   => 'Date of birth must be in the past.',

            'photo.image'  => 'Please upload a valid image file.',
            'photo.max'    => 'Photo size must be less than 5MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_verify_email'          => filter_var($this->input('auto_verify_email', false), FILTER_VALIDATE_BOOLEAN),
            'auto_verify_phone'          => filter_var($this->input('auto_verify_phone', false), FILTER_VALIDATE_BOOLEAN),
            'remove_phone_verification'  => filter_var($this->input('remove_phone_verification', false), FILTER_VALIDATE_BOOLEAN),
            'security_can_be_supervisor' => filter_var($this->input('security_can_be_supervisor', false), FILTER_VALIDATE_BOOLEAN),
            'remove_photo'               => filter_var($this->input('remove_photo', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        // Normalize empty email string → null
        if ($this->has('email') && trim((string) $this->input('email')) === '') {
            $this->merge(['email' => null]);
        }

        // Normalize empty certifications → null
        if ($this->has('security_supervisor_certifications')
            && trim((string) $this->input('security_supervisor_certifications')) === '') {
            $this->merge(['security_supervisor_certifications' => null]);
        }

        // Handle empty date fields
        if (empty($this->input('dob'))) {
            $this->merge(['dob' => null]);
        }

        // Handle empty string fields as null
        $nullFields = ['region', 'digital_address', 'location', 'gender'];
        foreach ($nullFields as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        // Normalize phone if present (matches store() behavior)
        if ($this->filled('phone')) {
            $phone = preg_replace('/[^0-9+]/', '', $this->input('phone'));

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

            $this->merge(['phone' => $phone]);
        }
    }
}