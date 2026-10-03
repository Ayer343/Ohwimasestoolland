<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class SendAgreementRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'send_to_developer' => 'nullable|boolean',
            'send_to_super_admin' => 'nullable|boolean',
            'message' => 'nullable|string|max:1000',
        ];
    }
}