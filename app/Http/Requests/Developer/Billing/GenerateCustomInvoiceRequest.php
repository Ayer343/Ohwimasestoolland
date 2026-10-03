<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCustomInvoiceRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'amount' => 'required|numeric|min:1|max:100000',
            'description' => 'required|string|max:500',
            'invoice_type' => 'required|in:one_time,additional,penalty,adjustment',
            'due_date' => 'required|date|after_or_equal:today',
        ];
    }
}