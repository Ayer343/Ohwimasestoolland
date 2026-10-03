<?php

namespace App\Http\Requests\Developer\Billing;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPaymentRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->type === \App\Models\User::TYPE_DEVELOPER;
    }

    public function rules()
    {
        return [
            'payment_id' => 'required|exists:agreement_payments,id',
            'amount_paid' => 'required|numeric|min:0',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|in:mtn,telecel,airteltigo,bank_transfer,cash',
            'transaction_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ];
    }
}