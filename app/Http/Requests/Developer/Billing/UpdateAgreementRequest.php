<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgreementRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'amount' => 'required|numeric|min:100|max:100000',
            'description' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'change_reason' => 'required|string|max:500',
            'effective_date' => 'required|date|after_or_equal:today',
            'regenerate_pdf' => 'nullable|boolean',
            'resend_for_signature' => 'nullable|boolean',
        ];
    }
}