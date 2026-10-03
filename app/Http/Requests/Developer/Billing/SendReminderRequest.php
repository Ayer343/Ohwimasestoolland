<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class SendReminderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Only developer (type 5) or super admin (type 0) can send reminders.
        return $user
            && in_array($user->type, [0, 5], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Accepts either JSON (AJAX from the agreements-list blade)
     * or form-encoded input from older views.
     */
    public function rules(): array
    {
        return [
            'reminder_type' => 'nullable|string|in:payment,signature,general',
            'message'       => 'nullable|string|max:1000',
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'reminder_type.in' => 'Reminder type must be one of: payment, signature, general.',
            'message.max'      => 'Message cannot exceed 1000 characters.',
        ];
    }

    /**
     * Ensure JSON responses are returned for AJAX/JSON requests,
     * so the blade's fetch() receives a clean JSON error shape.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->expectsJson() || $this->ajax()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Same for authorization failures — return JSON instead of a redirect.
     */
    protected function failedAuthorization()
    {
        if ($this->expectsJson() || $this->ajax()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to send reminder.',
                ], 403)
            );
        }

        parent::failedAuthorization();
    }
}