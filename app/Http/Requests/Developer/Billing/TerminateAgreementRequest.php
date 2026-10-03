<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class TerminateAgreementRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'termination_reason' => 'required|string|max:500',
            'effective_date' => 'required|date|after_or_equal:today'
        ];
    }
}